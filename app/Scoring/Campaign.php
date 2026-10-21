<?php

namespace App\Scoring;

/**
 * A themed campaign, e.g. "steps are worth 2 points this month".
 * "replace" makes the category worth $points instead of its usual value;
 * "bonus" adds $points on top.
 */
final readonly class Campaign
{
    public function __construct(
        public string $id,
        public string $start,
        public string $end,
        public string $category,
        public string $mode,
        public int $points,
    ) {}
}
