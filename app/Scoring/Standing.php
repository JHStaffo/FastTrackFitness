<?php

namespace App\Scoring;

final class Standing
{
    public int $rank = 0;

    /**
     * @param  int  $otherPointsEarned  other-category points in the period, before the contest cap
     * @param  int  $otherPointsCounted  after the contest cap (spec 3C)
     */
    public function __construct(
        public readonly int|string $id,
        public readonly string $name,
        public readonly int $dailyPoints,
        public readonly int $campaignPoints,
        public readonly int $otherPointsEarned,
        public readonly int $otherPointsCounted,
        public readonly int $daysWithAnyPoint,
        public readonly int $perfectDays,
        public readonly int $eligibleDays,
    ) {}

    public function total(): int
    {
        return $this->dailyPoints + $this->campaignPoints + $this->otherPointsCounted;
    }

    public function pctDaysWithAnyPoint(): float
    {
        return $this->eligibleDays === 0 ? 0.0 : $this->daysWithAnyPoint / $this->eligibleDays;
    }
}
