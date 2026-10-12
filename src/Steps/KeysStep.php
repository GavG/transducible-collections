<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\StepInterface;

class KeysStep implements StepInterface
{
    public function __construct(protected StepInterface $next)
    {
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        return $this->next->step($acc, $key, null);
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
