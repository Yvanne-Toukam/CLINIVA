<?php

declare(strict_types=1);

namespace Cliniva\Middleware;

final class CsrfMiddleware
{
    public function handle(): void
    {
        require_csrf();
    }
}
