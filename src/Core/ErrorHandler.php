<?php

declare(strict_types=1);

namespace Core;

use Core\Exception\AuthorizationException;
use Core\Exception\CsrfException;
use Core\Exception\NotFoundException;
use Core\Exception\ValidationException;
use ErrorException;
use Throwable;

/**
 * Gestionnaire global d'erreurs et d'exceptions pour Astral MVC.
 *
 * Activé par FrameworkServiceProvider (singleton) puis résolu au démarrage
 * dans Application::run() pour enregistrer les handlers PHP.
 *
 * Responsabilités :
 *   - Exceptions métier (404, 403, CSRF) → page dédiée (sans log, sauf CSRF → warning)
 *   - Toute autre exception               → log ERROR + page 500
 *   - Erreurs PHP (E_WARNING…)           → converties en ErrorException
 *   - Erreurs fatales (shutdown)         → log ERROR + page 500
 *
 * Les routes /api/* restent gérées par Application (réponses JSON).
 * Ce handler couvre le HTML, le bootstrap, les fatals et les exceptions non catchées.
 */
final class ErrorHandler
{
    public function __construct(
        private readonly Logger $logger,
        private readonly bool $debug = false,
        private readonly ?View $view = null,
    ) {
    }

    // ── Enregistrement ────────────────────────────────────────────────────────

    public function register(): void
    {
        set_exception_handler(function (Throwable $e): void {
            $this->handleException($e);
            exit(1);
        });

        set_error_handler($this->handleError(...));
        register_shutdown_function($this->handleShutdown(...));
    }

    // ── Handlers publics (testables) ──────────────────────────────────────────

    public function handleException(Throwable $e): void
    {
        if ($e instanceof NotFoundException) {
            $this->render(404, $e);
            return;
        }

        if ($e instanceof AuthorizationException) {
            $this->render(403, $e);
            return;
        }

        if ($e instanceof CsrfException) {
            $this->logger->warning('CSRF : ' . $e->getMessage());
            $this->render(403, $e);
            return;
        }

        if ($e instanceof ValidationException) {
            $this->logger->warning('ValidationException non interceptée : ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->render(500, $e);
            return;
        }

        $this->logger->error($e->getMessage(), [
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ]);

        $this->render(500, $e);
    }

    /**
     * Convertit les erreurs PHP en ErrorException pour une gestion uniforme.
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!($severity & error_reporting())) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Attrape les erreurs fatales que set_exception_handler ne voit pas.
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null) {
            return;
        }

        $fatals = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_CORE_WARNING,
            E_COMPILE_ERROR,
            E_COMPILE_WARNING,
        ];

        if (!in_array($error['type'], $fatals, true)) {
            return;
        }

        $this->logger->error('Erreur fatale : ' . $error['message'], [
            'file' => $error['file'],
            'line' => $error['line'],
        ]);

        $this->render(500, null);
    }

    // ── Rendu ─────────────────────────────────────────────────────────────────

    private function render(int $code, ?Throwable $e): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $titles = [
            403 => '403 — Accès refusé',
            404 => '404 — Page introuvable',
            500 => '500 — Erreur serveur',
        ];

        $defaultMessages = [
            403 => 'Vous n\'êtes pas autorisé à accéder à cette ressource.',
            404 => 'La page demandée est introuvable.',
            500 => 'Une erreur interne est survenue. Veuillez réessayer.',
        ];

        $data = [
            'title'     => $titles[$code] ?? "{$code} — Erreur",
            'message'   => $this->resolveMessage($code, $e, $defaultMessages),
            'debug'     => $this->debug,
            'exception' => $e,
        ];

        if ($this->view !== null) {
            try {
                echo $this->view->render("errors/{$code}", $data);
                return;
            } catch (Throwable) {
                // Fallback si la vue / le layout est indisponible
            }
        }

        $viewFile = $this->resolveStandaloneView($code);
        if ($viewFile !== null) {
            $debug     = $this->debug;
            $exception = $e;
            $title     = $data['title'];
            $message   = $data['message'];
            include $viewFile;
            return;
        }

        echo $this->fallback($code, $e);
    }

    /**
     * Message utilisateur : les 500 n'exposent jamais le détail technique hors bloc debug.
     *
     * @param array<int, string> $defaults
     */
    private function resolveMessage(int $code, ?Throwable $e, array $defaults): string
    {
        if ($code === 500) {
            return $defaults[500];
        }

        if ($e !== null && $e->getMessage() !== '') {
            return $e->getMessage();
        }

        return $defaults[$code] ?? 'Une erreur est survenue.';
    }

    private function resolveStandaloneView(int $code): ?string
    {
        // L'app hôte définit BASE_PATH (public/index.php, bin/console, tests).
        // Sans BASE_PATH, pas de vues applicatives — fallback HTML du package.
        if (!defined('BASE_PATH')) {
            return null;
        }

        $path = BASE_PATH . "/views/errors/{$code}.php";

        return file_exists($path) ? $path : null;
    }

    private function fallback(int $code, ?Throwable $e): string
    {
        $titles = [
            403 => '403 — Accès refusé',
            404 => '404 — Page introuvable',
            500 => '500 — Erreur serveur',
        ];

        $title = $titles[$code] ?? "{$code} — Erreur";

        if (!$this->debug || $e === null) {
            return '<!DOCTYPE html><html lang="fr"><body style="font-family:sans-serif;padding:40px">'
                . '<h1 style="color:#f38ba8">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
                . '</body></html>';
        }

        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $trace   = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
        $class   = htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8');
        $file    = htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
        $line    = $e->getLine();

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <body style="font-family:'JetBrains Mono',monospace;background:#1e1e2e;color:#cdd6f4;padding:40px;margin:0">
          <h1 style="color:#f38ba8;font-size:20px">{$title}</h1>
          <p style="color:#cba6f7;font-size:15px">{$class}</p>
          <p style="color:#a6e3a1;font-size:14px">{$message}</p>
          <p style="color:#6c7086;font-size:12px">{$file}:{$line}</p>
          <pre style="background:#181825;padding:20px;border-radius:6px;font-size:12px;
               overflow:auto;color:#cdd6f4;border-left:4px solid #89b4fa">{$trace}</pre>
        </body>
        </html>
        HTML;
    }
}
