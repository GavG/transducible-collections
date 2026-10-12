<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\StepInterface;
use Illuminate\Support\Collection;

class CollectStep implements StepInterface
{
    /**
     * @param  class-string<Collection>  $collectionClass
     */
    public function __construct(
        protected string $collectionClass = Collection::class
    ) {
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        if (! is_array($acc)) {
            $acc = [];
        }

        if ($key === null) {
            $acc[] = $value;
        } else {
            $acc[$key] = $value;
        }

        return $acc;
    }

    public function complete(mixed $acc): mixed
    {
        $class = $this->collectionClass;

        return new $class(is_array($acc) ? $acc : []);
    }
}
