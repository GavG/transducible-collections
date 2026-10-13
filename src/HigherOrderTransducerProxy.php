<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections;

use GavG\TransducibleCollections\TransducerPipeline;

class HigherOrderTransducerProxy
{
    public function __construct(
        protected TransducerPipeline $pipeline,
        protected string $method
    ) {
    }

    /**
     * Proxy accessing an attribute onto the pipeline items.
     */
    public function __get(string $key): TransducerPipeline
    {
        return $this->pipeline->{$this->method}(function ($value) use ($key) {
            return is_array($value) ? ($value[$key] ?? null) : ($value->{$key} ?? null);
        });
    }

    /**
     * Proxy a method call onto the pipeline items.
     */
    /** @param array<mixed> $parameters */
    public function __call(string $method, array $parameters): TransducerPipeline
    {
        return $this->pipeline->{$this->method}(function ($value) use ($method, $parameters) {
            return is_string($value)
                ? $value::{$method}(...$parameters)
                : $value->{$method}(...$parameters);
        });
    }
}
