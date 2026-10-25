<?php

namespace App\Models;

use App\Support\Demo;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * One app-wide setting, stored as JSON under its key.
 *
 * @property string $key
 * @property mixed $value
 */
#[Unguarded]
class Setting extends Model
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return Demo::preference("settings.{$key}", $setting === null ? $default : $setting->value);
    }

    public static function put(string $key, mixed $value): void
    {
        Demo::savePreference("settings.{$key}", $value, fn () => static::query()->updateOrCreate(['key' => $key], ['value' => $value]));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
