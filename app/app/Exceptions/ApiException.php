<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Одна доменная ошибка с HTTP-статусом и коротким типом для JSON-ответа.
 * Фабрики заменяют отдельные классы исключений.
 */
final class ApiException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly int $status,
        private readonly string $type,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function invalidInput(string $message): self
    {
        return new self($message, 422, 'InvalidInputException');
    }

    public static function auth(): self
    {
        return new self('checko API key is invalid or access is denied.', 401, 'AuthException');
    }

    public static function notFound(string $message = 'checko has no data for the requested object.'): self
    {
        return new self($message, 404, 'ResourceNotFoundException');
    }

    public static function api(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 502, 'CheckoApiException', $previous);
    }

    public static function rateLimit(): self
    {
        return new self('checko API rate limit exceeded.', 429, 'RateLimitException');
    }

    public static function timeout(?Throwable $previous = null): self
    {
        return new self('checko API did not respond in time.', 504, 'TimeoutException', $previous);
    }

    public function httpStatus(): int
    {
        return $this->status;
    }

    public function errorType(): string
    {
        return $this->type;
    }
}
