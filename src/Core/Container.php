<?php

declare(strict_types=1);

namespace Core;

use Closure;
use RuntimeException;

/**
 * Conteneur d'injection de dépendances minimaliste.
 *
 * Supporte :
 * - les bindings via factories (Closure)
 * - les singletons (instanciés une seule fois)
 * - la résolution automatique par réflexion (autowiring)
 */
final class Container
{
    /** @var array<string, Closure> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    // -------------------------------------------------------------------------
    // Enregistrement
    // -------------------------------------------------------------------------

    /**
     * Enregistre une factory.
     *
     * @param Closure(Container): object $factory
     */
    public function bind(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    /**
     * Enregistre une factory dont le résultat est mis en cache (singleton).
     *
     * @param Closure(Container): object $factory
     */
    public function singleton(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = function (self $c) use ($abstract, $factory): object {
            if (!isset($this->instances[$abstract])) {
                $this->instances[$abstract] = $factory($c);
            }
            return $this->instances[$abstract];
        };
    }

    /**
     * Enregistre une instance déjà créée.
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
        $this->bindings[$abstract]  = fn() => $instance;
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    /**
     * Indique si un type est enregistré dans le conteneur.
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    // -------------------------------------------------------------------------
    // Résolution
    // -------------------------------------------------------------------------

    /**
     * Résout un type (FQCN) depuis le conteneur ou par autowiring.
     *
     * @template T of object
     * @param  class-string<T> $abstract
     * @return T
     */
    public function make(string $abstract): object
    {
        if (isset($this->bindings[$abstract])) {
            /** @var T */
            return ($this->bindings[$abstract])($this);
        }

        return $this->autowire($abstract);
    }

    // -------------------------------------------------------------------------
    // Autowiring
    // -------------------------------------------------------------------------

    /**
     * @template T of object
     * @param  class-string<T> $class
     * @return T
     */
    private function autowire(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Classe introuvable : {$class}");
        }

        $reflector   = new \ReflectionClass($class);
        $constructor = $reflector->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return new $class();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                /** @var class-string<object> $typeName */
                $typeName = $type->getName();
                $args[]   = $this->make($typeName);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new RuntimeException(
                    "Impossible de résoudre le paramètre \${$param->getName()} de {$class}"
                );
            }
        }

        return $reflector->newInstanceArgs($args);
    }
}
