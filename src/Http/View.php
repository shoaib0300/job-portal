<?php

declare(strict_types=1);

namespace KaamFit\Http;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $name, array $data = []): string
    {
        $root = dirname(__DIR__) . '/Views/' . str_replace('.', '/', $name) . '.php';
        if (!is_readable($root)) {
            throw new \RuntimeException('View not found: ' . $name);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $root;
        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $data */
    public static function renderToLayout(string $title, string $name, array $data = []): void
    {
        layout_header($title);
        echo self::render($name, $data);
        layout_footer();
    }
}
