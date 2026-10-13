<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections;

use Closure;
use ReflectionFunction;

/**
 * Determines at construction time whether a callable accepts a second argument,
 * avoiding the need for a try/catch on every invocation.
 *
 * PHP built-in functions (e.g. 'strtoupper', 'strlen') throw ArgumentCountError
 * when called with more arguments than they declare. Closures and user-defined
 * functions silently ignore extra arguments. By inspecting arity once via
 * reflection we can pick the right call signature up-front and pay zero
 * exception overhead per element.
 */
trait CallableArity
{
    /**
     * Whether the callable accepts two or more parameters (value + key).
     */
    private bool $callableAcceptsKey;

    /**
     * Resolve and cache whether $callable accepts a second (key) argument.
     */
    private function resolveArity(callable $callable): void
    {
        $this->callableAcceptsKey = new ReflectionFunction(
            Closure::fromCallable($callable)
        )->getNumberOfParameters() >= 2;
    }

    /**
     * Invoke $callable with ($value, $key) or just ($value) based on cached arity.
     */
    private function invoke(callable $callable, mixed $value, mixed $key): mixed
    {
        return $this->callableAcceptsKey
            ? $callable($value, $key)
            : $callable($value);
    }
}
