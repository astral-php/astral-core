<?php

declare(strict_types=1);

namespace Core\Events;

/**
 * Contrat d'un subscriber d'événements.
 *
 * Un subscriber regroupe plusieurs registrations de listeners
 * en une seule classe. Utile pour regrouper les listeners
 * logiquement liés (ex. tous les listeners liés à l'authentification).
 *
 * Usage :
 *   final class AuthSubscriber implements SubscriberInterface
 *   {
 *       public function subscribe(EventDispatcher $dispatcher): void
 *       {
 *           $dispatcher->listen(UserRegistered::class, SendWelcomeEmail::class);
 *           $dispatcher->listen(UserLoggedIn::class, LogUserActivity::class);
 *       }
 *   }
 *
 *   // Enregistrement dans AppServiceProvider :
 *   $dispatcher->subscribe(AuthSubscriber::class);
 */
interface SubscriberInterface
{
    public function subscribe(EventDispatcher $dispatcher): void;
}
