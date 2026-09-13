<?php

declare(strict_types=1);

namespace Core;

use Core\Auth\Auth;
use Core\Exception\AuthorizationException;
use Core\Exception\CsrfException;
use Core\Exception\NotFoundException;
use Core\Http\ApiResponse;
use Core\ServiceProviderInterface;
use Throwable;

/**
 * Point d'entrée de l'application.
 *
 * Orchestre dans l'ordre :
 *   1. Logger             — disponible dès le début pour capturer les erreurs
 *   2. Environnement      — timezone, affichage d'erreurs
 *   3. Base de données    — création du dossier SQLite si absent
 *   4. Conteneur DI       — via config/dependencies.php
 *   5. Session            — démarrée avant tout rendu
 *   6. Partages de vue    — $session, $csrf et $auth disponibles dans toutes les vues
 *   7. ErrorHandler       — handlers PHP globaux (erreurs, fatals, exceptions hors API)
 *   8. Routeur            — via config/routes.php
 *   9. Dispatch           — résolution + pipeline middleware + contrôleur
 *
 * Seule la constante BASE_PATH doit être définie avant d'appeler run().
 */
final class Application
{
    /** @var array<string, mixed> */
    private array $appConfig = [];

    private string $basePath;
    private Logger $logger;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    // -------------------------------------------------------------------------
    // Point d'entrée public
    // -------------------------------------------------------------------------

    public function run(): void
    {
        $this->loadDotEnv();
        $this->ensureStorageDirs();

        $this->logger    = new Logger($this->basePath . '/storage/logs');
        $this->appConfig = require $this->basePath . '/config/app.php';
        $dbConfig        = require $this->basePath . '/config/database.php';

        $this->bootEnvironment($this->appConfig);
        $this->ensureDatabase($dbConfig);

        $container = new Container();
        $this->loadDependencies($container, $this->appConfig, $dbConfig);

        // Session — doit être démarrée avant tout rendu de vue
        /** @var Session $session */
        $session = $container->make(Session::class);
        $session->start();

        // Partage global dans toutes les vues
        /** @var View $view */
        $view = $container->make(View::class);
        $view->share('session', $session);
        $view->share('csrf', $container->make(CsrfGuard::class));
        $view->share('auth', $container->make(Auth::class));
        $view->share('viewEngine', $view);

        // Active set_exception_handler / set_error_handler / shutdown
        $errorHandler = $container->make(ErrorHandler::class);

        $request = $container->make(Request::class);
        $router  = new Router(request: $request, container: $container);
        $this->loadRoutes($router);

        $isApiRequest = str_starts_with($request->uri, '/api/');

        try {
            $router->dispatch();
        } catch (Throwable $e) {
            // Les routes API conservent des réponses JSON structurées.
            // Le HTML (et le reste) délègue à ErrorHandler.
            if ($isApiRequest) {
                $this->handleApiException($e);
                return;
            }

            $errorHandler->handleException($e);
        }
    }

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    /**
     * Charge le fichier .env via vlucas/phpdotenv.
     * Utilise safeLoad() : aucune exception si .env est absent
     * (utile en production où les variables sont injectées par le serveur).
     */
    private function loadDotEnv(): void
    {
        $dotenv = \Dotenv\Dotenv::createImmutable($this->basePath);
        $dotenv->safeLoad();
    }

    /** @param array<string, mixed> $config */
    private function bootEnvironment(array $config): void
    {
        date_default_timezone_set((string) ($config['timezone'] ?? 'UTC'));

        if ($config['debug'] ?? false) {
            ini_set('display_errors', '0'); // ErrorHandler affiche les détails
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            error_reporting(0);
        }
    }

    /** Crée les répertoires storage/ nécessaires s'ils n'existent pas. */
    private function ensureStorageDirs(): void
    {
        foreach (['storage/logs', 'storage/cache'] as $dir) {
            $path = $this->basePath . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /** @param array<string, mixed> $dbConfig */
    private function ensureDatabase(array $dbConfig): void
    {
        if (($dbConfig['driver'] ?? '') === 'sqlite') {
            $dir = dirname((string) $dbConfig['database']);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Chargement des fichiers de configuration
    // -------------------------------------------------------------------------

    /**
     * Charge la liste des Service Providers depuis config/dependencies.php
     * et appelle register() sur chacun d'eux.
     *
     * @param array<string, mixed> $appConfig
     * @param array<string, mixed> $dbConfig
     */
    private function loadDependencies(Container $container, array $appConfig, array $dbConfig): void
    {
        /** @var list<class-string<ServiceProviderInterface>> $providers */
        $providers = require $this->basePath . '/config/dependencies.php';

        foreach ($providers as $providerClass) {
            (new $providerClass())->register($container, $appConfig, $dbConfig);
        }
    }

    private function loadRoutes(Router $router): void
    {
        $register = require $this->basePath . '/config/routes.php';
        $register($router);
    }

    // -------------------------------------------------------------------------
    // Gestion des erreurs API (JSON)
    // -------------------------------------------------------------------------

    private function handleApiException(Throwable $e): void
    {
        if ($e instanceof NotFoundException) {
            ApiResponse::notFound($e->getMessage())->send();
            return;
        }

        if ($e instanceof AuthorizationException || $e instanceof CsrfException) {
            ApiResponse::forbidden($e->getMessage())->send();
            return;
        }

        $this->logger->error($e->getMessage(), [
            'class' => get_class($e),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
        ]);

        $message = ($this->appConfig['debug'] ?? false)
            ? $e->getMessage()
            : 'Erreur interne du serveur.';

        ApiResponse::error('SERVER_ERROR', $message, 500)->send();
    }
}
