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

    /**
     * Whether the custom step callable accepts all three args (acc, value, key).
     */
    private bool $acceptsKey;

    public function __construct(callable $step)
    {
        $this->step = $step;

        try {
            $ref = $step instanceof \Closure
                ? new \ReflectionFunction($step)
                : (is_string($step) && function_exists($step)
                    ? new \ReflectionFunction($step)
                    : new \ReflectionFunction(\Closure::fromCallable($step)));
            $this->acceptsKey = $ref->getNumberOfParameters() >= 3;
        } catch (\ReflectionException) {
            $this->acceptsKey = false;
        }
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        return $this->acceptsKey
            ? ($this->step)($acc, $value, $key)
            : ($this->step)($acc, $value);
    }

    public function complete(mixed $acc): mixed
    {
        return $acc;
    }
}
