<?php

namespace App\Scoring;

/**
 * One employee's inputs for one local day, after imports are de-duplicated.
 * Sleep is attributed to the day the employee woke up, so night-shift sleep
 * (for example 09:00 to 15:00) still counts.
 */
final readonly class DayActivity
{
    /** @param  list<array{0: string, 1: string}>  $workouts  [start, end] ISO timestamps */
    public function __construct(
        public string $date,
        public int $mealsLogged = 0,
        public int $sleepMinutes = 0,
        public array $workouts = [],
        public int $steps = 0,
        public float $waterOz = 0,
        public bool $mealPlanConfirmed = false,
    ) {}
}
