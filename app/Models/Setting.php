<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['option', 'value'];

    /**
     * Request- and queue-job-scoped in-memory settings cache keyed by option name.
     * Automatically cleared at each queue job start via AppServiceProvider; long-running jobs
     * that require fresh values mid-execution should call flushCache() explicitly.
     *
     * @var array<string, string|null>
     */
    private static array $cache = [];

    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            if (! $setting->wasRecentlyCreated && $setting->wasChanged('option')) {
                unset(self::$cache[(string) $setting->getOriginal('option')]);
            }

            unset(self::$cache[(string) $setting->option]);
        });

        static::deleted(function (self $setting): void {
            unset(self::$cache[(string) $setting->option]);
        });
    }

    public static function flushCache(): void
    {
        self::$cache = [];
    }

    private static function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    public static function setSetting(string $key, $setting): void
    {
        $old = self::whereOption($key)->first();

        if ($old) {
            $old->value = $setting;
            $old->save();

            unset(self::$cache[$key]);

            return;
        }

        $set = new Setting();
        $set->option = $key;
        $set->value = $setting;
        $set->save();

        unset(self::$cache[$key]);
    }

    public static function setSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            self::updateOrCreate(
                [
                    'option' => $key,
                ],
                [
                    'option' => $key,
                    'value' => $value,
                ]
            );

            unset(self::$cache[(string) $key]);
        }
    }

    public static function getSetting(string $key): ?string
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $setting = static::whereOption($key)->first();
        $value = $setting ? self::normalizeValue($setting->value) : null;

        self::$cache[$key] = $value;

        return $value;
    }

    public static function getSettings(array $settings): Collection
    {
        return static::whereIn('option', $settings)
            ->get()->mapWithKeys(function ($item) {
                return [$item['option'] => $item['value']];
            });
    }
}
