<?php

declare(strict_types=1);

use GavG\TransducibleCollections\TransducerPipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

it('registers the transducible macro on Collection', function () {
    expect(Collection::hasMacro('transducible'))->toBeTrue();

    $collection = collect([1, 2, 3]);
    $pipeline = $collection->transducible();

    expect($pipeline)->toBeInstanceOf(TransducerPipeline::class)
        ->and($pipeline->getCollection())->toBe($collection);
});

it('registers the transducible macro on LazyCollection', function () {
    expect(LazyCollection::hasMacro('transducible'))->toBeTrue();

    $lazy = LazyCollection::make([1, 2, 3]);
    $pipeline = $lazy->transducible();

    expect($pipeline)->toBeInstanceOf(TransducerPipeline::class)
        ->and($pipeline->getCollection())->toBe($lazy);
});
