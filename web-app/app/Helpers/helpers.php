<?php

function calculatePercentage(int|float $numerator, int|float $denominator, ?int $decimalPlaces = null)
{
    if ($denominator == 0) {
        return 0;
    }

    if (empty($decimalPlaces)) {
        return ($numerator / $denominator) * 100;
    }

    return round(($numerator / $denominator) * 100, $decimalPlaces);
}

function calculateMean(array $values, ?int $decimalPlaces = null)
{
    $total = 0;
    $count = 0;

    foreach ($values as $value) {
        if (empty($value)) {
            continue;
        }

        if (! is_int($value) && ! is_float($value)) {
            throw new Exception('Invalid value in mean calculation');
        }

        $total += $value;
        $count++;
    }

    if ($count === 0) {
        return 0;
    }

    $mean = $total / $count;

    if (empty($decimalPlaces)) {
        return $mean;
    }

    return round($mean, $decimalPlaces);
}
