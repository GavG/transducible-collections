<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\StepInterface;

class MapStep implements StepInterface
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
            $mapped = ($this->callback)($value, $key);
        } catch (\ArgumentCountError) {
            $mapped = ($this->callback)($value);
        }

        return $this->next->step($acc, $mapped, $key);
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
