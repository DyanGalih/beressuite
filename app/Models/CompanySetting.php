<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class CompanySetting extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'option', 'value'];

    /**
     * Request-scoped in-memory settings cache keyed by "<company_id>:<option>".
     *
     * @var array<string, string|null>
     */
    private static array $cache = [];

    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            self::$cache[self::cacheKey((string) $setting->option, $setting->company_id)] =
                $setting->value === null ? null : (string) $setting->value;
        });

        static::deleted(function (self $setting): void {
            unset(self::$cache[self::cacheKey((string) $setting->option, $setting->company_id)]);
        });
    }

    public static function flushCache(): void
    {
        self::$cache = [];
    }

    private static function cacheKey(string $key, int|string|null $company_id): string
    {
        $scope = $company_id === null ? '__null__' : (string) $company_id;

        return $scope.':'.$key;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeWhereCompany($query, $company_id)
    {
        $query->where('company_id', $company_id);
    }

    public static function setSettings(array $settings, int|string|null $company_id = null): void
    {
        foreach ($settings as $key => $value) {
            self::updateOrCreate(
                [
                    'option' => $key,
                    'company_id' => $company_id,
                ],
                [
                    'option' => $key,
                    'company_id' => $company_id,
                    'value' => $value,
                ]
            );

            self::$cache[self::cacheKey((string) $key, $company_id)] =
                $value === null ? null : (string) $value;
        }
    }

    public static function getAllSettings(int|string|null $company_id = null): Collection
    {
        return static::whereCompany($company_id)->get()->mapWithKeys(function ($item) {
            return [$item['option'] => $item['value']];
        });
    }

    public static function getSettings(array $settings, int|string|null $company_id = null): Collection
    {
        return static::whereIn('option', $settings)->whereCompany($company_id)
            ->get()->mapWithKeys(function ($item) {
                return [$item['option'] => $item['value']];
            });
    }

    public static function getSetting(string $key, int|string|null $company_id = null): ?string
    {
        $cacheKey = self::cacheKey($key, $company_id);

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        $setting = static::whereOption($key)->whereCompany($company_id)->first();
        $value = $setting ? ($setting->value === null ? null : (string) $setting->value) : null;

        self::$cache[$cacheKey] = $value;

        return $value;
    }
}
