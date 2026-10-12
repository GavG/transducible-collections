<?php

declare(strict_types=1);

class TestUser
{
    public function __construct(
        public string $name,
        public string $role,
        public bool $active,
        public int $score = 0
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function markAsDone(): void
    {
        $this->score += 10;
    }
}

it('supports map with higher order property access', function () {
    $users = collect([
        new TestUser('Alice', 'admin', true),
        new TestUser('Bob', 'guest', false),
    ]);

    $names = $users->transducible()->map->name->transduce();

    expect($names->all())->toBe(['Alice', 'Bob']);

    // Works with array items as well
    $arrayData = collect([
        ['name' => 'Charlie'],
        ['name' => 'Dana'],
    ]);

    $arrayNames = $arrayData->transducible()->map->name->transduce();
    expect($arrayNames->all())->toBe(['Charlie', 'Dana']);
});

it('supports map with higher order method invocation', function () {
    $users = collect([
        new TestUser('Alice', 'admin', true),
        new TestUser('Bob', 'guest', false),
    ]);

    $names = $users->transducible()->map->getName()->transduce();

    expect($names->all())->toBe(['Alice', 'Bob']);
});

it('supports filter and reject with higher order method invocation', function () {
    $users = collect([
        new TestUser('Alice', 'admin', true),
        new TestUser('Bob', 'guest', false),
        new TestUser('Charlie', 'dev', true),
    ]);

    $active = $users->transducible()->filter->isActive()->values()->transduce();
    expect($active->count())->toBe(2)
        ->and($active[0]->name)->toBe('Alice')
        ->and($active[1]->name)->toBe('Charlie');

    $inactive = $users->transducible()->reject->isActive()->values()->transduce();
    expect($inactive->count())->toBe(1)
        ->and($inactive[0]->name)->toBe('Bob');
});

it('supports filter and reject with higher order property access', function () {
    $users = collect([
        new TestUser('Alice', 'admin', true),
        new TestUser('Bob', 'guest', false),
    ]);

    $active = $users->transducible()->filter->active->values()->transduce();
    expect($active->count())->toBe(1)
        ->and($active[0]->name)->toBe('Alice');

    $inactive = $users->transducible()->reject->active->values()->transduce();
    expect($inactive->count())->toBe(1)
        ->and($inactive[0]->name)->toBe('Bob');
});

it('supports each with higher order method invocation', function () {
    $u1 = new TestUser('Alice', 'admin', true);
    $u2 = new TestUser('Bob', 'guest', false);

    $users = collect([$u1, $u2]);

    $users->transducible()->each->markAsDone()->transduce();

    expect($u1->score)->toBe(10)
        ->and($u2->score)->toBe(10);
});

it('supports groupBy with higher order property and method', function () {
    $users = collect([
        new TestUser('Alice', 'admin', true),
        new TestUser('Bob', 'guest', false),
        new TestUser('Charlie', 'admin', true),
    ]);

    $byProp = $users->transducible()->groupBy->role->transduce();
    expect($byProp->keys()->all())->toBe(['admin', 'guest'])
        ->and($byProp->get('admin')->count())->toBe(2)
        ->and($byProp->get('guest')->count())->toBe(1);

    $byMethod = $users->transducible()->groupBy->getRole()->transduce();
    expect($byMethod->keys()->all())->toBe(['admin', 'guest'])
        ->and($byMethod->get('admin')->count())->toBe(2);
});

it('supports keyBy with higher order property and method', function () {
    $users = collect([
        new TestUser('Alice', 'admin', true),
        new TestUser('Bob', 'guest', false),
    ]);

    $byProp = $users->transducible()->keyBy->name->transduce();
    expect($byProp->has('Alice'))->toBeTrue()
        ->and($byProp->get('Alice')->name)->toBe('Alice');

    $byMethod = $users->transducible()->keyBy->getName()->transduce();
    expect($byMethod->has('Bob'))->toBeTrue();
});

it('supports unique with higher order property', function () {
    $users = collect([
        new TestUser('Alice', 'admin', true),
        new TestUser('Bob', 'admin', false),
        new TestUser('Charlie', 'guest', true),
    ]);

    $uniqueRoles = $users->transducible()->unique->role->values()->transduce();

    expect($uniqueRoles->count())->toBe(2)
        ->and($uniqueRoles[0]->name)->toBe('Alice')
        ->and($uniqueRoles[1]->name)->toBe('Charlie');
});

it('supports chaining multiple higher order proxies', function () {
    $users = collect([
        new TestUser('Alice', 'admin', true),
        new TestUser('Bob', 'guest', false),
        new TestUser('Charlie', 'admin', true),
        new TestUser('Dave', 'guest', false),
    ]);

    $names = $users->transducible()
        ->filter->isActive()
        ->map->getName()
        ->values()
        ->transduce();

    expect($names->all())->toBe(['Alice', 'Charlie']);
});

it('throws exception when accessing non-proxyable property', function () {
    $pipeline = collect([1, 2, 3])->transducible();

    expect(fn () => $pipeline->nonExistentProxy)
        ->toThrow(Exception::class, 'Property [nonExistentProxy] does not exist on this pipeline instance.');
});
