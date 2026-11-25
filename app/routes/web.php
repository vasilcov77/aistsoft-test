<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\SwaggerController;
use App\Http\Response\Response;
use App\Http\Router;
use Psr\Http\Message\ResponseInterface;

/**
 * Веб-маршруты: корень, демо-страница, Swagger UI и OpenAPI-спека.
 */
return static function (
    Router $router,
    DemoController $demo,
    SwaggerController $swagger,
    string $appRoot,
): void {
    $router->get('/', static fn (): ResponseInterface => Response::json(200, [
        'name' => (string) config('app.name'),
        'version' => '1.0.0',
        'demo' => '/demo',
        'docs' => '/docs',
        'openapi' => '/openapi.yaml',
        'api' => '/api/v1/',
        'health' => '/api/v1/health',
    ]));
    $router->get('/demo', $demo->show(...));
    $router->get('/docs', $swagger->show(...));
    $router->get('/openapi.yaml', static function () use ($appRoot): ResponseInterface {
        $file = $appRoot . '/openapi.yaml';

        if (!is_file($file)) {
            throw ApiException::notFound('OpenAPI spec not found.');
        }

        return Response::text(200, (string) file_get_contents($file), 'application/yaml; charset=utf-8');
    });
};
