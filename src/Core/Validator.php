<?php

declare(strict_types=1);

namespace Core;

/**
 * Validateur de données léger.
 *
 * Règles supportées :
 *   required            — champ non vide
 *   min:N               — longueur/valeur minimale
 *   max:N               — longueur/valeur maximale
 *   email               — adresse e-mail valide
 *   integer             — entier (positif ou négatif)
 *   numeric             — valeur numérique
 *   alpha               — lettres uniquement
 *   url                 — URL valide
 *   confirmed           — doit correspondre au champ {field}_confirmation
 *   in:a,b,c            — valeur dans la liste
 *
 * Usage :
 *   $v = Validator::make($_POST, ['name' => 'required|min:2', 'email' => 'required|email']);
 *   if ($v->fails()) { ... $v->errors() ... }
 */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    private function __construct() {}

    // -------------------------------------------------------------------------
    // Fabrique statique
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed>              $data
     * @param array<string, string|list<string>> $rules
     */
    public static function make(array $data, array $rules): self
    {
        $instance = new self();
        $instance->run($data, $rules);
        return $instance;
    }

    // -------------------------------------------------------------------------
    // Résultats
    // -------------------------------------------------------------------------

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Retourne la première erreur d'un champ, ou null. */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    // -------------------------------------------------------------------------
    // Moteur de validation
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed>              $data
     * @param array<string, string|list<string>> $rules
     */
    private function run(array $data, array $rules): void
    {
        foreach ($rules as $field => $fieldRules) {
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule, $data);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function applyRule(string $field, mixed $value, string $rule, array $data): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

        match ($name) {
            'required'  => $this->ruleRequired($field, $value),
            'min'       => $this->ruleMin($field, $value, (int) $param),
            'max'       => $this->ruleMax($field, $value, (int) $param),
            'email'     => $this->ruleEmail($field, $value),
            'integer'   => $this->ruleInteger($field, $value),
            'numeric'   => $this->ruleNumeric($field, $value),
            'alpha'     => $this->ruleAlpha($field, $value),
            'url'       => $this->ruleUrl($field, $value),
            'confirmed' => $this->ruleConfirmed($field, $value, $data),
            'in'        => $this->ruleIn($field, $value, explode(',', $param ?? '')),
            default     => null,
        };
    }

    // -------------------------------------------------------------------------
    // Règles individuelles
    // -------------------------------------------------------------------------

    private function ruleRequired(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->add($field, "Le champ {$field} est obligatoire.");
        }
    }

    private function ruleMin(string $field, mixed $value, int $min): void
    {
        if ($this->isEmpty($value)) {
            return;
        }
        if (is_numeric($value)) {
            if ((float) $value < $min) {
                $this->add($field, "Le champ {$field} doit être supérieur ou égal à {$min}.");
            }
        } elseif (mb_strlen((string) $value) < $min) {
            $this->add($field, "Le champ {$field} doit contenir au moins {$min} caractère(s).");
        }
    }

    private function ruleMax(string $field, mixed $value, int $max): void
    {
        if ($this->isEmpty($value)) {
            return;
        }
        if (is_numeric($value)) {
            if ((float) $value > $max) {
                $this->add($field, "Le champ {$field} doit être inférieur ou égal à {$max}.");
            }
        } elseif (mb_strlen((string) $value) > $max) {
            $this->add($field, "Le champ {$field} ne doit pas dépasser {$max} caractère(s).");
        }
    }

    private function ruleEmail(string $field, mixed $value): void
    {
        if ($this->isEmpty($value)) {
            return;
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->add($field, "Le champ {$field} doit être une adresse e-mail valide.");
        }
    }

    private function ruleInteger(string $field, mixed $value): void
    {
        if ($this->isEmpty($value)) {
            return;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->add($field, "Le champ {$field} doit être un entier.");
        }
    }

    private function ruleNumeric(string $field, mixed $value): void
    {
        if ($this->isEmpty($value)) {
            return;
        }
        if (!is_numeric($value)) {
            $this->add($field, "Le champ {$field} doit être une valeur numérique.");
        }
    }

    private function ruleAlpha(string $field, mixed $value): void
    {
        if ($this->isEmpty($value)) {
            return;
        }
        if (!ctype_alpha((string) $value)) {
            $this->add($field, "Le champ {$field} ne doit contenir que des lettres.");
        }
    }

    private function ruleUrl(string $field, mixed $value): void
    {
        if ($this->isEmpty($value)) {
            return;
        }
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->add($field, "Le champ {$field} doit être une URL valide.");
        }
    }

    /** @param array<string, mixed> $data */
    private function ruleConfirmed(string $field, mixed $value, array $data): void
    {
        if ($this->isEmpty($value)) {
            return;
        }
        if ($value !== ($data["{$field}_confirmation"] ?? null)) {
            $this->add($field, "La confirmation du champ {$field} ne correspond pas.");
        }
    }

    /** @param list<string> $allowed */
    private function ruleIn(string $field, mixed $value, array $allowed): void
    {
        if ($this->isEmpty($value)) {
            return;
        }
        if (!in_array((string) $value, $allowed, true)) {
            $this->add($field, "La valeur du champ {$field} n'est pas autorisée.");
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private function add(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
