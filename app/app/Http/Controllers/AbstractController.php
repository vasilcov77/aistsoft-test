<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Response\Response;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Базовый контроллер: общий рендер HTML-шаблонов из resources/views.
 */
abstract class AbstractController
{
    protected function render(string $template): ResponseInterface
    {
        $file = dirname(__DIR__, 3) . '/resources/views/' . $template;

        if (!is_file($file)) {
            throw new RuntimeException("Template {$template} is missing.");
        }

        ob_start();
        require $file;
        $html = (string) ob_get_clean();

        return Response::html($html);
    }
}
