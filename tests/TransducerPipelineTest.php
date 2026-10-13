<?php

declare(strict_types=1);

use GavG\TransducibleCollections\Reduced;
use GavG\TransducibleCollections\StepInterface;
use GavG\TransducibleCollections\TransducerPipeline;
use Illuminate\Support\Collection;

it('transduces an unmodified collection', function () {
    $result = collect([1, 2, 3])->transducible()->transduce();

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->all())->toBe([1, 2, 3]);
});

it('supports map with values and keys', function () {
    $result = collect(['a' => 1, 'b' => 2, 'c' => 3])
        ->transducible()
        ->map(fn ($val, $key) => "{$key}:{$val}")
        ->transduce();

    expect($result->all())->toBe(['a' => 'a:1', 'b' => 'b:2', 'c' => 'c:3']);
});

it('supports single-parameter map callbacks', function () {
    $result = collect(['a', 'b', 'c'])
        ->transducible()
        ->map('strtoupper')
        ->transduce();

    expect($result->all())->toBe(['A', 'B', 'C']);
});

it('handles Closure::fromCallable wrapping a strict built-in correctly', function () {
    // Closure::fromCallable('strlen') produces a Closure that enforces strlen's
    // strict single-argument arity — calling it with (value, key) would throw
    // ArgumentCountError if arity detection treated it as a regular closure.
    $result = collect(['hello', 'world', 'foo'])
        ->transducible()
        ->map(\Closure::fromCallable('strtoupper'))
        ->transduce();

    expect($result->all())->toBe(['HELLO', 'WORLD', 'FOO']);

    // Also verify with filter — built-in wrapped in Closure
    $result = collect([1, 2, 3, 4, 5])
        ->transducible()
        ->filter(\Closure::fromCallable('is_int'))
        ->transduce();

    expect($result->values()->all())->toBe([1, 2, 3, 4, 5]);

    // And a two-parameter built-in wrapped in Closure — should pass both args
    $result = collect(['hello world', 'foo bar'])
        ->transducible()
        ->map(\Closure::fromCallable('strtolower'))
        ->transduce();

    expect($result->all())->toBe(['hello world', 'foo bar']);
});

it('supports mapWithKeys', function () {
    $result = collect([
        ['id' => 10, 'name' => 'Desk'],
        ['id' => 20, 'name' => 'Chair'],
    ])->transducible()
        ->mapWithKeys(fn ($item) => [$item['id'] => $item['name']])
        ->transduce();

    expect($result->all())->toBe([
        10 => 'Desk',
        20 => 'Chair',
    ]);
});

it('supports filter with callback and default truthy filter', function () {
    $truthy = collect([0, 1, false, 2, '', 3, null])
        ->transducible()
        ->filter()
        ->transduce();

    expect($truthy->all())->toBe([1 => 1, 3 => 2, 5 => 3]);

    $filtered = collect([1, 2, 3, 4, 5])
        ->transducible()
        ->filter(fn ($x) => $x % 2 === 0)
        ->transduce();

    expect($filtered->values()->all())->toBe([2, 4]);
});

it('supports reject', function () {
    $result = collect([1, 2, 3, 4, 5])
        ->transducible()
        ->reject(fn ($x) => $x % 2 === 0)
        ->transduce();

    expect($result->values()->all())->toBe([1, 3, 5]);

    $rejectTruthy = collect([0, 1, false, 2, '', 3, null])
        ->transducible()
        ->reject()
        ->transduce();

    expect($rejectTruthy->all())->toBe([0 => 0, 2 => false, 4 => '', 6 => null]);
});

it('supports each and terminates early when returning false', function () {
    $visited = [];

    $result = collect([1, 2, 3, 4, 5])
        ->transducible()
        ->each(function ($item) use (&$visited) {
            $visited[] = $item;
            if ($item === 3) {
                return false;
            }
        })
        ->map(fn ($x) => $x * 10)
        ->transduce();

    expect($visited)->toBe([1, 2, 3])
        ->and($result->values()->all())->toBe([10, 20]);
});

it('supports tap without breaking iteration even if return is false', function () {
    $tapped = [];

    $result = collect([1, 2, 3])
        ->transducible()
        ->tap(function ($item) use (&$tapped) {
            $tapped[] = $item;

            return false;
        })
        ->transduce();

    expect($tapped)->toBe([1, 2, 3])
        ->and($result->all())->toBe([1, 2, 3]);
});

it('supports take and halts processing immediately', function () {
    $processed = [];

    $result = collect([1, 2, 3, 4, 5])
        ->transducible()
        ->map(function ($x) use (&$processed) {
            $processed[] = $x;

            return $x * 2;
        })
        ->take(2)
        ->transduce();

    expect($result->values()->all())->toBe([2, 4])
        ->and($processed)->toBe([1, 2]); // Items 3, 4, 5 were never processed!
});

it('handles take with 0 or negative limits', function () {
    $result = collect([1, 2, 3])
        ->transducible()
        ->take(0)
        ->transduce();

    expect($result->all())->toBe([]);

    $resultNegative = collect([1, 2, 3])
        ->transducible()
        ->take(-1)
        ->transduce();

    expect($resultNegative->all())->toBe([]);
});

it('supports takeWhile and takeUntil', function () {
    $whileResult = collect([1, 2, 3, 4, 1, 2])
        ->transducible()
        ->takeWhile(fn ($x) => $x < 3)
        ->transduce();

    expect($whileResult->values()->all())->toBe([1, 2]);

    $untilResult = collect([1, 2, 3, 4, 5])
        ->transducible()
        ->takeUntil(fn ($x) => $x >= 3)
        ->transduce();

    expect($untilResult->values()->all())->toBe([1, 2]);
});

it('supports skip and drop', function () {
    $skipped = collect([1, 2, 3, 4, 5])
        ->transducible()
        ->skip(2)
        ->transduce();

    expect($skipped->values()->all())->toBe([3, 4, 5]);

    $dropped = collect([1, 2, 3, 4, 5])
        ->transducible()
        ->drop(3)
        ->transduce();

    expect($dropped->values()->all())->toBe([4, 5]);
});

it('supports skipWhile and skipUntil', function () {
    $whileResult = collect([1, 2, 5, 2, 1])
        ->transducible()
        ->skipWhile(fn ($x) => $x < 5)
        ->transduce();

    expect($whileResult->values()->all())->toBe([5, 2, 1]);

    $untilResult = collect([1, 2, 5, 2, 1])
        ->transducible()
        ->skipUntil(fn ($x) => $x >= 5)
        ->transduce();

    expect($untilResult->values()->all())->toBe([5, 2, 1]);
});

it('supports flatMap', function () {
    $result = collect([
        ['tags' => ['php', 'laravel']],
        ['tags' => ['pest', 'transducer']],
    ])->transducible()
        ->flatMap(fn ($item) => $item['tags'])
        ->transduce();

    expect($result->values()->all())->toBe(['php', 'laravel', 'pest', 'transducer']);
});

it('supports flatten with depth', function () {
    $result = collect([1, [2, [3, [4]]]])
        ->transducible()
        ->flatten(1)
        ->transduce();

    expect($result->values()->all())->toBe([1, 2, [3, [4]]]);

    $fullFlatten = collect([1, [2, [3, [4]]]])
        ->transducible()
        ->flatten()
        ->transduce();

    expect($fullFlatten->values()->all())->toBe([1, 2, 3, 4]);
});

it('supports values and keys', function () {
    $source = collect(['a' => 10, 'b' => 20, 'c' => 30]);

    expect($source->transducible()->values()->transduce()->all())->toBe([10, 20, 30])
        ->and($source->transducible()->keys()->transduce()->all())->toBe(['a', 'b', 'c']);
});

it('supports keyBy with string key and callback', function () {
    $data = collect([
        ['id' => 'first', 'name' => 'Alice'],
        ['id' => 'second', 'name' => 'Bob'],
    ]);

    $keyedByString = $data->transducible()->keyBy('id')->transduce();
    expect($keyedByString->get('first'))->toBe(['id' => 'first', 'name' => 'Alice'])
        ->and($keyedByString->get('second'))->toBe(['id' => 'second', 'name' => 'Bob']);

    $keyedByCallback = $data->transducible()->keyBy(fn ($item) => strtoupper($item['name']))->transduce();
    expect($keyedByCallback->has('ALICE'))->toBeTrue()
        ->and($keyedByCallback->has('BOB'))->toBeTrue();
});

it('supports unique with values, keys, and callbacks', function () {
    $uniqueVals = collect([1, 1, 2, 2, 3, 1])
        ->transducible()
        ->unique()
        ->values()
        ->transduce();

    expect($uniqueVals->all())->toBe([1, 2, 3]);

    $items = collect([
        ['role' => 'admin', 'name' => 'Alice'],
        ['role' => 'admin', 'name' => 'Bob'],
        ['role' => 'guest', 'name' => 'Charlie'],
    ]);

    $uniqueByKey = $items->transducible()->unique('role')->values()->transduce();
    expect($uniqueByKey->all())->toBe([
        ['role' => 'admin', 'name' => 'Alice'],
        ['role' => 'guest', 'name' => 'Charlie'],
    ]);
});

it('supports chunk with complete flushing', function () {
    $chunks = collect([1, 2, 3, 4, 5])
        ->transducible()
        ->chunk(2)
        ->transduce();

    expect($chunks->count())->toBe(3)
        ->and($chunks[0]->all())->toBe([1, 2])
        ->and($chunks[1]->all())->toBe([3, 4])
        ->and($chunks[2]->all())->toBe([5]);
});

it('supports pluck with value and optional key', function () {
    $data = collect([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ]);

    $plucked = $data->transducible()->pluck('name')->values()->transduce();
    expect($plucked->all())->toBe(['Alice', 'Bob']);

    $keyedPluck = $data->transducible()->pluck('name', 'id')->transduce();
    expect($keyedPluck->all())->toBe([1 => 'Alice', 2 => 'Bob']);
});

it('supports where, whereStrict, whereIn, whereNotIn, whereNull, whereNotNull', function () {
    $data = collect([
        ['name' => 'Hard Desk', 'price' => 100, 'category' => 'furniture', 'discount' => null],
        ['name' => 'Chair', 'price' => 50, 'category' => 'furniture', 'discount' => 10],
        ['name' => 'Book', 'price' => 10, 'category' => 'stationery', 'discount' => null],
    ]);

    // where 1 arg (truthy)
    $hasDiscount = $data->transducible()->where('discount')->values()->transduce();
    expect($hasDiscount->count())->toBe(1)
        ->and($hasDiscount[0]['name'])->toBe('Chair');

    // where 2 args (equals)
    $stationery = $data->transducible()->where('category', 'stationery')->values()->transduce();
    expect($stationery->count())->toBe(1)
        ->and($stationery[0]['name'])->toBe('Book');

    // where 3 args (operator)
    $expensive = $data->transducible()->where('price', '>', 50)->values()->transduce();
    expect($expensive->count())->toBe(1)
        ->and($expensive[0]['name'])->toBe('Hard Desk');

    // whereStrict
    $strict = $data->transducible()->whereStrict('price', 50)->values()->transduce();
    expect($strict->count())->toBe(1);

    // whereIn / whereNotIn
    $in = $data->transducible()->whereIn('price', [10, 50])->values()->transduce();
    expect($in->count())->toBe(2);

    $notIn = $data->transducible()->whereNotIn('price', [10, 50])->values()->transduce();
    expect($notIn->count())->toBe(1)
        ->and($notIn[0]['name'])->toBe('Hard Desk');

    // whereNull / whereNotNull
    $nullDiscount = $data->transducible()->whereNull('discount')->values()->transduce();
    expect($nullDiscount->count())->toBe(2);

    $notNullDiscount = $data->transducible()->whereNotNull('discount')->values()->transduce();
    expect($notNullDiscount->count())->toBe(1);
});

it('supports slice', function () {
    $result = collect([1, 2, 3, 4, 5, 6, 7])
        ->transducible()
        ->slice(2, 3)
        ->values()
        ->transduce();

    expect($result->all())->toBe([3, 4, 5]);
});

it('supports when and unless', function () {
    $whenTrue = collect([1, 2, 3])
        ->transducible()
        ->when(true, fn (TransducerPipeline $t) => $t->map(fn ($x) => $x * 10))
        ->transduce();

    expect($whenTrue->all())->toBe([10, 20, 30]);

    $whenFalseWithDefault = collect([1, 2, 3])
        ->transducible()
        ->when(false, fn (TransducerPipeline $t) => $t->map(fn ($x) => $x * 10), fn (TransducerPipeline $t) => $t->map(fn ($x) => $x + 1))
        ->transduce();

    expect($whenFalseWithDefault->all())->toBe([2, 3, 4]);

    $unlessTrue = collect([1, 2, 3])
        ->transducible()
        ->unless(true, fn (TransducerPipeline $t) => $t->map(fn ($x) => $x * 10))
        ->transduce();

    expect($unlessTrue->all())->toBe([1, 2, 3]);
});

it('supports pipe', function () {
    $result = collect([1, 2, 3])
        ->transducible()
        ->pipe(fn (TransducerPipeline $t) => $t->map(fn ($x) => $x * 2))
        ->transduce();

    expect($result->all())->toBe([2, 4, 6]);
});

it('supports transduce with custom step and reduce', function () {
    $sum = collect([1, 2, 3, 4])
        ->transducible()
        ->map(fn ($x) => $x * 2)
        ->transduce(fn ($acc, $val) => $acc + $val, 0);

    expect($sum)->toBe(20);

    $reduced = collect([1, 2, 3, 4])
        ->transducible()
        ->reduce(fn ($acc, $val) => $acc + $val, 100);

    expect($reduced)->toBe(110);
});

it('supports first with and without callback and handles short-circuiting', function () {
    $examined = [];

    $first = collect([1, 2, 3, 4, 5])
        ->transducible()
        ->tap(function ($x) use (&$examined) {
            $examined[] = $x;
        })
        ->first();

    expect($first)->toBe(1)
        ->and($examined)->toBe([1]); // Only first element examined

    $examined = [];
    $firstMatch = collect([1, 2, 3, 4, 5])
        ->transducible()
        ->tap(function ($x) use (&$examined) {
            $examined[] = $x;
        })
        ->first(fn ($x) => $x > 2);

    expect($firstMatch)->toBe(3)
        ->and($examined)->toBe([1, 2, 3]); // Stopped at 3!

    $default = collect([1, 2])
        ->transducible()
        ->first(fn ($x) => $x > 10, 'default_val');

    expect($default)->toBe('default_val');
});

it('supports count, all, toArray, collect', function () {
    $pipeline = collect([1, 2, 3])->transducible()->map(fn ($x) => $x * 2);

    expect($pipeline->count())->toBe(3)
        ->and($pipeline->all())->toBe([2, 4, 6])
        ->and($pipeline->toArray())->toBe([2, 4, 6])
        ->and($pipeline->collect())->toBeInstanceOf(Collection::class);
});

it('supports custom transducers via addTransducer', function () {
    // Custom transducer that repeats each element twice
    $repeatTransducer = function (StepInterface $next): StepInterface {
        return new class($next) implements StepInterface {
            public function __construct(protected StepInterface $next) {}

            public function step(mixed $acc, mixed $value, mixed $key): mixed
            {
                $acc = $this->next->step($acc, $value, null);
                if (Reduced::isReduced($acc)) {
                    return $acc;
                }

                return $this->next->step($acc, $value, null);
            }

            public function complete(mixed $acc): mixed
            {
                return $this->next->complete($acc);
            }
        };
    };

    $result = collect([1, 2])
        ->transducible()
        ->addTransducer($repeatTransducer)
        ->transduce();

    expect($result->all())->toBe([1, 1, 2, 2]);
});

it('supports evaluate as an alias for transduce', function () {
    $result = collect([1, 2, 3])
        ->transducible()
        ->map(fn ($x) => $x * 10)
        ->evaluate();

    expect($result->all())->toBe([10, 20, 30]);
});
