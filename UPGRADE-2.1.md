# Upgrade guide — astral-core 2.0 → 2.1

## Prérequis

- PHP **8.3+** (inchangé)
- `"astral-php/astral-core": "^2.1"`

## Breaking

**Aucun** pour les apps / composants qui consomment l’API publique 2.0.

Les signatures stables restent : `Auth::login(object)`, `email(): string`, `Mailer::send(string, …)`, Router, Container, Request/Response, AbstractDao, Validator, CsrfGuard.

## Nouveautés opt-in

### Value objects

```php
use Core\ValueObject\EmailAddress;
use Core\ValueObject\UserId;

$email = new EmailAddress('Alice@Example.com'); // → alice@example.com
$id    = new UserId(42);

$mailer->send((string) $email, $subject, $html); // Mailer reste en string
```

Validation e-mail alignée sur `filter_var(..., FILTER_VALIDATE_EMAIL)`.  
`UserId` exige un entier **≥ 1**.

## Internes

Modernisation PHP 8.3 (readonly deps, const typées, `#[\Override]`) — transparente pour les apps.

## Migration apps

1. Bump `composer.json` → `^2.1`
2. `composer update astral-php/astral-core`
3. Lancer la suite de tests de l’app
