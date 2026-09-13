# astral-core

Moteur PHP **8.1+** d’[Astral](https://github.com/astral-php/astral) : HTTP, DI, routeur, auth, DAO, vues, migrations, CLI, ErrorHandler.

Package **`library`** — fondation des **applications** (`create-project`) et des **composants** (`require`).

[![PHP](https://img.shields.io/badge/PHP-8.1%E2%80%938.5-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![Version](https://img.shields.io/badge/version-1.2.3-blue)](./CHANGELOG.md)

> Voir le hub [`astral.md`](../../mvc/astral.md) (Apps / Core / Components).

## Installation

```bash
composer require astral-php/astral-core:^1.2
```

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

## Dépendances

- `vlucas/phpdotenv` ^5.6  
- `phpmailer/phpmailer` ^7.0  

## Usage typique

Les applications (`astral-php/astral`, futur `astral-blog`) et les composants déclarent :

```json
"require": {
  "astral-php/astral-core": "^1.2"
}
```

L’app hôte définit `BASE_PATH`, fournit `app/`, `config/`, `public/`, `views/`.

## Compatibilité

- PHP : `^8.1` (cible 8.1 → 8.5)
- Aligné sur Astral MVC **1.2.3**

## Licence

MIT
