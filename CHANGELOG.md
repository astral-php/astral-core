# Changelog

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
