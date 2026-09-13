<?php

declare(strict_types=1);

namespace Core\Events;

use Core\Container;

/**
 * Dispatcher d'événements synchrone.
 *
 * Responsabilités :
 *   - Enregistrer des listeners (classe ou callable) sur un type d'événement
 *   - Enregistrer des subscribers (regroupements de listeners)
 *   - Dispatcher un événement vers tous ses listeners dans l'ordre d'enregistrement
 *
 * Modes de listener supportés :
 *   1. Class-string : la classe est résolue via le Container au moment du dispatch.
 *      La classe doit implémenter ListenerInterface.
 *   2. Callable     : invoqué directement avec l'événement en argument.
 *
 * Usage :
 *   // Enregistrement d'un listener (classe)
 *   $dispatcher->listen(UserRegistered::class, SendWelcomeEmail::class);
 *
 *   // Enregistrement d'un listener (callable)
 *   $dispatcher->listen(UserRegistered::class, fn(UserRegistered $e) => log($e->user->email));
 *
 *   // Enregistrement via subscriber
 *   $dispatcher->subscribe(AuthSubscriber::class);
 *
 *   // Dispatch
 *   $dispatcher->dispatch(new UserRegistered($user));
 */
final class EventDispatcher
{
    /**
     * Listeners indexés par FQCN de l'événement.
     *
     * @var array<class-string<EventInterface>, list<class-string<ListenerInterface>|callable(EventInterface): void>>
     */
    private array $listeners = [];

    public function __construct(private Container $container) {}

    // -------------------------------------------------------------------------
    // Enregistrement
    // -------------------------------------------------------------------------

    /**
     * Attache un listener à un type d'événement.
     *
     * @param class-string<EventInterface>                                  $eventClass
     * @param class-string<ListenerInterface>|callable(EventInterface): void $listener
     */
    public function listen(string $eventClass, string|callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Enregistre un subscriber : délègue la registration à la méthode subscribe().
     *
     * @param class-string<SubscriberInterface> $subscriberClass
     */
    public function subscribe(string $subscriberClass): void
    {
        /** @var SubscriberInterface $subscriber */
        $subscriber = $this->container->make($subscriberClass);
        $subscriber->subscribe($this);
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    /**
     * Dispatche un événement vers tous ses listeners dans l'ordre d'enregistrement.
     * Si un listener lève une exception, elle se propage normalement.
     */
    public function dispatch(EventInterface $event): void
    {
        $class = get_class($event);

        foreach ($this->listeners[$class] ?? [] as $listener) {
            if (is_callable($listener)) {
                $listener($event);
            } else {
                /** @var ListenerInterface $instance */
                $instance = $this->container->make($listener);
                $instance->handle($event);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    /**
     * Indique si des listeners sont enregistrés pour un type d'événement donné.
     *
     * @param class-string<EventInterface> $eventClass
     */
    public function hasListeners(string $eventClass): bool
    {
        return !empty($this->listeners[$eventClass]);
    }

    /**
     * Retourne tous les listeners enregistrés pour un type d'événement.
     *
     * @param  class-string<EventInterface> $eventClass
     * @return list<class-string<ListenerInterface>|callable(EventInterface): void>
     */
    public function getListeners(string $eventClass): array
    {
        return $this->listeners[$eventClass] ?? [];
    }

    /**
     * Supprime tous les listeners d'un type d'événement.
     * Utile dans les tests pour isoler les scénarios.
     *
     * @param class-string<EventInterface> $eventClass
     */
    public function forgetListeners(string $eventClass): void
    {
        unset($this->listeners[$eventClass]);
    }
}
