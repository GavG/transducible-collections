<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\StepInterface;

class KeyByStep implements StepInterface
{
    public function __construct(
        protected StepInterface $next,
        protected mixed $keyBy
    ) {
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        $newKey = is_callable($this->keyBy)
            ? (function () use ($value, $key) {
                try {
                    return ($this->keyBy)($value, $key);
                } catch (\ArgumentCountError) {
                    return ($this->keyBy)($value);
                }
            })()
            : data_get($value, $this->keyBy);

        return $this->next->step($acc, $value, $newKey);
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
