<?php

declare(strict_types=1);

namespace Cliniva\Core;

abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $view, array $data = []): void
    {
        View::render($view, $data);
    }
}
