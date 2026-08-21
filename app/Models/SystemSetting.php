<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function value(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return filter_var(
            static::value($key, $default ? '1' : '0'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function setBool(string $key, bool $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value ? '1' : '0']
        );
    }
}
