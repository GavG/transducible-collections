<?php

declare(strict_types=1);

use Illuminate\Support\Collection;

it('groups by string key', function () {
    $data = collect([
        ['dept' => 'Engineering', 'name' => 'Alice'],
        ['dept' => 'HR', 'name' => 'Bob'],
        ['dept' => 'Engineering', 'name' => 'Charlie'],
    ]);

    $grouped = $data->transducible()->groupBy('dept')->transduce();

    expect($grouped)->toBeInstanceOf(Collection::class)
        ->and($grouped->keys()->all())->toBe(['Engineering', 'HR'])
        ->and($grouped->get('Engineering'))->toBeInstanceOf(Collection::class)
        ->and($grouped->get('Engineering')->count())->toBe(2)
        ->and($grouped->get('HR')->count())->toBe(1);
});

it('groups by callback', function () {
    $data = collect([
        ['score' => 90, 'name' => 'Alice'],
        ['score' => 45, 'name' => 'Bob'],
        ['score' => 85, 'name' => 'Charlie'],
    ]);

    $grouped = $data->transducible()
        ->groupBy(fn ($item) => $item['score'] >= 50 ? 'pass' : 'fail')
        ->transduce();

    expect($grouped->keys()->all())->toBe(['pass', 'fail'])
        ->and($grouped->get('pass')->pluck('name')->all())->toBe(['Alice', 'Charlie'])
        ->and($grouped->get('fail')->pluck('name')->all())->toBe(['Bob']);
});

it('groups with nested array of keys', function () {
    $data = collect([
        ['dept' => 'IT', 'role' => 'lead', 'name' => 'Alice'],
        ['dept' => 'IT', 'role' => 'dev', 'name' => 'Bob'],
        ['dept' => 'Sales', 'role' => 'lead', 'name' => 'Charlie'],
    ]);

    $grouped = $data->transducible()->groupBy(['dept', 'role'])->transduce();

    expect($grouped->keys()->all())->toBe(['IT', 'Sales'])
        ->and($grouped->get('IT'))->toBeInstanceOf(Collection::class)
        ->and($grouped->get('IT')->keys()->all())->toBe(['lead', 'dev'])
        ->and($grouped->get('IT')->get('lead')->first()['name'])->toBe('Alice');
});

it('groups with preserveKeys', function () {
    $data = collect([
        'k1' => ['dept' => 'IT', 'name' => 'Alice'],
        'k2' => ['dept' => 'IT', 'name' => 'Bob'],
    ]);

    $grouped = $data->transducible()->groupBy('dept', true)->transduce();

    expect($grouped->get('IT')->keys()->all())->toBe(['k1', 'k2']);
});

it('allows operations before and after groupBy', function () {
    $data = collect([
        ['dept' => 'IT', 'salary' => 100],
        ['dept' => 'IT', 'salary' => 120],
        ['dept' => 'HR', 'salary' => 80],
        ['dept' => 'HR', 'salary' => 90],
        ['dept' => 'Legal', 'salary' => 50],
    ]);

    // filter before groupBy, map after groupBy
    $result = $data->transducible()
        ->filter(fn ($item) => $item['salary'] >= 80)
        ->groupBy('dept')
        ->map(fn (Collection $group) => $group->sum('salary'))
        ->transduce();

    expect($result->all())->toBe([
        'IT' => 220,
        'HR' => 170,
    ]);
});
