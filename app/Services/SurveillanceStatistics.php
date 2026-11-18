<?php

namespace App\Services;

use RuntimeException;

class SurveillanceStatistics
{
    public function outcome(int $observed, float $expected, float $adjustedProbability, float $alpha, float $watchAlpha, int $minimumCases = 1): string
    {
        if ($observed <= $expected) {
            return 'Normal';
        }

        if ($adjustedProbability <= $alpha) {
            return $observed >= $minimumCases ? 'Alert' : 'Signal';
        }

        return $adjustedProbability <= $watchAlpha ? 'Watch' : 'Normal';
    }

    /**
     * The upper-tail count probabilities use regularized gamma (Poisson) and beta
     * (negative binomial). Direct special-function evaluation avoids cancellation
     * when a small upper-tail probability would otherwise be subtracted from one.
     */
    public function poissonTail(int $observed, float $expected): float
    {
        if ($observed < 0 || $expected < 0 || ! is_finite($expected)) {
            throw new RuntimeException('Invalid Poisson parameters.');
        }
        if ($observed === 0) {
            return 1.0;
        }
        if ($expected === 0.0) {
            return 0.0;
        }

        return $this->gammaP((float) $observed, $expected);
    }

    public function negativeBinomialTail(int $observed, float $expected, float $size): float
    {
        if ($observed < 0 || $expected <= 0 || $size <= 0 || ! is_finite($expected) || ! is_finite($size)) {
            throw new RuntimeException('Invalid negative binomial parameters.');
        }
        if ($observed === 0) {
            return 1.0;
        }

        return $this->beta($expected / ($size + $expected), (float) $observed, $size);
    }

    private function logGamma(float $value): float
    {
        $coefficients = [676.5203681218851, -1259.1392167224028, 771.32342877765313, -176.61502916214059, 12.507343278686905, -0.13857109526572012, 9.984369578019572e-6, 1.5056327351493116e-7];
        if ($value < 0.5) {
            return log(M_PI) - log(sin(M_PI * $value)) - $this->logGamma(1 - $value);
        }
        $value--;
        $sum = 0.99999999999980993;
        foreach ($coefficients as $index => $coefficient) {
            $sum += $coefficient / ($value + $index + 1);
        }
        $t = $value + 7.5;

        return 0.5 * log(2 * M_PI) + ($value + 0.5) * log($t) - $t + log($sum);
    }

    private function gammaP(float $shape, float $value): float
    {
        $factor = exp(-$value + $shape * log($value) - $this->logGamma($shape));
        if ($value < $shape + 1) {
            $term = $sum = 1 / $shape;
            for ($index = 1; $index <= 10000; $index++) {
                $term *= $value / ($shape + $index);
                $sum += $term;
                if (abs($term) < abs($sum) * 1e-14) {
                    return max(0.0, min(1.0, $sum * $factor));
                }
            }
        } else {
            $b = $value + 1 - $shape;
            $c = 1e300;
            $d = 1 / $b;
            $h = $d;
            for ($index = 1; $index <= 10000; $index++) {
                $a = -$index * ($index - $shape);
                $b += 2;
                $d = $this->nonzero($a * $d + $b);
                $c = $this->nonzero($b + $a / $c);
                $d = 1 / $d;
                $delta = $d * $c;
                $h *= $delta;
                if (abs($delta - 1) < 1e-14) {
                    return max(0.0, min(1.0, 1 - $factor * $h));
                }
            }
        }
        throw new RuntimeException('Poisson probability calculation failed to converge.');
    }

    private function beta(float $value, float $a, float $b): float
    {
        if ($value <= 0) {
            return 0.0;
        }
        if ($value >= 1) {
            return 1.0;
        }
        $factor = exp($this->logGamma($a + $b) - $this->logGamma($a) - $this->logGamma($b) + $a * log($value) + $b * log1p(-$value));
        $probability = $value < ($a + 1) / ($a + $b + 2)
            ? $factor * $this->betaFraction($value, $a, $b) / $a
            : 1 - $factor * $this->betaFraction(1 - $value, $b, $a) / $b;

        return max(0.0, min(1.0, $probability));
    }

    private function betaFraction(float $value, float $a, float $b): float
    {
        $c = 1.0;
        $d = 1 / $this->nonzero(1 - ($a + $b) * $value / ($a + 1));
        $h = $d;
        for ($index = 1; $index <= 10000; $index++) {
            $twice = 2 * $index;
            $aa = $index * ($b - $index) * $value / (($a + $twice - 1) * ($a + $twice));
            $d = 1 / $this->nonzero(1 + $aa * $d);
            $c = $this->nonzero(1 + $aa / $c);
            $h *= $d * $c;
            $aa = -($a + $index) * ($a + $b + $index) * $value / (($a + $twice) * ($a + $twice + 1));
            $d = 1 / $this->nonzero(1 + $aa * $d);
            $c = $this->nonzero(1 + $aa / $c);
            $delta = $d * $c;
            $h *= $delta;
            if (abs($delta - 1) < 1e-14) {
                return $h;
            }
        }
        throw new RuntimeException('Negative binomial probability calculation failed to converge.');
    }

    private function nonzero(float $value): float
    {
        return abs($value) < 1e-300 ? ($value < 0 ? -1e-300 : 1e-300) : $value;
    }

    /**
     * Fit a constant syndrome rate with attendance as exposure. Pearson dispersion
     * selects NB2 when residual variance exceeds the configurable Poisson tolerance.
     *
     * @param  array<int, array{count: int, attendance: int}>  $periods
     * @return array{expected: float, dispersion: float, model: string, size: ?float, probability: float}
     */
    public function evaluate(int $observed, int $attendance, array $periods, float $overdispersionRatio): array
    {
        $totalAttendance = array_sum(array_column($periods, 'attendance'));
        $rate = array_sum(array_column($periods, 'count')) / max(1, $totalAttendance);
        $expected = $rate * $attendance;
        if ($expected <= 0 || count($periods) < 2) {
            throw new RuntimeException('A positive baseline and at least two comparable periods are required.');
        }
        $pearson = 0.0;
        foreach ($periods as $period) {
            $fitted = $rate * $period['attendance'];
            $pearson += ($period['count'] - $fitted) ** 2 / max(1e-12, $fitted);
        }
        $dispersion = $pearson / (count($periods) - 1);
        $size = $dispersion > $overdispersionRatio ? $expected / ($dispersion - 1) : null;

        return [
            'expected' => $expected, 'dispersion' => $dispersion,
            'model' => $size !== null ? 'Negative Binomial' : 'Poisson', 'size' => $size,
            'probability' => $size !== null ? $this->negativeBinomialTail($observed, $expected, $size) : $this->poissonTail($observed, $expected),
        ];
    }
}
