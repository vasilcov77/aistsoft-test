<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\ApiException;
use App\Http\Response\Response;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Переводит доменные ошибки в JSON. Внутренние детали не отдаёт клиенту.
 */
final class ExceptionHandler
{
    public function handle(Throwable $exception): ResponseInterface
    {
        if ($exception instanceof ApiException) {
            return Response::json($exception->httpStatus(), [
                'error' => [
                    'type' => $exception->errorType(),
                    'message' => $exception->getMessage(),
                    'code' => $exception->httpStatus(),
                ],
            ]);
        }

        error_log('Unhandled exception: ' . $exception::class . ': ' . $exception->getMessage());

        return Response::json(500, [
            'error' => [
                'type' => 'InternalServerError',
                'message' => 'Internal server error.',
                'code' => 500,
            ],
        ]);
    }
}
