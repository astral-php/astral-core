<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests unitaires du conteneur DI.
 * Couvre : bind, singleton, instance, has, make, autowiring.
 */
final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    // -------------------------------------------------------------------------
    // bind
    // -------------------------------------------------------------------------

    public function testBindResolvesEachTimeANewInstance(): void
    {
        $this->container->bind(SimpleService::class, fn() => new SimpleService());

        $a = $this->container->make(SimpleService::class);
        $b = $this->container->make(SimpleService::class);

        $this->assertNotSame($a, $b);
    }

    public function testBindReceivesContainerAsArgument(): void
    {
        $captured = null;

        $this->container->bind(SimpleService::class, function (Container $c) use (&$captured) {
            $captured = $c;
            return new SimpleService();
        });

        $this->container->make(SimpleService::class);

        $this->assertSame($this->container, $captured);
    }

    // -------------------------------------------------------------------------
    // singleton
    // -------------------------------------------------------------------------

    public function testSingletonReturnsSameInstance(): void
    {
        $this->container->singleton(SimpleService::class, fn() => new SimpleService());

        $a = $this->container->make(SimpleService::class);
        $b = $this->container->make(SimpleService::class);

        $this->assertSame($a, $b);
    }

    public function testSingletonFactoryCalledOnlyOnce(): void
    {
        $calls = 0;

        $this->container->singleton(SimpleService::class, function () use (&$calls) {
            $calls++;
            return new SimpleService();
        });

        $this->container->make(SimpleService::class);
        $this->container->make(SimpleService::class);

        $this->assertSame(1, $calls);
    }

    // -------------------------------------------------------------------------
    // instance
    // -------------------------------------------------------------------------

    public function testInstanceAlwaysReturnsSameObject(): void
    {
        $service = new SimpleService();
        $this->container->instance(SimpleService::class, $service);

        $resolved = $this->container->make(SimpleService::class);

        $this->assertSame($service, $resolved);
    }

    // -------------------------------------------------------------------------
    // has
    // -------------------------------------------------------------------------

    public function testHasReturnsTrueAfterBind(): void
    {
        $this->container->bind(SimpleService::class, fn() => new SimpleService());
        $this->assertTrue($this->container->has(SimpleService::class));
    }

    public function testHasReturnsFalseForUnregisteredType(): void
    {
        $this->assertFalse($this->container->has(SimpleService::class));
    }

    public function testHasReturnsTrueAfterInstance(): void
    {
        $this->container->instance(SimpleService::class, new SimpleService());
        $this->assertTrue($this->container->has(SimpleService::class));
    }

    // -------------------------------------------------------------------------
    // autowiring
    // -------------------------------------------------------------------------

    public function testAutowiringResolvesClassWithNoConstructor(): void
    {
        $resolved = $this->container->make(SimpleService::class);
        $this->assertInstanceOf(SimpleService::class, $resolved);
    }

    public function testAutowiringResolvesClassWithTypedDependency(): void
    {
        $resolved = $this->container->make(ServiceWithDependency::class);

        $this->assertInstanceOf(ServiceWithDependency::class, $resolved);
        $this->assertInstanceOf(SimpleService::class, $resolved->dep);
    }

    public function testAutowiringUsesDefaultParameterValue(): void
    {
        $resolved = $this->container->make(ServiceWithDefault::class);
        $this->assertSame('default', $resolved->value);
    }

    public function testAutowiringThrowsForUnresolvableBuiltinType(): void
    {
        $this->expectException(RuntimeException::class);
        $this->container->make(ServiceWithPrimitive::class);
    }

    public function testAutowiringThrowsForUnknownClass(): void
    {
        $this->expectException(RuntimeException::class);
        /** @phpstan-ignore-next-line */
        $this->container->make('NonExistentClass');
    }

    // -------------------------------------------------------------------------
    // make préfère le binding enregistré sur l'autowiring
    // -------------------------------------------------------------------------

    public function testBindOverridesAutowiring(): void
    {
        $custom = new SimpleService();
        $custom->tag = 'from_bind';

        $this->container->bind(SimpleService::class, fn() => $custom);

        $resolved = $this->container->make(SimpleService::class);
        $this->assertSame('from_bind', $resolved->tag);
    }
}

// ---------------------------------------------------------------------------
// Classes de support (locales au fichier de test)
// ---------------------------------------------------------------------------

final class SimpleService
{
    public string $tag = '';
}

final class ServiceWithDependency
{
    public function __construct(public SimpleService $dep) {}
}

final class ServiceWithDefault
{
    public function __construct(public string $value = 'default') {}
}

final class ServiceWithPrimitive
{
    public function __construct(private string $name) {}
}
