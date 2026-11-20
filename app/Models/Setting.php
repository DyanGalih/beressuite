<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['option', 'value'];

    /**
     * Request-scoped in-memory settings cache keyed by option name.
     *
     * @var array<string, string|null>
     */
    private static array $cache = [];

    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            self::$cache[(string) $setting->option] =
                $setting->value === null ? null : (string) $setting->value;
        });

        static::deleted(function (self $setting): void {
            unset(self::$cache[(string) $setting->option]);
        });
    }

    public static function flushCache(): void
    {
        self::$cache = [];
    }

    public static function setSetting(string $key, $setting): void
    {
        $old = self::whereOption($key)->first();

        if ($old) {
            $old->value = $setting;
            $old->save();

            self::$cache[$key] = $setting === null ? null : (string) $setting;

            return;
        }

        $set = new Setting();
        $set->option = $key;
        $set->value = $setting;
        $set->save();

        self::$cache[$key] = $setting === null ? null : (string) $setting;
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

            self::$cache[(string) $key] = $value === null ? null : (string) $value;
        }
    }

    public static function getSetting(string $key): ?string
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $setting = static::whereOption($key)->first();
        $value = $setting ? ($setting->value === null ? null : (string) $setting->value) : null;

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
