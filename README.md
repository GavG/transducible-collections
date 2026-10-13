# Transducible Collections

A Laravel package that adds a transducer pipeline to Illuminate `Collection` and `LazyCollection`, enabling **single-pass, early-terminating** processing chains that can be dramatically faster than standard chained collection methods — in the right situations.

---

## Requirements

- PHP 8.2+
- Laravel / Illuminate Support 11, 12, or 13

---

## Installation

```bash
composer require gavg/transducible-collections
```

The service provider is auto-discovered via Laravel's package discovery. No manual registration needed.

---

## The core idea

A standard Laravel collection chain allocates an intermediate collection at **every step**:

```php
$collection
    ->filter($predicate)   // allocates a new collection (all N items checked)
    ->map($transform)      // allocates another new collection (all surviving items)
    ->take(10);            // finally discards everything but 10 items
```

Three full passes over the data. Three intermediate arrays. All N items are checked by `filter`, all survivors are transformed by `map`, and only then are 10 items kept.

A transducer pipeline **fuses all steps into a single pass** and supports **early termination** — once `take(10)` has collected its 10 items, iteration stops immediately. Items after that point are never visited at all:

```php
$collection
    ->transducible()
    ->filter($predicate)
    ->map($transform)
    ->take(10)
    ->all();
```

One pass. No intermediate arrays. Stops after 10 items pass the filter.

---

## When to use this package

> **This package is specifically valuable for processing collections of a few thousand items or more when early termination is involved.**

The transducer pipeline has PHP-level method dispatch overhead on every element (vs the C-level loops inside native collection methods). For full-pass transforms where every item must be visited and returned, the standard collection is typically faster.

The wins are significant when the pipeline can **stop early** — using `take`, `takeWhile`, `takeUntil`, or `first`. In these cases, the transducer processes only the minimum number of source records necessary and exits, while the standard chain must complete all intermediate passes in full before truncating.

### Benchmark results (5,000 records, 200 iterations)

| Pipeline | Standard | Transduced | Speedup |
|---|---|---|---|
| `map → filter → take(10)` | 1.05 ms/run | 28 µs/run | **~37x faster** |
| `filter → map → take(25)` | 571 µs/run | 32 µs/run | **~18x faster** |
| `filter → map → skip(200) → take(50)` | 917 µs/run | 163 µs/run | **~6x faster** |
| `map → takeWhile(condition)` | 219 µs/run | 71 µs/run | **~3x faster** |
| `map → map → map → map → map` (full pass) | 1.5 ms/run | 4.3 ms/run | **~3x slower** |

The last row is the important caveat — see [Limitations](#limitations) below.

### Good candidates

- Finding the first N items matching a complex condition
- Paginating through large in-memory collections
- Any pipeline ending in `take`, `takeWhile`, `takeUntil`, or `first`
- Pipelines where a `filter` significantly reduces the working set before a `take`

### Poor candidates

- Full-pass transforms where every item is returned (no early termination)
- Small collections (< ~500 items) where pipeline setup overhead dominates
- Simple single-step operations (`->map()` alone, `->filter()` alone)

---

## Usage

### Basic usage

Call `->transducible()` on any collection to enter the pipeline, chain your operations, then terminate with `->all()`, `->collect()`, `->toArray()`, `->first()`, or `->transduce()`:

```php
use Illuminate\Support\Collection;

$results = collect($largeDataset)
    ->transducible()
    ->filter(fn ($item) => $item['active'])
    ->map(fn ($item) => $item['name'])
    ->take(20)
    ->all();
```

### Available operations

**Transforms**
```php
->map(callable $callback)
->mapWithKeys(callable $callback)
->flatMap(callable $callback)
->flatten(int|float $depth = INF)
->pluck(string|array $value, ?string $key = null)
```

**Filtering**
```php
->filter(?callable $callback = null)
->reject(?callable $callback = null)
->where(string $key, mixed $operator = null, mixed $value = null)
->whereStrict(string $key, mixed $value)
->whereIn(string $key, mixed $values, bool $strict = false)
->whereNotIn(string $key, mixed $values, bool $strict = false)
->whereNull(?string $key = null)
->whereNotNull(?string $key = null)
->unique(callable|string|null $key = null)
```

**Early termination — where the performance wins are**
```php
->take(int $limit)          // stop after N items
->takeWhile(callable $cb)   // stop when predicate returns false
->takeUntil(callable $cb)   // stop when predicate returns true
->first(?callable $cb = null, mixed $default = null)
```

**Skipping**
```php
->skip(int $count)          // alias: ->drop(int $count)
->skipWhile(callable $cb)
->skipUntil(callable $cb)
->slice(int $offset, ?int $length = null)
```

**Key manipulation**
```php
->values()
->keys()
->keyBy(callable|string $keyBy)
```

**Grouping and chunking**
```php
->groupBy(mixed $groupBy, bool $preserveKeys = false)
->chunk(int $size)
```

**Side effects (non-transforming)**
```php
->each(callable $callback)   // return false to stop iteration early
->tap(callable $callback)    // inspect without modifying; never stops iteration
```

**Conditional application**
```php
->when(mixed $value, ?callable $callback, ?callable $default = null)
->unless(mixed $value, ?callable $callback, ?callable $default = null)
->pipe(callable $callback)
```

**Targeting a specific collection class**
```php
->into(string $collectionClass)   // collect into a custom Collection subclass
```

### Terminals — collecting results

```php
->all(): array
->toArray(): array
->collect(?string $collectionClass = null): Collection
->first(?callable $callback = null, mixed $default = null): mixed
->count(): int
->transduce(?callable $step = null, mixed $initial = null): mixed
->evaluate(?callable $step = null, mixed $initial = null): mixed  // alias for transduce
->reduce(callable $callback, mixed $initial = null): mixed
```

### Higher-order proxies

Common operations support higher-order syntax via property and method access on the proxy:

```php
// Equivalent to ->map(fn ($item) => $item['name'])
$pipeline->map->name;

// Equivalent to ->map(fn ($item) => $item->toUpper())
$pipeline->map->toUpper();

// Equivalent to ->filter(fn ($item) => $item->isActive())
$pipeline->filter->isActive();
```

Supported: `each`, `filter`, `flatMap`, `groupBy`, `keyBy`, `map`, `reject`, `skipUntil`, `skipWhile`, `takeUntil`, `takeWhile`, `unique`.

### Custom transducers

You can inject your own transducer factory — a callable that receives the next step and returns a new step:

```php
$pipeline->addTransducer(function (StepInterface $next) {
    return new CustomStep(function (mixed $acc, mixed $value, mixed $key) use ($next) {
        // your logic here
        return $next->step($acc, $value, $key);
    });
});
```

### Custom reduction with `transduce()`

Supply your own step function and initial accumulator for custom aggregation:

```php
// Count items matching a filter — stops iterating using the transducer pipeline
$count = collect($items)
    ->transducible()
    ->filter(fn ($item) => $item['score'] > 50)
    ->transduce(fn ($acc) => $acc + 1, 0);

// Sum with early termination
$sum = collect($items)
    ->transducible()
    ->filter(fn ($item) => $item['active'])
    ->take(100)
    ->transduce(fn ($acc, $item) => $acc + $item['value'], 0);
```

### Works with `LazyCollection`

```php
LazyCollection::make(function () {
    // infinite or very large generator
    yield from someHugeDataSource();
})
    ->transducible()
    ->filter(fn ($item) => $item['relevant'])
    ->map(fn ($item) => $item['value'])
    ->take(50)
    ->all();
```

### Preserving custom collection subclasses

If you call `->transducible()` on a custom `Collection` subclass, the pipeline will collect back into the same subclass automatically. You can also specify the target class explicitly:

```php
$result = MyCustomCollection::make($items)
    ->transducible()
    ->filter($predicate)
    ->collect(); // returns a MyCustomCollection instance

// Or explicitly:
collect($items)
    ->transducible()
    ->filter($predicate)
    ->into(MyCustomCollection::class)
    ->collect();
```

---

## Limitations

### Full-pass transforms are slower than standard collections

When **every item must be visited and returned** (no `take`, `takeWhile`, `takeUntil`, or `first` in the chain), the standard Laravel collection is faster. PHP's native `array_map` and `array_filter` run at C speed; the transducer pipeline runs at PHP method-dispatch speed.

For a 5×map full-pass over 5,000 items, the transducer is approximately **3x slower** than chaining standard collection methods. Do not use this package as a drop-in replacement for all collection operations — use it selectively where early termination applies.

### Setup overhead on small collections

Building the transducer pipeline (registering step factories, composing them) has a fixed cost per `->transducible()` call. For small collections (< ~500 items), this overhead can outweigh the savings even with early termination. Profile your specific case if in doubt.

### `groupBy` breaks the single-pass guarantee

`->groupBy()` is a materialisation point — it must collect all items from the preceding steps into groups before the pipeline can continue. Steps after `->groupBy()` operate on the grouped structure in a new single-pass pipeline, but the `groupBy` itself is always eager and full-pass.

### `each` returning `false` stops iteration

Unlike the standard `Collection::each()` which ignores the return value, `TransducerPipeline::each()` stops iteration immediately when the callback returns `false`. This is consistent with how `each` behaves as a transducer step signal.

### `tap` never stops iteration

Conversely, `->tap()` calls your callback for side effects but always continues iteration, even if the callback returns `false`. Use `->each()` if you need to conditionally stop.

---

## How it works

Transducers are composable transformation factories. Each step (map, filter, take, etc.) is a factory that wraps the next step in the chain. When `transduce()` runs, all steps are composed right-to-left into a single pipeline object, then the source collection is iterated **once** — each element flows through the entire composed pipeline in a single pass.

Early termination is signalled by wrapping the accumulator in a `Reduced` sentinel object. When any step returns a `Reduced` value (e.g. `TakeStep` after collecting N items), the reduce loop unwraps it and stops immediately — no further elements are visited.

This is the same concept as Clojure's transducers, adapted for PHP's object model.

---

## Licence

MIT
