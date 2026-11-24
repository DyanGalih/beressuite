<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class CompanySetting extends Model implements AuditableContract
{
    use Auditable;
    use HasFactory;

    protected $fillable = ['company_id', 'option', 'value'];

    /**
     * Request- and queue-job-scoped in-memory settings cache keyed by "<company_id>:<option>".
     * Automatically cleared at each queue job start via AppServiceProvider; long-running jobs
     * that require fresh values mid-execution should call flushCache() explicitly.
     *
     * @var array<string, string|null>
     */
    private static array $cache = [];

    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            if (! $setting->wasRecentlyCreated && $setting->wasChanged(['company_id', 'option'])) {
                unset(self::$cache[self::cacheKey(
                    (string) $setting->getOriginal('option'),
                    $setting->getOriginal('company_id')
                )]);
            }

            unset(self::$cache[self::cacheKey((string) $setting->option, $setting->company_id)]);
        });

        static::deleted(function (self $setting): void {
            unset(self::$cache[self::cacheKey((string) $setting->option, $setting->company_id)]);
        });
    }

    public static function flushCache(): void
    {
        self::$cache = [];
    }

    public function transformAudit(array $data): array
    {
        $sensitiveOptions = config('audit.exclude', []);

        $isSensitive = in_array($this->option, $sensitiveOptions, true)
            || in_array($this->getOriginal('option'), $sensitiveOptions, true);

        if ($isSensitive) {
            if (isset($data['old_values']['value'])) {
                $data['old_values']['value'] = '[REDACTED]';
            }
            if (isset($data['new_values']['value'])) {
                $data['new_values']['value'] = '[REDACTED]';
            }
        }

        return $data;
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

            unset(self::$cache[self::cacheKey((string) $key, $company_id)]);
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
        $value = $setting ? self::normalizeValue($setting->value) : null;

        self::$cache[$cacheKey] = $value;

        return $value;
    }
}
