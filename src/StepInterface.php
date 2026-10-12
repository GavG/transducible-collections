<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections;

interface StepInterface
{
    /**
     * Process an item in the reducing step.
     */
    public function step(mixed $acc, mixed $value, mixed $key): mixed;

    /**
     * Complete and flush any buffered state in the reducing step.
     */
    public function complete(mixed $acc): mixed;
}
