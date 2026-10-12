<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\CallableArity;
use GavG\TransducibleCollections\StepInterface;

class SkipWhileStep implements StepInterface
{
    use CallableArity;

    protected bool $skipping = true;

    /**
     * @var callable
     */
    protected $predicate;

    public function __construct(
        protected StepInterface $next,
        callable $predicate
    ) {
        $this->predicate = $predicate;
        $this->resolveArity($predicate);
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        if ($this->skipping) {
            $passes = $this->invoke($this->predicate, $value, $key);

            if ($passes) {
                return $acc;
            }

            $this->skipping = false;
        }

        return $this->next->step($acc, $value, $key);
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
