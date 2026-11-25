<?php

declare(strict_types=1);

use App\Support\Config;

if (!function_exists('env')) {
    /** Значение из окружения; пустая строка считается отсутствующим значением. */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        // getenv() returns false for a missing variable, and false casts to ''.
        return is_scalar($value) && (string) $value !== '' ? $value : $default;
    }
}

if (!function_exists('config')) {
    /** Доступ к загруженным config/*.php по точечному ключу: config('checko.token'). */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}
