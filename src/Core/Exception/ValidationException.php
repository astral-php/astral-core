<?php

declare(strict_types=1);

namespace Core\Exception;

use RuntimeException;

/**
 * Levée quand la validation échoue et qu'on souhaite
 * propager les erreurs jusqu'au gestionnaire global.
 *
 * Usage dans un contrôleur :
 *   $v = Validator::make($data, $rules);
 *   if ($v->fails()) {
 *       throw new ValidationException($v->errors());
 *   }
 */
final class ValidationException extends RuntimeException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(
        private array $errors,
        string $message = 'Les données soumises sont invalides.',
    ) {
        parent::__construct($message);
    }

    /** @return array<string, list<string>> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
