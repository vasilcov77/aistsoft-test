<?php

declare(strict_types=1);

use App\Application;
use App\Support\Config;
use Dotenv\Dotenv;

$appRoot = dirname(__DIR__);

if (is_file($appRoot . '/.env') || is_file(dirname($appRoot) . '/.env')) {
    Dotenv::createImmutable([$appRoot, dirname($appRoot)])->safeLoad();
}

Config::load($appRoot . '/config');

if (config('checko.token') === '') {
    throw new RuntimeException('TOKEN is required (checko API key).');
}

return Application::create($appRoot);
