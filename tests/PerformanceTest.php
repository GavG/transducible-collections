<?php

declare(strict_types=1);

use Illuminate\Support\Collection;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Run $fn $iterations times, with one warm-up call, and return the total
 * elapsed time in seconds (excluding the warm-up).
 */
function benchmarkRun(callable $fn, int $iterations): float
{
    $fn(); // warm-up: avoid cold-start skew

    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $fn();
    }

    return (hrtime(true) - $start) / 1_000_000_000;
}

/**
 * Format a duration in seconds to a human-readable string.
 */
function formatDuration(float $seconds): string
{
    $ms = $seconds * 1_000;
    if ($ms >= 1.0) {
        return number_format($ms, 3) . ' ms';
    }

    return number_format($ms * 1_000, 3) . ' µs';
}

/**
 * Print a formatted benchmark comparison block and return the speedup ratio.
 */
function printBenchmark(string $label, float $standardTime, float $transducedTime, int $iterations): float
{
    $ratio   = $standardTime / $transducedTime;
    $savedMs = ($standardTime - $transducedTime) * 1_000;

    echo PHP_EOL;
    echo "  [{$label}]" . PHP_EOL;
    echo '    Standard   : ' . formatDuration($standardTime)
        . ' total / ' . formatDuration($standardTime / $iterations) . ' per run'
        . " ({$iterations} iterations)" . PHP_EOL;
    echo '    Transduced : ' . formatDuration($transducedTime)
        . ' total / ' . formatDuration($transducedTime / $iterations) . ' per run'
        . " ({$iterations} iterations)" . PHP_EOL;

    if ($transducedTime < $standardTime) {
        echo '    ✓ Speedup  : ' . number_format($ratio, 2) . 'x faster'
            . ' (+' . number_format(abs($savedMs), 2) . ' ms saved total)' . PHP_EOL;
    } else {
        echo '    ✗ Overhead : Standard was ' . number_format(1 / $ratio, 2) . 'x faster'
            . ' (' . number_format(abs($savedMs), 2) . ' ms overhead total)' . PHP_EOL;
    }

    return $ratio;
}

// ---------------------------------------------------------------------------
// Dataset: 5,000 records, 200 iterations
// ---------------------------------------------------------------------------

const RECORD_COUNT = 5000;
const ITERATIONS   = 200;

$records = collect(array_map(fn (int $i) => [
    'id'       => $i,
    'score'    => $i % 100,
    'active'   => $i % 3 !== 0,
    'category' => ['A', 'B', 'C'][$i % 3],
    'value'    => $i * 1.5,
    'name'     => 'item_' . $i,
], range(1, RECORD_COUNT)));

// ---------------------------------------------------------------------------
// Early-termination tests — the core strength of transducers
//
// Standard collection chains process ALL records through each step before
// applying take/skip. Transducers fuse all steps into a single pass and
// halt immediately once the limit is reached — so they only ever touch
// the minimum number of records necessary.
// ---------------------------------------------------------------------------

it('transduced map→filter→take(10) is much faster due to early termination', function () use ($records) {
    // Standard: map ALL 5000 → filter ALL → take 10 (3 full-size intermediate arrays)
    $standardTime = benchmarkRun(function () use ($records) {
        $records
            ->map(fn ($item) => array_merge($item, ['computed' => $item['value'] ** 2]))
            ->filter(fn ($item) => $item['active'] && $item['score'] > 5)
            ->take(10)
            ->values()
            ->all();
    }, ITERATIONS);

    // Transduced: single fused pass — stops the moment 10 items pass the filter
    $transducedTime = benchmarkRun(function () use ($records) {
        $records
            ->transducible()
            ->map(fn ($item) => array_merge($item, ['computed' => $item['value'] ** 2]))
            ->filter(fn ($item) => $item['active'] && $item['score'] > 5)
            ->take(10)
            ->values()
            ->all();
    }, ITERATIONS);

    $ratio = printBenchmark(
        'map→filter→take(10) early termination (' . RECORD_COUNT . ' records)',
        $standardTime,
        $transducedTime,
        ITERATIONS
    );

    expect($transducedTime)->toBeLessThan($standardTime, sprintf(
        'Transduced (%.3f ms) should be faster than standard (%.3f ms) due to early termination.',
        $transducedTime * 1000,
        $standardTime * 1000
    ));
})->group('performance');

it('transduced filter→map→take(25) is faster due to early termination', function () use ($records) {
    $standardTime = benchmarkRun(function () use ($records) {
        $records
            ->filter(fn ($item) => $item['active'])
            ->map(fn ($item) => strtoupper($item['name']) . '_' . $item['id'])
            ->take(25)
            ->values()
            ->all();
    }, ITERATIONS);

    $transducedTime = benchmarkRun(function () use ($records) {
        $records
            ->transducible()
            ->filter(fn ($item) => $item['active'])
            ->map(fn ($item) => strtoupper($item['name']) . '_' . $item['id'])
            ->take(25)
            ->values()
            ->all();
    }, ITERATIONS);

    printBenchmark(
        'filter→map→take(25) early termination (' . RECORD_COUNT . ' records)',
        $standardTime,
        $transducedTime,
        ITERATIONS
    );

    expect($transducedTime)->toBeLessThan($standardTime);
})->group('performance');

it('transduced filter→map→skip(200)→take(50) is faster due to early termination', function () use ($records) {
    // Standard must: filter all 5000 → map surviving → skip 200 → take 50
    $standardTime = benchmarkRun(function () use ($records) {
        $records
            ->filter(fn ($item) => $item['active'])
            ->map(fn ($item) => $item['name'] . '::' . $item['value'])
            ->skip(200)
            ->take(50)
            ->values()
            ->all();
    }, ITERATIONS);

    // Transduced: single pass, exits once 50 items after the skip are collected
    $transducedTime = benchmarkRun(function () use ($records) {
        $records
            ->transducible()
            ->filter(fn ($item) => $item['active'])
            ->map(fn ($item) => $item['name'] . '::' . $item['value'])
            ->skip(200)
            ->take(50)
            ->values()
            ->all();
    }, ITERATIONS);

    printBenchmark(
        'filter→map→skip(200)→take(50) early termination (' . RECORD_COUNT . ' records)',
        $standardTime,
        $transducedTime,
        ITERATIONS
    );

    expect($transducedTime)->toBeLessThan($standardTime);
})->group('performance');

it('transduced map→filter→take is faster when take targets a small fraction of the source', function () use ($records) {
    // take(10) from 5000 records after a filter that passes ~2/3:
    // Standard: map ALL 5000, filter ALL 5000, then take 10 (three intermediate arrays).
    // Transduced: processes only ~15 source records before halting — a 333x source reduction.
    $standardTime = benchmarkRun(function () use ($records) {
        $records
            ->map(fn ($item) => [
                'key'   => $item['category'] . '-' . $item['id'],
                'value' => $item['value'] * M_PI,
                'score' => $item['score'],
            ])
            ->filter(fn ($item) => $item['score'] % 2 === 0)
            ->take(10)
            ->values()
            ->all();
    }, ITERATIONS);

    // Transduced: single fused pass, stops after 10 items pass the filter
    $transducedTime = benchmarkRun(function () use ($records) {
        $records
            ->transducible()
            ->map(fn ($item) => [
                'key'   => $item['category'] . '-' . $item['id'],
                'value' => $item['value'] * M_PI,
                'score' => $item['score'],
            ])
            ->filter(fn ($item) => $item['score'] % 2 === 0)
            ->take(10)
            ->values()
            ->all();
    }, ITERATIONS);

    printBenchmark(
        'map→filter→take(10) — processes ~15 of ' . RECORD_COUNT . ' source records',
        $standardTime,
        $transducedTime,
        ITERATIONS
    );

    expect($transducedTime)->toBeLessThan($standardTime);
})->group('performance');

it('transduced takeWhile terminates earlier than a standard chained filter', function () use ($records) {
    // Standard: filter ALL records (no built-in takeWhile short-circuit across chained ops)
    $standardTime = benchmarkRun(function () use ($records) {
        $records
            ->map(fn ($item) => $item['value'] * 2)
            ->takeWhile(fn ($value) => $value < 500)
            ->values()
            ->all();
    }, ITERATIONS);

    // Transduced: map + takeWhile fused — stops the moment the predicate fails
    $transducedTime = benchmarkRun(function () use ($records) {
        $records
            ->transducible()
            ->map(fn ($item) => $item['value'] * 2)
            ->takeWhile(fn ($value) => $value < 500)
            ->values()
            ->all();
    }, ITERATIONS);

    printBenchmark(
        'map→takeWhile(<500) early termination (' . RECORD_COUNT . ' records)',
        $standardTime,
        $transducedTime,
        ITERATIONS
    );

    expect($transducedTime)->toBeLessThan($standardTime);
})->group('performance');


it('full-pass multiple maps are informational — standard array_map beats transduced reduce loop', function () use ($records) {
    // This test is intentionally informational only (no assertion).
    //
    // For full-pass transforms (all records, no early termination), the standard
    // Collection uses C-level array_map() and does not pay the PHP reduce loop's
    // copy-on-write array accumulator cost (CollectStep returns $acc by value on
    // every element, triggering 5000 array copies). Transducers win only when
    // early termination dramatically reduces the number of elements processed.
    //
    // Fewer iterations used here as the transduced path is intentionally slow
    // for this case and we don't need high statistical accuracy.
    $fullPassIter = 20;

    $standardTime = benchmarkRun(function () use ($records) {
        $records
            ->map(fn ($item) => $item['value'] * 2)
            ->map(fn ($value) => $value + 3)
            ->map(fn ($value) => $value * 4)
            ->map(fn ($value) => $value / 2)
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();
    }, $fullPassIter);

    $transducedTime = benchmarkRun(function () use ($records) {
        $records
            ->transducible()
            ->map(fn ($item) => $item['value'] * 2)
            ->map(fn ($value) => $value + 3)
            ->map(fn ($value) => $value * 4)
            ->map(fn ($value) => $value / 2)
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();
    }, $fullPassIter);

    printBenchmark(
        'full-pass 5×map (' . RECORD_COUNT . ' records) — informational',
        $standardTime,
        $transducedTime,
        $fullPassIter
    );

    // No assertion — standard wins here due to C-level array_map vs PHP reduce loop.
    // Transducer value is in early-termination scenarios (tested above).
    expect(true)->toBeTrue();
})->group('performance');