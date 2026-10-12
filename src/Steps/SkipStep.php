<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\StepInterface;

class SkipStep implements StepInterface
{
    protected int $skipped = 0;

    public function __construct(
        protected StepInterface $next,
        protected int $count
    ) {
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        if ($this->skipped < $this->count) {
            $this->skipped++;
            return $acc;
        }

        return $this->next->step($acc, $value, $key);
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
