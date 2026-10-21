<?php

namespace App\Scoring;

final readonly class DayScore
{
    /**
     * @param  array<string, int>  $categories  standard points per category, before campaigns
     * @param  int  $dailyPoints  standard points capped at the daily maximum; the weekly reward uses only these
     * @param  int  $campaignPoints  count toward contests, never toward the weekly reward
     * @param  list<string>  $appliedCampaignIds
     */
    public function __construct(
        public string $date,
        public array $categories,
        public int $dailyPoints,
        public int $campaignPoints,
        public array $appliedCampaignIds,
    ) {}
}
