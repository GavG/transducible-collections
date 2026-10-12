<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\StepInterface;

class SkipWhileStep implements StepInterface
{
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
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        if ($this->skipping) {
            try {
                $passes = ($this->predicate)($value, $key);
            } catch (\ArgumentCountError) {
                $passes = ($this->predicate)($value);
            }

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
