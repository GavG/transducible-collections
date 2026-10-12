<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\StepInterface;

class UniqueStep implements StepInterface
{
    protected array $seen = [];

    public function __construct(
        protected StepInterface $next,
        protected mixed $key = null
    ) {
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        $check = null;
        if ($this->key === null) {
            $check = $value;
        } elseif (is_callable($this->key)) {
            try {
                $check = ($this->key)($value, $key);
            } catch (\ArgumentCountError) {
                $check = ($this->key)($value);
            }
        } else {
            $check = data_get($value, $this->key);
        }

        $serialized = is_object($check) ? spl_object_hash($check) : serialize($check);

        if (isset($this->seen[$serialized])) {
            return $acc;
        }

        $this->seen[$serialized] = true;

        return $this->next->step($acc, $value, $key);
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
