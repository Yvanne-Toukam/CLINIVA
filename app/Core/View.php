<?php

declare(strict_types=1);

namespace Cliniva\Core;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], string $layout = 'layouts/main'): void
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require BASE_PATH . '/app/Views/' . $view . '.php';
        $content = ob_get_clean();

        require BASE_PATH . '/app/Views/' . $layout . '.php';
    }
}
