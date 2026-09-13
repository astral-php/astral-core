<?php

declare(strict_types=1);

namespace Core\Exception;

use RuntimeException;

/**
 * Levée quand un utilisateur tente d'accéder à une ressource
 * pour laquelle il n'a pas les droits (HTTP 403).
 */
final class AuthorizationException extends RuntimeException {}
