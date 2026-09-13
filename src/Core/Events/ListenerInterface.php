<?php

declare(strict_types=1);

namespace Core\Events;

/**
 * Contrat d'un listener d'événement.
 *
 * Usage :
 *   final class SendWelcomeEmail implements ListenerInterface
 *   {
 *       public function handle(EventInterface $event): void
 *       {
 *           assert($event instanceof UserRegistered);
 *           $this->mailer->send($event->user->email, …);
 *       }
 *   }
 */
interface ListenerInterface
{
    public function handle(EventInterface $event): void;
}
