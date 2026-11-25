<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\ApiException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Простой роутер без регулярных выражений и магии.
 */
final class Router
{
    /** @var array<string, callable(ServerRequestInterface): ResponseInterface> */
    private array $routes = [];

    /** @param callable(ServerRequestInterface): ResponseInterface $handler */
    public function get(string $path, callable $handler): void
    {
        $this->routes['GET ' . $this->normalize($path)] = $handler;
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $key = $request->getMethod() . ' ' . $this->normalize($request->getUri()->getPath());

        if (!isset($this->routes[$key])) {
            throw ApiException::notFound('Route not found.');
        }

        return ($this->routes[$key])($request);
    }

    private function normalize(string $path): string
    {
        return rtrim($path, '/') ?: '/';
    }
}
