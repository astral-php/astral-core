# Upgrade guide — astral-core 1.x → 2.0

## Prérequis

- PHP **8.3+**
- `"astral-php/astral-core": "^2.0"`

## Role

Remplacer les constantes de classe :

| 1.x | 2.0 |
|-----|-----|
| `Role::ADMIN` | `Role::Admin` |
| `Role::USER` | `Role::User` |
| `Role::GUEST` | `Role::Guest` |

Les **valeurs** string (`'admin'`, `'user'`, `'guest'`) et `Role::all()` / `Role::isValid()` restent valides.

`Auth::is()` / `can()` acceptent l’enum **ou** une string.

```php
$auth->is(Role::Admin);
$auth->is('admin'); // OK
```

`Auth::role()` retourne toujours une **string** (valeur).

## Cache

- Stocker `null` est désormais un **hit** (`has` / `remember` ne retraitent plus comme miss).
- TTL doit être **≥ 1** ; plus de `set($k, $v, -1)` pour forcer l’expiration en test.
- Ne pas mettre d’**objets** en cache (lecture refusée). Structures scalaires / tableaux uniquement.
- Répertoire cache : dédié, **hors** `public/`.
- Échecs d’écriture : exceptions (`RuntimeException`).

## Apps

Mettre à jour les vues / DAOs qui référencent `Role::ADMIN` etc., puis retester Auth et cache applicatif (`FileCacheStore` shop, etc.).
