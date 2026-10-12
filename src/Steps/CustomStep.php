<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\StepInterface;

class CustomStep implements StepInterface
{
    /**
     * @var callable
     */
    protected $step;

    public function __construct(callable $step)
    {
        $this->step = $step;
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        try {
            return ($this->step)($acc, $value, $key);
        } catch (\ArgumentCountError) {
            return ($this->step)($acc, $value);
        }
    }

    public function complete(mixed $acc): mixed
    {
        return $acc;
    }
}
