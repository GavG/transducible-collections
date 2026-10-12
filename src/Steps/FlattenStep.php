<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\Reduced;
use GavG\TransducibleCollections\StepInterface;

class FlattenStep implements StepInterface
{
    public function __construct(
        protected StepInterface $next,
        protected int|float $depth = INF
    ) {
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        return $this->flatten($acc, $value, 0);
    }

    protected function flatten(mixed $acc, mixed $value, int|float $currentDepth): mixed
    {
        if (is_iterable($value) && $currentDepth < $this->depth) {
            foreach ($value as $subValue) {
                $acc = $this->flatten($acc, $subValue, $currentDepth + 1);
                if (Reduced::isReduced($acc)) {
                    return $acc;
                }
            }

            return $acc;
        }

        return $this->next->step($acc, $value, null);
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
