<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Psr\Http\Message\ResponseInterface;

/**
 * Демонстрационная страница работы API.
 */
final class DemoController extends AbstractController
{
    public function show(): ResponseInterface
    {
        return $this->render('demo.php');
    }
}
