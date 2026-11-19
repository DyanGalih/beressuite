<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['option', 'value'];

    public static function setSetting(string $key, $setting): void
    {
        $old = self::whereOption($key)->first();

        if ($old) {
            $old->value = $setting;
            $old->save();

            return;
        }

        $set = new Setting();
        $set->option = $key;
        $set->value = $setting;
        $set->save();
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
        }
    }

    public static function getSetting(string $key): ?string
    {
        $setting = static::whereOption($key)->first();

        if ($setting) {
            return $setting->value;
        } else {
            return null;
        }
    }

    public static function getSettings(array $settings): Collection
    {
        return static::whereIn('option', $settings)
            ->get()->mapWithKeys(function ($item) {
                return [$item['option'] => $item['value']];
            });
    }
}
