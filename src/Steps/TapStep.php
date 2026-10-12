<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\StepInterface;

class TapStep implements StepInterface
{
    /**
     * @var callable
     */
    protected $callback;

    public function __construct(
        protected StepInterface $next,
        callable $callback
    ) {
        $this->callback = $callback;
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        try {
            ($this->callback)($value, $key);
        } catch (\ArgumentCountError) {
            ($this->callback)($value);
        }

        return $this->next->step($acc, $value, $key);
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
