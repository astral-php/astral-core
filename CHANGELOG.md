# Changelog

## [2.2.0] — 2026-11-13 (feuille 2.2 · CLI)

### Ajouté

- `Core\Console\ConsoleFactory` — bootstrap commun, migrations en lazy PDO
- `Core\Console\Input` — parse arguments / options
- `serve` — serveur de développement PHP (`public/` + `router.php`)
- Chargement `config/console.php` → commandes `app/Console/`

### Corrigé / amélioré

- CLI-01/02 : plus de connexion DB au démarrage pour `list` / `make:*`
- CLI-03 : `make:module` propage les échecs (code ≠ 0)
- CLI-06 : `make:migration` refuse collision fichier / classe
- CLI-07 : `error` / `warning` sur STDERR
- CLI-08 : exceptions de commande → message + code 1
- CLI-09 : doublon d’enregistrement → avertissement explicite
- `<commande> --help` / `-h` sans exécution (ex. pas de génération `Help`)
- Options globales : `--no-ansi`, `--no-interaction`

## [2.1.2] — 2026-10-31

### Corrigé

- `Console::register()` : retrait de `#[\Override]` sans méthode parente (fatal PHP 8.3).

## [2.1.1] — 2026-10-31

### Corrigé

- `Core\Console\Console::$commands` : retrait de `readonly` avec valeur par défaut (fatal PHP 8.3 — bloquait tout `bin/console`).

## [2.1.0] — 2026-10-30

### Ajouté

- Value objects opt-in : `Core\ValueObject\EmailAddress`, `Core\ValueObject\UserId`
- `#[\Override]` sur middlewares, providers et commandes console
- Constantes de classe typées (`Auth`, `Session`, `CsrfGuard`, `Migrator`)
- `#[\SensitiveParameter]` sur le mot de passe `Mailer`

### Modifié

- Dépendances ctor immuables marquées `readonly` (Auth, Cache, Logger, Mailer, Csrf, Events, Router deps, middlewares, CLI, Migrator, AbstractDao PDO, AbstractController View, chemins View, …)

### Notes

- **Pas de breaking API** sur Router / Container / Request / Response / Auth / Mailer / DAO.
- Guide : [`UPGRADE-2.1.md`](./UPGRADE-2.1.md).

## [2.0.0] — 2026-10-28

### Breaking

- PHP minimum : **`^8.3`** (drop 8.1 / 8.2).
- `Core\Auth\Role` : class + constantes → **backed enum** `Role::Admin` / `Role::User` / `Role::Guest` (valeurs string inchangées : `admin`, `user`, `guest`).
- `Auth::is()` / `Auth::can()` : acceptent `Role|string`.
- `Core\Cache` :
  - `has()` / `remember()` traitent correctement une valeur stockée `null` (hit).
  - `unserialize(..., ['allowed_classes' => false])`.
  - TTL `< 1` → `InvalidArgumentException`.
  - Échec `mkdir` / écriture → `RuntimeException`.
  - Payload invalide / objets → miss + purge.

### Ajouté

- Spec / comportements cache documentés (UPGRADE + infos audit 2.0).
- Tests `AuthTest`, `RoleTest` ; CacheTest étendu (null, TTL, payload).

### Notes

- Anti-stampede `remember` : toujours best-effort (LOCK_EX écriture seule) — cache avancé → package **astral-cache** (plus tard).
- Guide migration : [`UPGRADE-2.0.md`](./UPGRADE-2.0.md).

## [1.2.4] — 2026-10-08

### Ajouté

- Suite **PHPUnit** dans le package (`tests/Core`, `tests/Database`, fixtures).
- `phpunit.xml` + `autoload-dev` + script `composer test`.
- `Router::handle()` — résout la route et retourne la `Response` **sans** `send()` (tests d’intégration apps).

### Modifié

- `Router::dispatch()` délègue à `handle()` puis envoie la réponse.
- `ErrorHandler::render()` n’appelle `http_response_code()` / `header()` que si les en-têtes ne sont pas encore envoyés (CLI / PHPUnit).
- `Session::start()` : en CLI / si en-têtes déjà envoyés, initialise `$_SESSION` sans `session_start()` (smokes PHPUnit).

## [1.2.3] — 2026-09-13

### Ajouté

- Première publication du package **`astral-php/astral-core`** (library).
- Contenu extrait d’Astral MVC 1.2.2 : `Core\`, `Database\`, `Controller\`, `helpers.php` (`dump` / `dd`).
- Dépendances : `vlucas/phpdotenv`, `phpmailer/phpmailer`.

Versions **alignées** avec l’application `astral-php/astral` **1.2.3**.
