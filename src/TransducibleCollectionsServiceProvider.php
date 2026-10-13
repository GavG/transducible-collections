<?php

declare(strict_types=1);

namespace GavG\TransducibleCollections;

use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\ServiceProvider;

class TransducibleCollectionsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        Collection::macro('transducible', function () {
            /** @var Collection<int|string, mixed> $this */
            return new TransducerPipeline($this);
        });

        if (class_exists(LazyCollection::class)) {
            LazyCollection::macro('transducible', function () {
                /** @var LazyCollection<int|string, mixed> $this */
                return new TransducerPipeline($this);
            });
        }
    }
}
