<?php

use App\Services\SurveillanceStatistics;

it('calculates Poisson upper tails including zero and large means', function () {
    $statistics = new SurveillanceStatistics;
    expect($statistics->poissonTail(3, 1))->toEqualWithDelta(0.0803013970713942, 1e-12)
        ->and($statistics->poissonTail(10, 2))->toEqualWithDelta(0.0000464980750173, 1e-14)
        ->and($statistics->poissonTail(1000, 1000))->toEqualWithDelta(0.504205244180216, 1e-10)
        ->and($statistics->poissonTail(0, 0))->toBe(1.0)
        ->and($statistics->poissonTail(1, 0))->toBe(0.0)
        ->and($statistics->poissonTail(200, 1))->toBeLessThan(1e-100);
});

it('calculates negative binomial upper tails against a geometric reference distribution', function () {
    $statistics = new SurveillanceStatistics;
    expect($statistics->negativeBinomialTail(3, 2, 1))->toEqualWithDelta((2 / 3) ** 3, 1e-12)
        ->and($statistics->negativeBinomialTail(20, 2, 1))->toEqualWithDelta((2 / 3) ** 20, 1e-14)
        ->and($statistics->negativeBinomialTail(0, 2, 1))->toBe(1.0)
        ->and($statistics->negativeBinomialTail(10, 5, 2))->toEqualWithDelta((5 / 7) ** 10 * (1 + 10 * 2 / 7), 1e-12);
});

it('adjusts for attendance and uses a negative binomial for overdispersed counts', function () {
    $statistics = new SurveillanceStatistics;
    $stable = array_fill(0, 8, ['count' => 2, 'attendance' => 10]);
    expect($statistics->evaluate(4, 20, $stable, 1.5)['expected'])->toBe(4.0)
        ->and($statistics->evaluate(4, 20, $stable, 1.5)['model'])->toBe('Poisson');
    $variable = array_map(fn (int $count): array => ['count' => $count, 'attendance' => 30], [0, 0, 0, 0, 12, 15, 18, 20]);
    $result = $statistics->evaluate(20, 30, $variable, 1.5);
    expect($result['model'])->toBe('Negative Binomial')->and($result['size'])->toBeGreaterThan(0)
        ->and($result['probability'])->toBeGreaterThan($statistics->poissonTail(20, $result['expected']));
});
