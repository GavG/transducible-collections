<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\Reduced;
use GavG\TransducibleCollections\StepInterface;

class TakeStep implements StepInterface
{
    protected int $taken = 0;

    public function __construct(
        protected StepInterface $next,
        protected int $limit
    ) {
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        if ($this->limit <= 0) {
            return Reduced::wrap($acc);
        }

        $this->taken++;
        $result = $this->next->step($acc, $value, $key);

        if ($this->taken >= $this->limit) {
            return Reduced::isReduced($result) ? $result : Reduced::wrap($result);
        }

        return $result;
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
