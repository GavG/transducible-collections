<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\CallableArity;
use GavG\TransducibleCollections\Reduced;
use GavG\TransducibleCollections\StepInterface;

class MapWithKeysStep implements StepInterface
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
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        $pairs = $this->invoke($this->callback, $value, $key);

        if (is_iterable($pairs)) {
            foreach ($pairs as $newKey => $newValue) {
                $acc = $this->next->step($acc, $newValue, $newKey);
                if (Reduced::isReduced($acc)) {
                    return $acc;
                }
            }
            return $acc;
        }

        return $this->next->step($acc, $pairs, $key);
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
