<?php

declare(strict_types=1);

namespace App\Transport;

use App\Exceptions\ApiException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Единая точка внешних запросов: HTTP, таймауты, ретраи, распознавание ошибок.
 */
final class HttpTransport
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout,
        private readonly int $maxAttempts,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function request(ResourceInterface $resource, array $params): array
    {
        $url = $this->baseUrl . '/' . ltrim($resource->getPath(), '/');
        $attempt = 1;

        while (true) {
            try {
                $response = $this->http->request('POST', $url, [
                    'json' => ['key' => $this->apiKey] + $params,
                    'timeout' => $this->timeout,
                    'connect_timeout' => min(3, $this->timeout),
                    'http_errors' => false,
                    'headers' => ['Accept' => 'application/json', 'User-Agent' => 'checko-api/1.0'],
                ]);

                return $this->parse($response);
            } catch (ConnectException $exception) {
                if ($attempt >= $this->maxAttempts) {
                    error_log('checko request timed out after ' . $attempt . ' attempts');

                    throw ApiException::timeout($exception);
                }

                usleep(200_000 * (2 ** ($attempt - 1)));
                $attempt++;
            } catch (GuzzleException $exception) {
                throw ApiException::api('checko request failed.', $exception);
            }
        }
    }

    /** @return array<string, mixed> */
    private function parse(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        $json = json_decode((string) $response->getBody(), true);

        if (!is_array($json)) {
            throw ApiException::api('checko returned malformed JSON.');
        }

        $meta = is_array($json['meta'] ?? null) ? $json['meta'] : [];

        if ($status === 401 || $status === 403) {
            throw ApiException::auth();
        }

        if ($status === 404) {
            throw ApiException::notFound();
        }

        if ($status === 429) {
            throw ApiException::rateLimit();
        }

        if ($status !== 200) {
            throw ApiException::api(match (true) {
                $status === 402 => 'checko API balance is exhausted.',
                $status >= 500 => 'checko API is temporarily unavailable.',
                default => 'checko API returned an unexpected HTTP status.',
            });
        }

        if (($meta['status'] ?? null) !== 'ok') {
            error_log('checko error: ' . trim((string) ($meta['message'] ?? 'unknown')));

            throw ApiException::api('checko API returned an error.');
        }

        $data = $json['data'] ?? null;
        if (!is_array($data) || $data === []) {
            throw ApiException::notFound();
        }

        return $data;
    }
}
