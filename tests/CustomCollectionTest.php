<?php

declare(strict_types=1);

use Illuminate\Support\Collection;

class CustomSubCollection extends Collection
{
    public function customMethod(): string
    {
        return 'custom';
    }
}

it('preserves custom Collection subclass when transducing', function () {
    $custom = new CustomSubCollection([1, 2, 3, 4]);

    $result = $custom->transducible()
        ->map(fn ($x) => $x * 10)
        ->transduce();

    expect($result)->toBeInstanceOf(CustomSubCollection::class)
        ->and($result->all())->toBe([10, 20, 30, 40])
        ->and($result->customMethod())->toBe('custom');
});

it('preserves custom Collection subclass in chunks', function () {
    $custom = new CustomSubCollection([1, 2, 3, 4]);

    $chunks = $custom->transducible()
        ->chunk(2)
        ->transduce();

    expect($chunks)->toBeInstanceOf(CustomSubCollection::class)
        ->and($chunks[0])->toBeInstanceOf(CustomSubCollection::class)
        ->and($chunks[0]->customMethod())->toBe('custom')
        ->and($chunks[1])->toBeInstanceOf(CustomSubCollection::class);
});

it('preserves custom Collection subclass in groupBy', function () {
    $custom = new CustomSubCollection([
        ['type' => 'a', 'val' => 1],
        ['type' => 'b', 'val' => 2],
        ['type' => 'a', 'val' => 3],
    ]);

    $grouped = $custom->transducible()
        ->groupBy('type')
        ->transduce();

    expect($grouped)->toBeInstanceOf(CustomSubCollection::class)
        ->and($grouped->get('a'))->toBeInstanceOf(CustomSubCollection::class)
        ->and($grouped->get('a')->customMethod())->toBe('custom');
});

it('preserves custom Collection subclass with collect()', function () {
    $custom = new CustomSubCollection([1, 2, 3]);

    $result = $custom->transducible()->map(fn ($x) => $x * 2)->collect();

    expect($result)->toBeInstanceOf(CustomSubCollection::class);
});

it('allows explicitly collecting into a specific collection class with into()', function () {
    $base = collect([1, 2, 3]);

    $result = $base->transducible()
        ->map(fn ($x) => $x * 5)
        ->into(CustomSubCollection::class)
        ->transduce();

    expect($result)->toBeInstanceOf(CustomSubCollection::class)
        ->and($result->all())->toBe([5, 10, 15])
        ->and($result->customMethod())->toBe('custom');
});

it('supports passing collection class directly to collect()', function () {
    $base = collect([10, 20]);

    $result = $base->transducible()
        ->collect(CustomSubCollection::class);

    expect($result)->toBeInstanceOf(CustomSubCollection::class)
        ->and($result->customMethod())->toBe('custom');
});
