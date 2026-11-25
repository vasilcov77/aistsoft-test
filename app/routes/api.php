<?php

declare(strict_types=1);

use App\Client\CheckoClient;
use App\Http\Controllers\CompanyController;
use App\Http\Response\Response;
use App\Http\Router;
use Psr\Http\Message\ResponseInterface;

/**
 * API-маршруты под префиксом /api/v1.
 */
return static function (Router $router, CheckoClient $client): void {
    $router->get('/api/v1/company', new CompanyController($client)->show(...));
    $router->get('/api/v1/health', static fn (): ResponseInterface => Response::json(200, ['status' => 'ok']));
};
