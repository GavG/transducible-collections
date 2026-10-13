<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\CallableArity;
use GavG\TransducibleCollections\StepInterface;

class RejectStep implements StepInterface
{
    use CallableArity;

    /**
     * @var callable
     */
    protected $predicate;

    public function __construct(
        protected StepInterface $next,
        ?callable $predicate = null
    ) {
        $this->predicate = $predicate ?? fn ($value) => (bool) $value;
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        $passes = $this->invoke($this->predicate, $value, $key);

        if (! $passes) {
            return $this->next->step($acc, $value, $key);
        }

        return $acc;
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
