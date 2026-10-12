<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\StepInterface;

class RejectStep implements StepInterface
{
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
        try {
            $passes = ($this->predicate)($value, $key);
        } catch (\ArgumentCountError) {
            $passes = ($this->predicate)($value);
        }

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
