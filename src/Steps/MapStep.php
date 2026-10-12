<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\CallableArity;
use GavG\TransducibleCollections\StepInterface;

class MapStep implements StepInterface
{
    use CallableArity;

    /**
     * @var callable
     */
    protected $callback;

    public function __construct(
        protected StepInterface $next,
        callable $callback
    ) {
        $this->callback = $callback;
        $this->resolveArity($callback);
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        $mapped = $this->invoke($this->callback, $value, $key);

        return $this->next->step($acc, $mapped, $key);
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
