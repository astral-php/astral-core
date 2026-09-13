<?php

declare(strict_types=1);

namespace Core\Events;

/**
 * Marqueur commun à tous les événements du framework.
 *
 * Chaque événement applicatif implémente cette interface afin
 * de bénéficier du typage strict dans EventDispatcher.
 *
 * Usage :
 *   final class UserRegistered implements EventInterface
 *   {
 *       public function __construct(public readonly User $user) {}
 *   }
 */
interface EventInterface {}
