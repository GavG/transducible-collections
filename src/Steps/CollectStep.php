<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\StepInterface;
use Illuminate\Support\Collection;

class CollectStep implements StepInterface
{
    /**
     * The mutable accumulator array, held as object state so that PHP's
     * copy-on-write mechanism is never triggered during the reduce loop.
     *
     * Because objects are always passed by handle in PHP, returning $this
     * from step() means the caller's $acc variable is reassigned to the
     * same object — no array duplication ever occurs regardless of how
     * many elements are processed.
     */
    /** @var array<mixed> */
    private array $items = [];

    /**
     * @param  class-string<Collection<int|string, mixed>>  $collectionClass
     */
    public function __construct(
        protected string $collectionClass = Collection::class
    ) {
    }

    public function step(mixed $acc, mixed $value, mixed $key): static
    {
        if ($key === null) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }

        return $this;
    }

    /** @return Collection<int|string, mixed> */
    public function complete(mixed $acc): Collection
    {
        $class = $this->collectionClass;

        return new $class($this->items);
    }
}
