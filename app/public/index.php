<?php

declare(strict_types=1);

use App\Application;
use App\Http\ExceptionHandler;
use App\Http\Response\Response;

$appRoot = dirname(__DIR__);

require $appRoot . '/vendor/autoload.php';

ini_set('display_errors', '0');

try {
    /** @var Application $app */
    $app = require $appRoot . '/bootstrap/app.php';
    $app->run();
} catch (Throwable $exception) {
    // Ошибки до создания Application (например, отсутствующий TOKEN) тоже отдаём JSON.
    Response::send((new ExceptionHandler())->handle($exception));
}
