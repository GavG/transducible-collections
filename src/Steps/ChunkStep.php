<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\Reduced;
use GavG\TransducibleCollections\StepInterface;
use Illuminate\Support\Collection;

class ChunkStep implements StepInterface
{
    /** @var array<mixed> */
    protected array $buffer = [];

    /**
     * @param  class-string<Collection<int|string, mixed>>  $collectionClass
     */
    public function __construct(
        protected StepInterface $next,
        protected int $size,
        protected string $collectionClass = Collection::class
    ) {
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        $this->buffer[] = $value;

        if (count($this->buffer) >= $this->size) {
            $class = $this->collectionClass;
            $chunk = new $class($this->buffer);
            $this->buffer = [];

            return $this->next->step($acc, $chunk, null);
        }

        return $acc;
    }

    public function complete(mixed $acc): mixed
    {
        if (! empty($this->buffer)) {
            $class = $this->collectionClass;
            $chunk = new $class($this->buffer);
            $this->buffer = [];
            $acc = $this->next->step($acc, $chunk, null);
            if (Reduced::isReduced($acc)) {
                $acc = Reduced::deref($acc);
            }
        }

        return $this->next->complete($acc);
    }
}
