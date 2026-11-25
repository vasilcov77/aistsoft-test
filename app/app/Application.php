<?php

declare(strict_types=1);

namespace App;

use App\Cache\RedisCache;
use App\Client\CheckoClient;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\SwaggerController;
use App\Http\ExceptionHandler;
use App\Http\Response\Response;
use App\Http\Router;
use App\Resources\CompanyResource;
use App\Transport\HttpTransport;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Ядро приложения: собирает зависимости и обрабатывает HTTP-запрос.
 */
final class Application
{
    private function __construct(private readonly Router $router)
    {
    }

    public static function create(string $appRoot): self
    {
        $transport = new HttpTransport(
            new Client(),
            (string) config('checko.base_url'),
            (string) config('checko.token'),
            (int) config('checko.timeout'),
            (int) config('checko.retries'),
        );
        $client = new CheckoClient(
            $transport,
            new CompanyResource(),
            new RedisCache(
                (string) config('cache.redis.host'),
                (int) config('cache.redis.port'),
                config('cache.redis.password'),
            ),
            (int) config('cache.ttl'),
        );

        $router = new Router();

        (require $appRoot . '/routes/web.php')(
            $router,
            new DemoController(),
            new SwaggerController(),
            $appRoot,
        );
        (require $appRoot . '/routes/api.php')($router, $client);

        return new self($router);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $exception) {
            return (new ExceptionHandler())->handle($exception);
        }
    }

    public function run(): void
    {
        Response::send($this->handle(ServerRequest::fromGlobals()));
    }
}
