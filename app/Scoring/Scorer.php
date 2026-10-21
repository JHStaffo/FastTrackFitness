<?php

namespace App\Scoring;

use Carbon\CarbonImmutable;

/**
 * The program rules engine: daily points, the weekly reward, other-category
 * limits, contest standings, tie-breakers and winner selection. Pure logic
 * with no database access, so every rule is covered by unit tests.
 */
class Scorer
{
    public function __construct(public readonly RuleSet $rules) {}

    public static function current(): self
    {
        return new self(RuleSet::current());
    }

    // ---- Daily habits -------------------------------------------------

    /** @param  list<Campaign>  $campaigns */
    public function scoreDay(DayActivity $day, array $campaigns = []): DayScore
    {
        $r = fn (string $k) => $this->rules->daily($k);

        $categories = [
            'meals' => min(max(0, $day->mealsLogged) * $r('points_per_meal'), $r('max_meal_points')),
            'sleep' => (int) ($day->sleepMinutes >= $r('sleep_min_minutes')),
            'workout' => (int) (self::mergedMinutes($day->workouts) >= $r('workout_min_minutes')),
            'steps' => (int) ($day->steps >= $r('steps_min')),
            'water' => (int) ($day->waterOz >= $r('water_min_oz')),
            'meal_plan' => (int) $day->mealPlanConfirmed,
        ];
        $dailyPoints = min(array_sum($categories), $r('max_points_per_day'));

        // At most one campaign per category per day, so overlapping campaigns can't double count.
        $active = array_values(array_filter($campaigns, fn (Campaign $c) => $day->date >= $c->start && $day->date <= $c->end));
        usort($active, fn (Campaign $a, Campaign $b) => [$a->start, $a->id] <=> [$b->start, $b->id]);

        $campaignPoints = 0;
        $applied = [];
        $used = [];
        foreach ($active as $c) {
            if (isset($used[$c->category]) || ($categories[$c->category] ?? 0) === 0) {
                continue;
            }
            $used[$c->category] = true;
            $extra = $c->mode === 'replace' ? max(0, $c->points - $categories[$c->category]) : $c->points;
            if ($extra > 0) {
                $campaignPoints += $extra;
                $applied[] = $c->id;
            }
        }

        return new DayScore($day->date, $categories, $dailyPoints, $campaignPoints, $applied);
    }

    /**
     * Total minutes covered by the intervals, counting overlapping time once,
     * so the same workout recorded by two sources isn't double counted.
     *
     * @param  list<array{0: string, 1: string}>  $intervals
     */
    public static function mergedMinutes(array $intervals): int
    {
        $spans = [];
        foreach ($intervals as [$start, $end]) {
            $s = CarbonImmutable::parse($start)->getTimestamp();
            $e = CarbonImmutable::parse($end)->getTimestamp();
            if ($e > $s) {
                $spans[] = [$s, $e];
            }
        }
        sort($spans);

        $total = 0;
        $cur = null;
        foreach ($spans as [$s, $e]) {
            if ($cur === null || $s > $cur[1]) {
                if ($cur !== null) {
                    $total += $cur[1] - $cur[0];
                }
                $cur = [$s, $e];
            } else {
                $cur[1] = max($cur[1], $e);
            }
        }
        if ($cur !== null) {
            $total += $cur[1] - $cur[0];
        }

        return intdiv($total, 60);
    }

    // ---- Weekly reward ------------------------------------------------

    /**
     * The weekly gift-card check for the Sunday-Saturday week containing $date.
     * Only standard daily points count (spec 4).
     *
     * @param  list<DayScore>  $scores
     * @return array{week: Period, points: int, required: int, qualifies: bool, amount_usd: int}
     */
    public function weeklyReward(array $scores, string $date): array
    {
        $week = Period::for('week', $date);
        $points = array_sum(array_map(
            fn (DayScore $s) => $week->contains($s->date) ? $s->dailyPoints : 0,
            $scores,
        ));
        $required = $this->rules->weeklyPointsRequired();
        $qualifies = $points >= $required;

        return [
            'week' => $week,
            'points' => $points,
            'required' => $required,
            'qualifies' => $qualifies,
            'amount_usd' => $qualifies ? $this->rules->weeklyAmountUsd() : 0,
        ];
    }

    // ---- Other wellness activities ------------------------------------

    /**
     * Awards other-category points in date order, enforcing each category's
     * limit (for example dentist: 185 per visit, at most 370 per year).
     *
     * @param  list<OtherEvent>  $events
     * @return list<array{event: OtherEvent, points: int, limited_by: ?string}>
     */
    public function scoreOtherEvents(array $events): array
    {
        usort($events, fn (OtherEvent $a, OtherEvent $b) => [$a->date, $a->id] <=> [$b->date, $b->id]);
        $used = [];

        return array_map(function (OtherEvent $e) use (&$used) {
            $rule = $this->rules->other($e->category);
            if ($rule['requires_document'] && ! $e->documentApproved) {
                return ['event' => $e, 'points' => 0, 'limited_by' => 'awaiting_document'];
            }
            $bucket = $e->category.':'.Period::for($rule['limit_period'], $e->date)->key;
            $already = $used[$bucket] ?? 0;
            $points = max(0, min($rule['points_per_event'], $rule['max_points_per_period'] - $already));
            $used[$bucket] = $already + $points;

            return ['event' => $e, 'points' => $points, 'limited_by' => $points < $rule['points_per_event'] ? 'category_limit' : null];
        }, $events);
    }

    // ---- Contests -----------------------------------------------------

    public static function contestPeriod(string $tier, string $date): Period
    {
        return Period::for(RuleSet::TIERS[$tier], $date);
    }

    /**
     * Ranks employees for a contest period. $asOf limits scoring to days that
     * have happened, so standings can be shown mid-period.
     *
     * @param  list<EmployeeHistory>  $employees
     * @param  list<Campaign>  $campaigns
     * @return list<Standing>
     */
    public function standings(array $employees, Period $period, array $campaigns = [], ?string $asOf = null): array
    {
        $lastDay = $asOf !== null && $asOf < $period->end ? $asOf : $period->end;
        $window = new Period($period->kind, $period->key, $period->start, $lastDay);
        $cap = $this->rules->contestOtherCap($period->kind);
        $max = $this->rules->daily('max_points_per_day');

        $rows = array_map(function (EmployeeHistory $emp) use ($window, $campaigns, $cap, $max) {
            $scores = [];
            foreach ($emp->days as $day) {
                if ($window->contains($day->date)) {
                    $scores[] = $this->scoreDay($day, $campaigns);
                }
            }
            $otherEarned = 0;
            foreach ($this->scoreOtherEvents($emp->otherEvents) as $award) {
                if ($window->contains($award['event']->date)) {
                    $otherEarned += $award['points'];
                }
            }

            $firstDay = max($emp->enrolledFrom, $window->start);
            $eligible = $firstDay > $window->end ? 0 : count(Period::datesBetween($firstDay, $window->end));
            $counted = array_filter($scores, fn (DayScore $s) => $s->date >= $firstDay);

            return new Standing(
                id: $emp->id,
                name: $emp->name,
                dailyPoints: array_sum(array_map(fn ($s) => $s->dailyPoints, $scores)),
                campaignPoints: array_sum(array_map(fn ($s) => $s->campaignPoints, $scores)),
                otherPointsEarned: $otherEarned,
                otherPointsCounted: $cap === null ? $otherEarned : min($otherEarned, $cap),
                daysWithAnyPoint: count(array_filter($counted, fn ($s) => $s->dailyPoints > 0)),
                perfectDays: count(array_filter($counted, fn ($s) => $s->dailyPoints >= $max)),
                eligibleDays: $eligible,
            );
        }, $employees);

        usort($rows, [self::class, 'compare']);

        // Employees still tied after every tie-breaker share a rank; an admin resolves it (spec 5).
        foreach ($rows as $i => $row) {
            $row->rank = $i > 0 && self::compare($rows[$i - 1], $row) === 0 ? $rows[$i - 1]->rank : $i + 1;
        }

        return $rows;
    }

    /** Points first, then the spec's tie-breakers in order. */
    public static function compare(Standing $a, Standing $b): int
    {
        return [$b->total(), $b->daysWithAnyPoint, $b->perfectDays, $b->pctDaysWithAnyPoint()]
            <=> [$a->total(), $a->daysWithAnyPoint, $a->perfectDays, $a->pctDaysWithAnyPoint()];
    }

    /**
     * Applies prize restrictions and picks the winner plus the next-highest
     * eligible alternates. $otherIneligible carries reasons from outside
     * scoring, such as an inactive employee or no connected wearable.
     *
     * @param  list<Standing>  $standings
     * @param  list<array{id: int|string, tier: string, period_date: string}>  $priorWins
     * @param  array<int|string, string>  $otherIneligible
     * @return array{winner: ?Standing, alternates: list<Standing>, skipped: list<array{id: int|string, reason: string}>, needs_admin_tie_break: bool}
     */
    public function selectWinner(array $standings, string $tier, Period $period, array $priorWins, array $otherIneligible = [], int $alternates = 3): array
    {
        $rank = array_flip(array_keys(RuleSet::TIERS));
        $year = substr($period->start, 0, 4);
        $quarter = Period::for('quarter', $period->start)->key;

        $reasonFor = function (Standing $s) use ($priorWins, $otherIneligible, $tier, $rank, $year, $quarter): ?string {
            if (isset($otherIneligible[$s->id])) {
                return $otherIneligible[$s->id];
            }
            if ($s->total() <= 0) {
                return 'No points in this period';
            }
            foreach ($priorWins as $w) {
                if ($w['id'] !== $s->id || substr($w['period_date'], 0, 4) !== $year) {
                    continue;
                }
                if ($w['tier'] === $tier) {
                    if ($tier === 'monthly' && Period::for('quarter', $w['period_date'])->key === $quarter) {
                        return 'Already won a monthly prize this quarter';
                    }
                    if ($tier !== 'monthly') {
                        return "Already won the {$tier} prize this year";
                    }

                    continue;
                }
                if ($this->rules->stacking() === 'one_tier_per_year') {
                    return "Already won a {$w['tier']} prize this year";
                }
                if ($rank[$w['tier']] > $rank[$tier]) {
                    return "Already won a higher ({$w['tier']}) prize this year";
                }
            }

            return null;
        };

        $eligible = [];
        $skipped = [];
        foreach ($standings as $s) {
            $reason = $reasonFor($s);
            if ($reason === null) {
                $eligible[] = $s;
            } else {
                $skipped[] = ['id' => $s->id, 'reason' => $reason];
            }
        }

        $winner = $eligible[0] ?? null;
        $next = $eligible[1] ?? null;

        return [
            'winner' => $winner,
            'alternates' => array_slice($eligible, 1, $alternates),
            'skipped' => $skipped,
            'needs_admin_tie_break' => $winner && $next && self::compare($winner, $next) === 0,
        ];
    }
}
