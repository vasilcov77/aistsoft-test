<?php

declare(strict_types=1);

namespace App\Http\Response;

use GuzzleHttp\Psr7\Response as Psr7Response;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-7 ответы для JSON, HTML и текста, а также отправка ответа в SAPI.
 */
final class Response
{
    /** @param array<string, mixed> $payload */
    public static function json(int $status, array $payload): ResponseInterface
    {
        return new Psr7Response(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            ),
        );
    }

    public static function html(string $html): ResponseInterface
    {
        return new Psr7Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public static function text(int $status, string $body, string $contentType): ResponseInterface
    {
        return new Psr7Response($status, ['Content-Type' => $contentType], $body);
    }

    public static function send(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }

        if (!$response->hasHeader('Cache-Control')) {
            header('Cache-Control: no-store');
        }

        echo (string) $response->getBody();
    }
}
