<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Psr\Http\Message\ResponseInterface;

/**
 * Страница Swagger UI с OpenAPI-спецификацией.
 */
final class SwaggerController extends AbstractController
{
    public function show(): ResponseInterface
    {
        return $this->render('swagger.php');
    }
}
