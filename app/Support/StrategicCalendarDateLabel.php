<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

final class StrategicCalendarDateLabel
{
    public static function format(?CarbonInterface $startsOn, ?CarbonInterface $endsOn): string
    {
        if ($startsOn === null) {
            return '—';
        }

        $startLabel = $startsOn->format('d/m/Y');

        if ($endsOn === null || $endsOn->toDateString() === $startsOn->toDateString()) {
            return $startLabel;
        }

        return $startLabel.' a '.$endsOn->format('d/m/Y');
    }
}
