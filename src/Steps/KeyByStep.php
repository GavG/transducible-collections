<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\CallableArity;
use GavG\TransducibleCollections\StepInterface;

class KeyByStep implements StepInterface
{
    use CallableArity;

    public function __construct(
        protected StepInterface $next,
        protected mixed $keyBy
    ) {
        if (is_callable($this->keyBy)) {
            $this->resolveArity($this->keyBy);
        } else {
            $this->callableAcceptsKey = false;
        }
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        $newKey = is_callable($this->keyBy)
            ? $this->invoke($this->keyBy, $value, $key)
            : data_get($value, $this->keyBy);

        return $this->next->step($acc, $value, $newKey);
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
