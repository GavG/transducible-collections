<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections;

use GavG\TransducibleCollections\Steps\ChunkStep;
use GavG\TransducibleCollections\Steps\CollectStep;
use GavG\TransducibleCollections\Steps\CustomStep;
use GavG\TransducibleCollections\Steps\EachStep;
use GavG\TransducibleCollections\Steps\FilterStep;
use GavG\TransducibleCollections\Steps\FlatMapStep;
use GavG\TransducibleCollections\Steps\FlattenStep;
use GavG\TransducibleCollections\Steps\GroupStep;
use GavG\TransducibleCollections\Steps\KeyByStep;
use GavG\TransducibleCollections\Steps\KeysStep;
use GavG\TransducibleCollections\Steps\MapStep;
use GavG\TransducibleCollections\Steps\MapWithKeysStep;
use GavG\TransducibleCollections\Steps\PluckStep;
use GavG\TransducibleCollections\Steps\RejectStep;
use GavG\TransducibleCollections\Steps\SkipStep;
use GavG\TransducibleCollections\Steps\SkipUntilStep;
use GavG\TransducibleCollections\Steps\SkipWhileStep;
use GavG\TransducibleCollections\Steps\TakeStep;
use GavG\TransducibleCollections\Steps\TakeUntilStep;
use GavG\TransducibleCollections\Steps\TakeWhileStep;
use GavG\TransducibleCollections\Steps\TapStep;
use GavG\TransducibleCollections\Steps\UniqueStep;
use GavG\TransducibleCollections\Steps\ValuesStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

class TransducerPipeline
{
    /**
     * The methods that can be proxied.
     *
     * @var array<int, string>
     */
    protected static array $proxies = [
        'each',
        'filter',
        'flatMap',
        'groupBy',
        'keyBy',
        'map',
        'reject',
        'skipUntil',
        'skipWhile',
        'takeUntil',
        'takeWhile',
        'unique',
    ];

    /**
     * The stages of transducers, separated by grouping steps.
     *
     * @var array<int, array{transducers: array<int, callable>, groupBy: mixed, preserveKeys: bool}>
     */
    protected array $stages = [];

    /**
     * The current stage's transducers.
     *
     * @var array<int, callable>
     */
    protected array $transducers = [];

    /**
     * The target collection class to collect into, if specified.
     *
     * @var class-string<Collection<int|string, mixed>>|null
     */
    protected ?string $targetCollectionClass = null;

    /**
     * Create a new transducer pipeline instance.
     */
    public function __construct(protected mixed $collection)
    {
    }

    /**
     * Get the underlying collection or iterable.
     */
    public function getCollection(): mixed
    {
        return $this->collection;
    }

    /**
     * Set the collection class that the pipeline should collect into.
     *
     * @param  class-string<Collection<int|string, mixed>>  $collectionClass
     */
    public function into(string $collectionClass): static
    {
        $this->targetCollectionClass = $collectionClass;

        return $this;
    }

    /**
     * Get the collection class to instantiate when creating new collections.
     *
     * @return class-string<Collection<int|string, mixed>>
     */
    public function getCollectionClass(): string
    {
        if ($this->targetCollectionClass !== null) {
            return $this->targetCollectionClass;
        }

        if ($this->collection instanceof Collection) {
            return $this->collection::class;
        }

        return Collection::class;
    }

    /**
     * Add a custom transducer to the pipeline.
     */
    public function addTransducer(callable $transducer): static
    {
        $this->transducers[] = $transducer;

        return $this;
    }

    /**
     * Run a map over each of the items.
     */
    public function map(callable $callback): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new MapStep($next, $callback));
    }

    /**
     * Run an associative map over each of the items.
     */
    public function mapWithKeys(callable $callback): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new MapWithKeysStep($next, $callback));
    }

    /**
     * Run a filter over each of the items.
     */
    public function filter(?callable $callback = null): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new FilterStep($next, $callback));
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     */
    public function reject(?callable $callback = null): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new RejectStep($next, $callback));
    }

    /**
     * Execute a callback over each item.
     */
    public function each(callable $callback): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new EachStep($next, $callback));
    }

    /**
     * Pass the collection items to the given callback and return the items.
     */
    public function tap(callable $callback): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new TapStep($next, $callback));
    }

    /**
     * Take the first N items.
     */
    public function take(int $limit): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new TakeStep($next, $limit));
    }

    /**
     * Take items until the given callback returns true.
     */
    public function takeUntil(callable $callback): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new TakeUntilStep($next, $callback));
    }

    /**
     * Take items while the given callback returns true.
     */
    public function takeWhile(callable $callback): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new TakeWhileStep($next, $callback));
    }

    /**
     * Skip the first N items.
     */
    public function skip(int $count): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new SkipStep($next, $count));
    }

    /**
     * Alias for skip.
     */
    public function drop(int $count): static
    {
        return $this->skip($count);
    }

    /**
     * Skip items until the given callback returns true.
     */
    public function skipUntil(callable $callback): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new SkipUntilStep($next, $callback));
    }

    /**
     * Skip items while the given callback returns true.
     */
    public function skipWhile(callable $callback): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new SkipWhileStep($next, $callback));
    }

    /**
     * Map a collection and flatten the result by a single level.
     */
    public function flatMap(callable $callback): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new FlatMapStep($next, $callback));
    }

    /**
     * Flatten a multi-dimensional collection by the given depth.
     */
    public function flatten(int|float $depth = INF): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new FlattenStep($next, $depth));
    }

    /**
     * Reset the keys on the collection.
     */
    public function values(): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new ValuesStep($next));
    }

    /**
     * Get the keys of the collection items.
     */
    public function keys(): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new KeysStep($next));
    }

    /**
     * Key an associative collection by a field or using a callback.
     */
    public function keyBy(callable|string $keyBy): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new KeyByStep($next, $keyBy));
    }

    /**
     * Return only unique items from the collection.
     */
    public function unique(callable|string|null $key = null): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new UniqueStep($next, $key));
    }

    /**
     * Chunk the collection into chunks of the given size.
     */
    public function chunk(int $size): static
    {
        $collectionClass = $this->getCollectionClass();

        return $this->addTransducer(fn (StepInterface $next) => new ChunkStep($next, $size, $collectionClass));
    }

    /**
     * Get the values of a given key.
     */
    /** @param string|array<mixed> $value */
    public function pluck(string|array $value, ?string $key = null): static
    {
        return $this->addTransducer(fn (StepInterface $next) => new PluckStep($next, $value, $key));
    }

    /**
     * Filter items by the given key value pair.
     */
    public function where(string $key, mixed $operator = null, mixed $value = null): static
    {
        $args = func_num_args();

        if ($args === 1) {
            $value = true;
            $operator = '=';
        } elseif ($args === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->filter(function ($item) use ($key, $operator, $value) {
            $retrieved = data_get($item, $key);

            return match ($operator) {
                '=', '==' => $retrieved == $value,
                '===' => $retrieved === $value,
                '!=', '<>' => $retrieved != $value,
                '!==' => $retrieved !== $value,
                '<' => $retrieved < $value,
                '>' => $retrieved > $value,
                '<=' => $retrieved <= $value,
                '>=' => $retrieved >= $value,
                default => $retrieved == $value,
            };
        });
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     */
    public function whereStrict(string $key, mixed $value): static
    {
        return $this->where($key, '===', $value);
    }

    /**
     * Filter items such that the value of the given key is in the given array.
     */
    public function whereIn(string $key, mixed $values, bool $strict = false): static
    {
        $values = $values instanceof Enumerable ? $values->all() : (array) $values;

        return $this->filter(fn ($item) => in_array(data_get($item, $key), $values, $strict));
    }

    /**
     * Filter items such that the value of the given key is not in the given array.
     */
    public function whereNotIn(string $key, mixed $values, bool $strict = false): static
    {
        $values = $values instanceof Enumerable ? $values->all() : (array) $values;

        return $this->reject(fn ($item) => in_array(data_get($item, $key), $values, $strict));
    }

    /**
     * Filter items where the given key is null.
     */
    public function whereNull(?string $key = null): static
    {
        return $this->filter(fn ($item) => is_null(data_get($item, $key)));
    }

    /**
     * Filter items where the given key is not null.
     */
    public function whereNotNull(?string $key = null): static
    {
        return $this->filter(fn ($item) => ! is_null(data_get($item, $key)));
    }

    /**
     * Slice the underlying collection.
     */
    public function slice(int $offset, ?int $length = null): static
    {
        if ($offset > 0) {
            $this->skip($offset);
        }

        if ($length !== null) {
            $this->take($length);
        }

        return $this;
    }

    /**
     * Group items by the given key or callback.
     */
    public function groupBy(mixed $groupBy, bool $preserveKeys = false): static
    {
        $this->stages[] = [
            'transducers' => $this->transducers,
            'groupBy' => $groupBy,
            'preserveKeys' => $preserveKeys,
        ];

        $this->transducers = [];

        return $this;
    }

    /**
     * Apply the callback if the given "value" is truthy.
     */
    public function when(mixed $value = null, ?callable $callback = null, ?callable $default = null): static
    {
        $value = value($value, $this);

        if ($value) {
            return $callback ? ($callback($this, $value) ?? $this) : $this;
        }

        if ($default) {
            return $default($this, $value) ?? $this;
        }

        return $this;
    }

    /**
     * Apply the callback unless the given "value" is truthy.
     */
    public function unless(mixed $value = null, ?callable $callback = null, ?callable $default = null): static
    {
        $value = value($value, $this);

        if (! $value) {
            return $callback ? ($callback($this, $value) ?? $this) : $this;
        }

        if ($default) {
            return $default($this, $value) ?? $this;
        }

        return $this;
    }

    /**
     * Pass the collection to the given callback and return the result.
     */
    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * Transduce the pipeline across the collection.
     */
    public function transduce(?callable $step = null, mixed $initial = null): mixed
    {
        $currentSource = $this->collection;
        $collectionClass = $this->getCollectionClass();

        foreach ($this->stages as $stage) {
            $stageTransducers = $stage['transducers'];
            $groupStep = new GroupStep($stage['groupBy'], $stage['preserveKeys'], $collectionClass);

            $pipeline = $groupStep;
            for ($i = count($stageTransducers) - 1; $i >= 0; $i--) {
                $pipeline = ($stageTransducers[$i])($pipeline);
            }

            $acc = [];
            foreach ($currentSource as $key => $value) {
                $acc = $pipeline->step($acc, $value, $key);
                if (Reduced::isReduced($acc)) {
                    $acc = Reduced::deref($acc);
                    break;
                }
            }

            $currentSource = $pipeline->complete($acc);
        }

        if (! empty($this->stages) && empty($this->transducers) && $step === null) {
            return $currentSource instanceof Collection ? $currentSource : new $collectionClass($currentSource);
        }

        $finalStep = $step !== null
            ? new CustomStep($step)
            : new CollectStep($collectionClass);

        $pipeline = $finalStep;
        for ($i = count($this->transducers) - 1; $i >= 0; $i--) {
            $pipeline = ($this->transducers[$i])($pipeline);
        }

        // For the CollectStep path, use the step itself as the initial accumulator —
        // step() returns $this (the same object handle) on every call, so PHP's
        // copy-on-write array duplication is never triggered.
        // For custom step/initial paths, behaviour is unchanged.
        $acc = $initial ?? ($step !== null ? null : $finalStep);
        foreach ($currentSource as $key => $value) {
            $acc = $pipeline->step($acc, $value, $key);
            if (Reduced::isReduced($acc)) {
                $acc = Reduced::deref($acc);
                break;
            }
        }

        return $pipeline->complete($acc);
    }

    /**
     * Alias for transduce.
     */
    public function evaluate(?callable $step = null, mixed $initial = null): mixed
    {
        return $this->transduce($step, $initial);
    }

    /**
     * Reduce the collection to a single value using the transducer pipeline.
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return $this->transduce($callback, $initial);
    }

    /**
     * Get the first item from the transduced collection passing the given truth test.
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        $pipeline = clone $this;
        if ($callback !== null) {
            $pipeline->filter($callback);
        }

        $result = $pipeline->take(1)->transduce();

        return $result instanceof Collection ? $result->first(null, $default) : $default;
    }

    /**
     * Count the number of items in the transduced collection.
     */
    public function count(): int
    {
        return (int) $this->transduce(fn ($acc) => $acc + 1, 0);
    }

    /**
     * Get all of the items in the collection.
     */
    /** @return array<mixed> */
    public function all(): array
    {
        $result = $this->transduce();

        return $result instanceof Collection ? $result->all() : (array) $result;
    }

    /**
     * Get the collection of items as a plain array.
     */
    /** @return array<mixed> */
    public function toArray(): array
    {
        $result = $this->transduce();

        return $result instanceof Collection ? $result->toArray() : (array) $result;
    }

    /**
     * Evaluate and return as a Collection instance matching the target collection type.
     *
     * @param  class-string<Collection>|null  $collectionClass
     */
    /**
     * @param  class-string<Collection<int|string, mixed>>|null  $collectionClass
     * @return Collection<int|string, mixed>
     */
    public function collect(?string $collectionClass = null): Collection
    {
        if ($collectionClass !== null) {
            $this->into($collectionClass);
        }

        $result = $this->transduce();
        $class = $this->getCollectionClass();

        return $result instanceof Collection ? $result : new $class($result);
    }

    /**
     * Dynamically access collection proxies.
     *
     * @throws \Exception
     */
    public function __get(string $key): HigherOrderTransducerProxy
    {
        if (! in_array($key, static::$proxies, true)) {
            throw new \Exception("Property [{$key}] does not exist on this pipeline instance.");
        }

        return new HigherOrderTransducerProxy($this, $key);
    }
}
