<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Client\CheckoClient;
use App\Http\Response\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CompanyController extends AbstractController
{
    public function __construct(private readonly CheckoClient $client)
    {
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $company = $this->client->fetch($request->getQueryParams());

        return Response::json(200, ['data' => $company->toArray()]);
    }
}
