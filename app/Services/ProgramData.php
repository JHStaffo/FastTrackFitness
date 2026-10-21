<?php

namespace App\Services;

use App\Models\ActivityRecord;
use App\Models\ManualEntry;
use App\Models\Participant;
use App\Models\Submission;
use App\Models\User;
use App\Scoring\DayActivity;
use App\Scoring\EmployeeHistory;
use App\Scoring\OtherEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Turns stored records into the inputs the Scorer works on. */
class ProgramData
{
    public static function today(): string
    {
        return CarbonImmutable::now(config('wellness.timezone'))->toDateString();
    }

    /**
     * Enrolled, active employees with their days between $from and $to.
     *
     * @param  list<int>|null  $userIds  limit to these people
     * @return list<EmployeeHistory>
     */
    public function histories(string $from, string $to, ?array $userIds = null): array
    {
        $participants = Participant::query()
            ->when($userIds, fn ($q) => $q->whereIn('user_id', $userIds))
            ->get()->keyBy('user_id');
        $users = User::whereIn('id', $participants->keys())->where('active', true)->get();
        $ids = $users->pluck('id')->all();

        $records = ActivityRecord::whereIn('user_id', $ids)
            ->whereBetween('scoring_date', [$from, $to])
            ->where('user_entered', false)
            ->get()->groupBy('user_id');
        $entries = ManualEntry::whereIn('user_id', $ids)
            ->whereBetween('scoring_date', [$from, $to])
            ->get()->groupBy('user_id');
        // Whole years, so yearly visit limits see earlier events.
        $submissions = Submission::whereIn('user_id', $ids)
            ->whereBetween('event_date', [substr($from, 0, 4).'-01-01', $to])
            ->where('status', '!=', 'rejected')
            ->get()->groupBy('user_id');

        return $users->map(fn (User $u) => new EmployeeHistory(
            id: $u->id,
            name: $u->name,
            enrolledFrom: $participants[$u->id]->enrolled_from->toDateString(),
            days: $this->days($records[$u->id] ?? collect(), $entries[$u->id] ?? collect()),
            otherEvents: ($submissions[$u->id] ?? collect())->map(fn (Submission $s) => new OtherEvent(
                (string) $s->id, $s->event_date->toDateString(), $s->category, $s->status === 'approved',
            ))->values()->all(),
        ))->values()->all();
    }

    /** @return list<DayActivity> */
    private function days(Collection $records, Collection $entries): array
    {
        $dates = $records->map(fn ($r) => $r->scoring_date->toDateString())
            ->merge($entries->map(fn ($e) => $e->scoring_date->toDateString()))
            ->unique()->sort()->values();
        $recordsByDate = $records->groupBy(fn ($r) => $r->scoring_date->toDateString());
        $entriesByDate = $entries->keyBy(fn ($e) => $e->scoring_date->toDateString());

        return $dates->map(function (string $date) use ($recordsByDate, $entriesByDate) {
            $day = $recordsByDate[$date] ?? collect();
            $entry = $entriesByDate[$date] ?? null;
            $sum = fn (string $type) => $day->where('type', $type)->sum('value');

            return new DayActivity(
                date: $date,
                mealsLogged: $entry?->meals_logged ?? 0,
                sleepMinutes: (int) $sum('sleep'),
                workouts: $day->where('type', 'workout')
                    ->map(fn ($r) => [$r->start_at->toIso8601String(), $r->end_at->toIso8601String()])->values()->all(),
                steps: (int) $sum('steps'),
                // Water can be typed here or synced from a watch app; take the larger so it isn't counted twice.
                waterOz: max((float) $sum('water'), (float) ($entry?->water_oz ?? 0)),
                mealPlanConfirmed: (bool) $entry?->meal_plan_confirmed,
            );
        })->all();
    }
}
