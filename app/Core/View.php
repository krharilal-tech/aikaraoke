<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $content = self::renderTemplate($view, $data);

        if ($layout === null) {
            return $content;
        }

        return self::renderTemplate($layout, [...$data, 'content' => $content]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function renderTemplate(string $view, array $data): string
    {
        $path = __DIR__ . '/../Views/' . $view . '.php';

        if (!is_file($path)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $path;

        return (string) ob_get_clean();
    }
}
