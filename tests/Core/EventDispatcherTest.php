<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Container;
use Core\Events\EventDispatcher;
use Core\Events\EventInterface;
use Core\Events\ListenerInterface;
use Core\Events\SubscriberInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du EventDispatcher.
 * Couvre : listen (callable + classe), dispatch, hasListeners,
 * getListeners, forgetListeners, subscribe, ordre d'exécution,
 * propagation d'exception, événement passé correctement.
 */
final class EventDispatcherTest extends TestCase
{
    private Container      $container;
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->container  = new Container();
        $this->dispatcher = new EventDispatcher($this->container);
    }

    // -------------------------------------------------------------------------
    // listen + dispatch via callable
    // -------------------------------------------------------------------------

    public function testDispatchCallsCallableListener(): void
    {
        $called = false;

        $this->dispatcher->listen(FakeEvent::class, function () use (&$called) {
            $called = true;
        });

        $this->dispatcher->dispatch(new FakeEvent());

        $this->assertTrue($called);
    }

    public function testCallableListenerReceivesEvent(): void
    {
        $received = null;

        $this->dispatcher->listen(FakeEvent::class, function (EventInterface $e) use (&$received) {
            $received = $e;
        });

        $event = new FakeEvent();
        $this->dispatcher->dispatch($event);

        $this->assertSame($event, $received);
    }

    // -------------------------------------------------------------------------
    // listen + dispatch via class-string
    // -------------------------------------------------------------------------

    public function testDispatchCallsClassListener(): void
    {
        RecordingListener::$calls = [];

        $this->container->bind(RecordingListener::class, fn() => new RecordingListener());
        $this->dispatcher->listen(FakeEvent::class, RecordingListener::class);

        $this->dispatcher->dispatch(new FakeEvent());

        $this->assertCount(1, RecordingListener::$calls);
    }

    public function testClassListenerReceivesEvent(): void
    {
        RecordingListener::$calls = [];

        $this->container->bind(RecordingListener::class, fn() => new RecordingListener());
        $this->dispatcher->listen(FakeEvent::class, RecordingListener::class);

        $event = new FakeEvent('payload');
        $this->dispatcher->dispatch($event);

        $this->assertSame($event, RecordingListener::$calls[0]);
    }

    // -------------------------------------------------------------------------
    // Événement sans listener
    // -------------------------------------------------------------------------

    public function testDispatchWithNoListenersSilently(): void
    {
        $this->dispatcher->dispatch(new FakeEvent());
        $this->addToAssertionCount(1); // Aucune exception attendue
    }

    // -------------------------------------------------------------------------
    // Plusieurs listeners sur le même événement
    // -------------------------------------------------------------------------

    public function testMultipleListenersAllCalled(): void
    {
        $log = [];

        $this->dispatcher->listen(FakeEvent::class, function () use (&$log) { $log[] = 'A'; });
        $this->dispatcher->listen(FakeEvent::class, function () use (&$log) { $log[] = 'B'; });
        $this->dispatcher->listen(FakeEvent::class, function () use (&$log) { $log[] = 'C'; });

        $this->dispatcher->dispatch(new FakeEvent());

        $this->assertSame(['A', 'B', 'C'], $log);
    }

    public function testListenersCalledInRegistrationOrder(): void
    {
        $order = [];

        $this->dispatcher->listen(FakeEvent::class, function () use (&$order) { $order[] = 1; });
        $this->dispatcher->listen(FakeEvent::class, function () use (&$order) { $order[] = 2; });
        $this->dispatcher->listen(FakeEvent::class, function () use (&$order) { $order[] = 3; });

        $this->dispatcher->dispatch(new FakeEvent());

        $this->assertSame([1, 2, 3], $order);
    }

    // -------------------------------------------------------------------------
    // Listeners isolés par type d'événement
    // -------------------------------------------------------------------------

    public function testListenerNotCalledForOtherEvents(): void
    {
        $called = false;

        $this->dispatcher->listen(FakeEvent::class, function () use (&$called) {
            $called = true;
        });

        $this->dispatcher->dispatch(new AnotherFakeEvent());

        $this->assertFalse($called);
    }

    public function testTwoEventTypesGetSeparateListeners(): void
    {
        $fakeLog    = [];
        $anotherLog = [];

        $this->dispatcher->listen(FakeEvent::class,        function () use (&$fakeLog)    { $fakeLog[]    = 'fake'; });
        $this->dispatcher->listen(AnotherFakeEvent::class, function () use (&$anotherLog) { $anotherLog[] = 'another'; });

        $this->dispatcher->dispatch(new FakeEvent());
        $this->dispatcher->dispatch(new AnotherFakeEvent());

        $this->assertSame(['fake'], $fakeLog);
        $this->assertSame(['another'], $anotherLog);
    }

    // -------------------------------------------------------------------------
    // hasListeners
    // -------------------------------------------------------------------------

    public function testHasListenersReturnsTrueAfterListen(): void
    {
        $this->dispatcher->listen(FakeEvent::class, fn() => null);
        $this->assertTrue($this->dispatcher->hasListeners(FakeEvent::class));
    }

    public function testHasListenersReturnsFalseWhenNoListener(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners(FakeEvent::class));
    }

    // -------------------------------------------------------------------------
    // getListeners
    // -------------------------------------------------------------------------

    public function testGetListenersReturnsRegisteredCallable(): void
    {
        $fn = fn() => null;
        $this->dispatcher->listen(FakeEvent::class, $fn);

        $listeners = $this->dispatcher->getListeners(FakeEvent::class);

        $this->assertCount(1, $listeners);
        $this->assertSame($fn, $listeners[0]);
    }

    public function testGetListenersReturnsEmptyArrayForUnknownEvent(): void
    {
        $this->assertSame([], $this->dispatcher->getListeners(FakeEvent::class));
    }

    // -------------------------------------------------------------------------
    // forgetListeners
    // -------------------------------------------------------------------------

    public function testForgetListenersRemovesAllForEvent(): void
    {
        $this->dispatcher->listen(FakeEvent::class, fn() => null);
        $this->dispatcher->listen(FakeEvent::class, fn() => null);

        $this->dispatcher->forgetListeners(FakeEvent::class);

        $this->assertFalse($this->dispatcher->hasListeners(FakeEvent::class));
    }

    public function testForgetListenersDoesNotAffectOtherEvents(): void
    {
        $this->dispatcher->listen(FakeEvent::class,        fn() => null);
        $this->dispatcher->listen(AnotherFakeEvent::class, fn() => null);

        $this->dispatcher->forgetListeners(FakeEvent::class);

        $this->assertTrue($this->dispatcher->hasListeners(AnotherFakeEvent::class));
    }

    public function testForgetListenersOnUnknownEventDoesNotThrow(): void
    {
        $this->dispatcher->forgetListeners(FakeEvent::class);
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // subscribe
    // -------------------------------------------------------------------------

    public function testSubscribeRegistersListenersDeclaredBySubscriber(): void
    {
        // Utilise un objet pour éviter les problèmes de capture par valeur
        // des arrow functions avec des références scalaires.
        $record = new \stdClass();
        $record->called = false;

        $this->container->bind(
            FakeSubscriber::class,
            fn() => new FakeSubscriber(function () use ($record) { $record->called = true; }),
        );

        $this->dispatcher->subscribe(FakeSubscriber::class);
        $this->dispatcher->dispatch(new FakeEvent());

        $this->assertTrue($record->called);
    }

    // -------------------------------------------------------------------------
    // Propagation d'exception depuis un listener
    // -------------------------------------------------------------------------

    public function testExceptionInListenerPropagates(): void
    {
        $this->dispatcher->listen(FakeEvent::class, function () {
            throw new \RuntimeException('listener failed');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('listener failed');

        $this->dispatcher->dispatch(new FakeEvent());
    }

    public function testExceptionStopsSubsequentListeners(): void
    {
        $reached = false;

        $this->dispatcher->listen(FakeEvent::class, function () {
            throw new \RuntimeException('stop');
        });
        $this->dispatcher->listen(FakeEvent::class, function () use (&$reached) {
            $reached = true;
        });

        try {
            $this->dispatcher->dispatch(new FakeEvent());
        } catch (\RuntimeException) {}

        $this->assertFalse($reached);
    }

    // -------------------------------------------------------------------------
    // Même listener sur plusieurs événements
    // -------------------------------------------------------------------------

    public function testSameListenerCanHandleMultipleEventTypes(): void
    {
        $log = [];

        $multiListener = function (EventInterface $e) use (&$log) {
            $log[] = get_class($e);
        };

        $this->dispatcher->listen(FakeEvent::class,        $multiListener);
        $this->dispatcher->listen(AnotherFakeEvent::class, $multiListener);

        $this->dispatcher->dispatch(new FakeEvent());
        $this->dispatcher->dispatch(new AnotherFakeEvent());

        $this->assertContains(FakeEvent::class, $log);
        $this->assertContains(AnotherFakeEvent::class, $log);
    }
}

// ---------------------------------------------------------------------------
// Classes de support (locales au fichier de test)
// ---------------------------------------------------------------------------

final class FakeEvent implements EventInterface
{
    public function __construct(public string $data = '') {}
}

final class AnotherFakeEvent implements EventInterface {}

final class RecordingListener implements ListenerInterface
{
    /** @var list<EventInterface> */
    public static array $calls = [];

    public function handle(EventInterface $event): void
    {
        self::$calls[] = $event;
    }
}

final class FakeSubscriber implements SubscriberInterface
{
    public function __construct(private \Closure $callback) {}

    public function subscribe(EventDispatcher $dispatcher): void
    {
        $dispatcher->listen(FakeEvent::class, $this->callback);
    }
}
