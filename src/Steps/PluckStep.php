<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\StepInterface;

class PluckStep implements StepInterface
{
    public function __construct(
        protected StepInterface $next,
        protected string|array $value,
        protected ?string $key = null
    ) {
    }

    public function step(mixed $acc, mixed $item, mixed $currentKey): mixed
    {
        $extractedValue = data_get($item, $this->value);
        $extractedKey = $this->key !== null ? data_get($item, $this->key) : null;

        return $this->next->step($acc, $extractedValue, $extractedKey);
    }

    public function complete(mixed $acc): mixed
    {
        return $this->next->complete($acc);
    }
}
