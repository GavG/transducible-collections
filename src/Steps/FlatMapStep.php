<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\CallableArity;
use GavG\TransducibleCollections\Reduced;
use GavG\TransducibleCollections\StepInterface;

class FlatMapStep implements StepInterface
{
    use CallableArity;

    /**
     * @var callable
     */
    protected $callback;

    public function __construct(
        protected StepInterface $next,
        callable $callback
    ) {
        $this->callback = $callback;
        $this->resolveArity($callback);
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        $items = $this->invoke($this->callback, $value, $key);

        if (! is_iterable($items)) {
            $items = [$items];
        }

        foreach ($items as $subKey => $subValue) {
            $keyToPass = is_int($subKey) ? null : $subKey;
            $acc = $this->next->step($acc, $subValue, $keyToPass);
            if (Reduced::isReduced($acc)) {
                return $acc;
            }
        }

        return $acc;
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
