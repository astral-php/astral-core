<?php

declare(strict_types=1);

namespace Core\Exception;

use RuntimeException;

/**
 * Levée quand le token CSRF est absent ou invalide (HTTP 403).
 */
final class CsrfException extends RuntimeException {}
