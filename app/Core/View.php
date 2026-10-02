<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Template engine minimal: file PHP biasa di views/, dirender dengan output
 * buffering. Semua nilai dinamis di template WAJIB lewat e() (escape HTML).
 */
final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(private readonly string $directory)
    {
    }

    /**
     * Data yang tersedia di semua template (user login, token CSRF, flash).
     */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = $this->partial($template, $data);
        if ($layout === null) {
            return $content;
        }

        return $this->partial($layout, $data + ['content' => $content]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        $file = $this->directory . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Template tidak ditemukan: ' . $template);
        }

        $view = $this;
        extract($this->shared + $data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
