<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections;

class Reduced
{
    public function __construct(public mixed $value)
    {
    }

    public static function wrap(mixed $value): self
    {
        return new self($value);
    }

    public static function isReduced(mixed $value): bool
    {
        return $value instanceof self;
    }

    public static function deref(mixed $value): mixed
    {
        return $value instanceof self ? $value->value : $value;
    }
}
