<?php

declare(strict_types=1);

namespace Core;

use RuntimeException;

/**
 * Moteur de rendu de vues (layout + template).
 *
 * Utilise une zone de tampon (output buffering) pour injecter le contenu
 * d'une vue dans un layout principal.
 */
final class View
{
    private string $viewsPath;
    private string $layoutsPath;
    private string $layout;

    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(string $basePath, string $layout = 'main')
    {
        $this->layout      = $layout;
        $this->viewsPath   = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'views';
        $this->layoutsPath = $this->viewsPath . DIRECTORY_SEPARATOR . 'layout';
    }

    // -------------------------------------------------------------------------
    // API publique
    // -------------------------------------------------------------------------

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    /**
     * Rend une vue dans le layout courant et retourne le HTML complet.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        $content = $this->renderPartial($view, $data);
        return $this->renderLayout($content, $data);
    }

    /**
     * Alias court pour renderPartial : inclut un partial depuis une vue ou le layout.
     *
     * @param  array<string, mixed> $data
     */
    public function partial(string $view, array $data = []): string
    {
        return $this->renderPartial($view, $data);
    }

    /**
     * Rend une vue partielle (sans layout) et retourne son HTML.
     *
     * @param  array<string, mixed> $data
     */
    public function renderPartial(string $view, array $data = []): string
    {
        $file = $this->resolvePath($this->viewsPath, $view);
        extract(array_merge($this->shared, $data), EXTR_SKIP);

        ob_start();
        require $file;
        return ob_get_clean() ?: '';
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function renderLayout(string $content, array $data = []): string
    {
        $file = $this->resolvePath($this->layoutsPath, $this->layout);
        extract(array_merge($this->shared, $data), EXTR_SKIP);

        ob_start();
        require $file;
        return ob_get_clean() ?: '';
    }

    private function resolvePath(string $base, string $name): string
    {
        if (str_contains($name, '..')) {
            throw new RuntimeException("Nom de vue invalide : {$name}");
        }

        $file = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php';

        if (!file_exists($file)) {
            throw new RuntimeException("Vue introuvable : {$file}");
        }

        return $file;
    }
}
