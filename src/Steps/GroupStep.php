<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections\Steps;

use GavG\TransducibleCollections\StepInterface;
use GavG\TransducibleCollections\TransducerPipeline;
use Illuminate\Support\Collection;

class GroupStep implements StepInterface
{
    protected mixed $currentGroupBy;
    protected array $nextGroups = [];

    /**
     * @param  class-string<Collection>  $collectionClass
     */
    public function __construct(
        mixed $groupBy,
        protected bool $preserveKeys = false,
        protected string $collectionClass = Collection::class
    ) {
        if (! is_callable($groupBy) && is_array($groupBy)) {
            $this->nextGroups = $groupBy;
            $this->currentGroupBy = array_shift($this->nextGroups);
        } else {
            $this->currentGroupBy = $groupBy;
        }
    }

    public function step(mixed $acc, mixed $value, mixed $key): mixed
    {
        $groupKeys = is_callable($this->currentGroupBy)
            ? (function () use ($value, $key) {
                try {
                    return ($this->currentGroupBy)($value, $key);
                } catch (\ArgumentCountError) {
                    return ($this->currentGroupBy)($value);
                }
            })()
            : data_get($value, $this->currentGroupBy);

        if (! is_array($groupKeys)) {
            $groupKeys = [$groupKeys];
        }

        if (! is_array($acc)) {
            $acc = [];
        }

        foreach ($groupKeys as $groupKey) {
            $groupKey = match (true) {
                is_bool($groupKey) => (int) $groupKey,
                $groupKey instanceof \BackedEnum => $groupKey->value,
                $groupKey instanceof \UnitEnum => $groupKey->name,
                $groupKey instanceof \Stringable, is_null($groupKey) => (string) $groupKey,
                default => $groupKey,
            };

            if (! isset($acc[$groupKey])) {
                $acc[$groupKey] = [];
            }

            if ($this->preserveKeys) {
                $acc[$groupKey][$key] = $value;
            } else {
                $acc[$groupKey][] = $value;
            }
        }

        return $acc;
    }

    public function complete(mixed $acc): mixed
    {
        $class = $this->collectionClass;

        if (! is_array($acc)) {
            return new $class();
        }

        $results = [];
        foreach ($acc as $groupKey => $items) {
            $groupCollection = new $class($items);
            if (! empty($this->nextGroups)) {
                $groupCollection = (new TransducerPipeline($groupCollection))
                    ->groupBy($this->nextGroups, $this->preserveKeys)
                    ->transduce();
            }
            $results[$groupKey] = $groupCollection;
        }

        return new $class($results);
    }
}
