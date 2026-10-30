# astral-core

Moteur PHP **8.3+** d’[Astral](https://github.com/astral-php/astral) : HTTP, DI, routeur, auth, DAO, vues, migrations, CLI, ErrorHandler.

Package **`library`** — fondation des **applications** (`create-project`) et des **composants** (`require`).

[![PHP](https://img.shields.io/badge/PHP-8.3%E2%80%938.5-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![Version](https://img.shields.io/badge/version-2.1.0-blue)](./CHANGELOG.md)
[![Tests](https://img.shields.io/badge/tests-PHPUnit%209.6-9933CC)](./phpunit.xml)

## Installation

```bash
composer require astral-php/astral-core:^2.1
```

Migration 1.x → 2.0 : [`UPGRADE-2.0.md`](./UPGRADE-2.0.md) · 2.0 → 2.1 : [`UPGRADE-2.1.md`](./UPGRADE-2.1.md).

En local (`components-astral`) :

```json
"repositories": [
  { "type": "path", "url": "../astral-core" }
]
```

## Namespaces

| Namespace | Contenu |
|-----------|---------|
| `Core\` | Application, Router, Container, Auth, View, Console… |
| `Database\` | Connection, AbstractDao, Migrator |
| `Controller\` | AbstractController, AbstractApiController |

Helpers globaux : `dump()`, `dd()` (`src/helpers.php`).

## Tests

```bash
composer install
composer test
```

`Router::handle()` retourne la `Response` sans `send()` — pour les smokes HTTP des apps.

## Dépendances

- `vlucas/phpdotenv` ^5.6  
- `phpmailer/phpmailer` ^7.0  

## Usage typique

```json
"require": {
  "astral-php/astral-core": "^2.0"
}
```

L’app hôte définit `BASE_PATH`, fournit `app/`, `config/`, `public/`, `views/`.

## Compatibilité

- PHP : **`^8.3`**
- Feuille Astral **2.0** — voir `CHANGELOG.md` et `UPGRADE-2.0.md`

## Licence

MIT
