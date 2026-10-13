<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections;

/**
 * Efficiently resolves whether a callable accepts a (value, key) pair or
 * just a single (value) argument, caching the result to avoid per-element
 * overhead.
 *
 * Strategy — lazy with closure fast-path:
 *  - On the first call to invoke(), the arity is detected and cached.
 *  - Closures are immediately marked as key-accepting at zero cost (they
 *    never throw ArgumentCountError with extra arguments).
 *  - All other callables (PHP built-ins like 'strtoupper', named functions,
 *    array callables, invokable objects) are inspected once via reflection.
 *  - All subsequent calls branch on the cached bool — no reflection, no
 *    exception handling.
 *
 * No explicit setup call is required in step constructors; invoke() handles
 * everything automatically on first use.
 */
trait CallableArity
{
    /**
     * Whether the callable accepts two or more parameters (value + key).
     * Uninitialised until the first invoke() call.
     */
    private bool $callableAcceptsKey;

    /**
     * Invoke $callable with ($value, $key) or just ($value).
     *
     * Detects and caches arity on the first call, then branches cheaply
     * on every subsequent call.
     */
    private function invoke(callable $callable, mixed $value, mixed $key): mixed
    {
        if (! isset($this->callableAcceptsKey)) {
            // Use Closure::fromCallable() to normalise any callable type — strings,
            // arrays, invokable objects, and closures — into a single Closure that
            // ReflectionFunction can always introspect.
            //
            // We cannot skip reflection for closures: Closure::fromCallable('strlen')
            // produces a Closure that *does* enforce strict arity (it inherits the
            // built-in's argument count), so `instanceof \Closure` alone is not a
            // reliable signal that extra arguments are safe to pass.
            try {
                $ref = new \ReflectionFunction(\Closure::fromCallable($callable));
                $this->callableAcceptsKey = $ref->getNumberOfParameters() >= 2;
            } catch (\ReflectionException) {
                $this->callableAcceptsKey = false;
            }
        }

        return $this->callableAcceptsKey
            ? $callable($value, $key)
            : $callable($value);
    }
}
