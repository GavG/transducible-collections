<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\Reduced;
use GavG\TransducibleCollections\StepInterface;

class EachStep implements StepInterface
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
            $result = ($this->callback)($value, $key);
        } catch (\ArgumentCountError) {
            $result = ($this->callback)($value);
        }

        if ($result === false) {
            return Reduced::wrap($acc);
        }

        return $this->next->step($acc, $value, $key);
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
