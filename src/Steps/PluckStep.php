<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\StepInterface;

class PluckStep implements StepInterface
{
    /**
     * @param string|array<mixed> $value
     */
    public function __construct(
        protected StepInterface $next,
        protected string|array $value,
        protected ?string $key = null
    ) {
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        $extractedValue = data_get($value, $this->value);
        $extractedKey = $this->key !== null ? data_get($value, $this->key) : null;

        return $this->next->step($acc, $extractedValue, $extractedKey);
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
