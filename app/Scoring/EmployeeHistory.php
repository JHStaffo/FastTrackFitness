<?php

namespace App\Scoring;

final readonly class EmployeeHistory
{
    /**
     * @param  string  $enrolledFrom  first day the employee could score
     * @param  list<DayActivity>  $days
     * @param  list<OtherEvent>  $otherEvents  full history, so yearly category limits see earlier events
     */
    public function __construct(
        public int|string $id,
        public string $name,
        public string $enrolledFrom,
        public array $days,
        public array $otherEvents = [],
    ) {}
}
