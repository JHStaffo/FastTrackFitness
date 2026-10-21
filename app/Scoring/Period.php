<?php

namespace App\Scoring;

use Carbon\CarbonImmutable;

/**
 * A scoring period. Dates are local "Y-m-d" strings in the program time zone,
 * so string comparison orders them correctly.
 */
final readonly class Period
{
    public function __construct(
        public string $kind,
        public string $key,
        public string $start,
        public string $end,
    ) {}

    /** @param  'week'|'month'|'quarter'|'year'  $kind */
    public static function for(string $kind, string $date): self
    {
        $d = CarbonImmutable::parse($date);
        // Scoring weeks run Sunday through Saturday.
        $sunday = $d->subDays($d->dayOfWeek);

        return match ($kind) {
            'week' => new self('week', 'W'.$sunday->toDateString(), $sunday->toDateString(), $sunday->addDays(6)->toDateString()),
            'month' => new self('month', $d->format('Y-m'), $d->startOfMonth()->toDateString(), $d->endOfMonth()->toDateString()),
            'quarter' => new self('quarter', $d->year.'-Q'.$d->quarter, $d->startOfQuarter()->toDateString(), $d->endOfQuarter()->toDateString()),
            'year' => new self('year', (string) $d->year, $d->startOfYear()->toDateString(), $d->endOfYear()->toDateString()),
        };
    }

    public function contains(string $date): bool
    {
        return $date >= $this->start && $date <= $this->end;
    }

    /** @return list<string> */
    public static function datesBetween(string $start, string $end): array
    {
        $dates = [];
        for ($d = CarbonImmutable::parse($start); $d->toDateString() <= $end; $d = $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        return $dates;
    }
}
