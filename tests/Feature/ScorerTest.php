<?php

namespace Tests\Feature;

use App\Scoring\Campaign;
use App\Scoring\DayActivity;
use App\Scoring\EmployeeHistory;
use App\Scoring\OtherEvent;
use App\Scoring\Period;
use App\Scoring\RuleSet;
use App\Scoring\Scorer;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/** The rules engine against the spec's section 14 acceptance criteria. */
class ScorerTest extends TestCase
{
    private Scorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = Scorer::current();
    }

    private static function perfectDay(string $date, array $overrides = []): DayActivity
    {
        return new DayActivity(...array_merge([
            'date' => $date,
            'mealsLogged' => 3,
            'sleepMinutes' => 420,
            'workouts' => [["{$date}T12:00:00Z", "{$date}T12:45:00Z"]],
            'steps' => 12000,
            'waterOz' => 80,
            'mealPlanConfirmed' => true,
        ], $overrides));
    }

    /** @return list<DayActivity> */
    private static function days(string $start, int $count, callable $make): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = $make(CarbonImmutable::parse($start)->addDays($i)->toDateString(), $i);
        }

        return $out;
    }

    private static function employee(string $id, array $days, array $other = [], string $enrolled = '2027-01-01'): EmployeeHistory
    {
        return new EmployeeHistory($id, strtoupper($id), $enrolled, $days, $other);
    }

    public function test_weeks_run_sunday_to_saturday(): void
    {
        // 2027-01-06 is a Wednesday.
        $week = Period::for('week', '2027-01-06');
        $this->assertSame(['2027-01-03', '2027-01-09'], [$week->start, $week->end]);
        $this->assertSame('2027-01-03', Period::for('week', '2027-01-09')->start);
        $this->assertSame(['2028-02-01', '2028-02-29'], [Period::for('month', '2028-02-10')->start, Period::for('month', '2028-02-10')->end]);
        $this->assertSame('2027-Q2', Period::for('quarter', '2027-05-15')->key);
    }

    public function test_a_perfect_day_is_8_points_and_meals_cap_at_3(): void
    {
        $this->assertSame(8, $this->scorer->scoreDay(self::perfectDay('2027-01-04'))->dailyPoints);

        $s = $this->scorer->scoreDay(self::perfectDay('2027-01-04', ['mealsLogged' => 6]));
        $this->assertSame(3, $s->categories['meals']);
        $this->assertSame(8, $s->dailyPoints);
    }

    public function test_the_daily_maximum_holds_even_if_category_rules_allow_more(): void
    {
        $loose = new Scorer(RuleSet::current()->with(['daily' => ['max_meal_points' => 6]]));
        $this->assertSame(8, $loose->scoreDay(self::perfectDay('2027-01-04', ['mealsLogged' => 6]))->dailyPoints);
    }

    public function test_each_threshold(): void
    {
        $s = $this->scorer->scoreDay(self::perfectDay('2027-01-04', [
            'sleepMinutes' => 359, 'steps' => 9999, 'waterOz' => 63, 'mealPlanConfirmed' => false,
        ]));
        $this->assertSame(['meals' => 3, 'sleep' => 0, 'workout' => 1, 'steps' => 0, 'water' => 0, 'meal_plan' => 0], $s->categories);
        $this->assertSame(4, $s->dailyPoints);
    }

    public function test_the_same_workout_from_two_sources_counts_once(): void
    {
        $dup = [['2027-01-04T12:00:00Z', '2027-01-04T12:20:00Z'], ['2027-01-04T12:00:00Z', '2027-01-04T12:20:00Z']];
        $this->assertSame(20, Scorer::mergedMinutes($dup));
        $this->assertSame(0, $this->scorer->scoreDay(self::perfectDay('2027-01-04', ['workouts' => $dup]))->categories['workout']);

        $two = [['2027-01-04T08:00:00Z', '2027-01-04T08:15:00Z'], ['2027-01-04T18:00:00Z', '2027-01-04T18:15:00Z']];
        $this->assertSame(1, $this->scorer->scoreDay(self::perfectDay('2027-01-04', ['workouts' => $two]))->categories['workout']);
    }

    public function test_campaigns_replace_or_add_without_changing_standard_points(): void
    {
        $stepMonth = new Campaign('steps-feb', '2027-02-01', '2027-02-28', 'steps', 'replace', 2);
        $s = $this->scorer->scoreDay(self::perfectDay('2027-02-10'), [$stepMonth]);
        $this->assertSame(8, $s->dailyPoints);
        $this->assertSame(1, $s->campaignPoints);

        // Only one campaign per category per day.
        $bonus = new Campaign('steps-bonus', '2027-02-01', '2027-02-28', 'steps', 'bonus', 5);
        $this->assertCount(1, $this->scorer->scoreDay(self::perfectDay('2027-02-10'), [$stepMonth, $bonus])->appliedCampaignIds);

        // Nothing outside its dates or when the goal was missed.
        $this->assertSame(0, $this->scorer->scoreDay(self::perfectDay('2027-03-01'), [$stepMonth])->campaignPoints);
        $this->assertSame(0, $this->scorer->scoreDay(self::perfectDay('2027-02-10', ['steps' => 100]), [$stepMonth])->campaignPoints);
    }

    public function test_the_weekly_reward_needs_56_points_inside_one_sunday_to_saturday_week(): void
    {
        $week = array_map(fn ($d) => $this->scorer->scoreDay($d), self::days('2027-01-03', 7, fn ($d) => self::perfectDay($d)));
        $r = $this->scorer->weeklyReward($week, '2027-01-05');
        $this->assertSame([56, true, 10], [$r['points'], $r['qualifies'], $r['amount_usd']]);

        // Monday to Sunday: seven perfect days, split across two weeks.
        $split = array_map(fn ($d) => $this->scorer->scoreDay($d), self::days('2027-01-04', 7, fn ($d) => self::perfectDay($d)));
        $r = $this->scorer->weeklyReward($split, '2027-01-04');
        $this->assertSame([48, false], [$r['points'], $r['qualifies']]);
    }

    public function test_other_and_campaign_points_cannot_replace_missing_daily_points(): void
    {
        $bonus = new Campaign('c', '2027-01-01', '2027-01-31', 'steps', 'bonus', 5);
        $days = self::days('2027-01-03', 7, fn ($d, $i) => self::perfectDay($d, $i === 0 ? ['waterOz' => 0] : []));
        $scores = array_map(fn ($d) => $this->scorer->scoreDay($d, [$bonus]), $days);
        $r = $this->scorer->weeklyReward($scores, '2027-01-03');
        $this->assertSame([55, false], [$r['points'], $r['qualifies']]);
    }

    public function test_other_category_limits(): void
    {
        $events = [
            new OtherEvent('d1', '2027-02-01', 'dentist', true),
            new OtherEvent('d2', '2027-08-01', 'dentist', true),
            new OtherEvent('d3', '2027-11-01', 'dentist', true),
            new OtherEvent('s1', '2027-01-04', 'self_care', true),
            new OtherEvent('s2', '2027-01-06', 'self_care', true),
            new OtherEvent('s3', '2027-01-10', 'self_care', true),
            new OtherEvent('p', '2027-03-01', 'primary_care'), // note not approved yet
            new OtherEvent('w', '2027-03-01', 'weigh_in'), // no document needed
        ];
        $points = [];
        foreach ($this->scorer->scoreOtherEvents($events) as $a) {
            $points[$a['event']->id] = $a['points'];
        }
        $this->assertEquals(['d1' => 185, 'd2' => 185, 'd3' => 0, 's1' => 10, 's2' => 0, 's3' => 10, 'p' => 0, 'w' => 10], $points);
    }

    public function test_contest_caps_other_points(): void
    {
        $jan = Scorer::contestPeriod('monthly', '2027-01-15');
        $appointments = self::employee('appt', [], [
            new OtherEvent('pc', '2027-01-05', 'primary_care', true),
            new OtherEvent('dn', '2027-01-06', 'dentist', true),
            new OtherEvent('ey', '2027-01-07', 'eye_doctor', true),
        ]);
        $habits = self::employee('habits', self::days('2027-01-01', 31, fn ($d) => self::perfectDay($d)));

        [$first, $second] = $this->scorer->standings([$appointments, $habits], $jan);
        $this->assertSame(['habits', 248], [$first->id, $first->total()]);
        $this->assertSame([650, 60, 60], [$second->otherPointsEarned, $second->otherPointsCounted, $second->total()]);

        $uncapped = new Scorer(RuleSet::current()->with(['contest_other_caps' => ['month' => null]]));
        $this->assertSame(650, $uncapped->standings([$appointments], $jan)[0]->total());
    }

    public function test_tie_breakers_in_spec_order(): void
    {
        $jan = Scorer::contestPeriod('monthly', '2027-01-15');
        $empty = fn ($d, $o = []) => new DayActivity($d, ...$o);

        // 1. Both 40 points: B scored on 10 days, A on 5.
        $a = self::employee('a', self::days('2027-01-01', 5, fn ($d) => self::perfectDay($d)));
        $b = self::employee('b', self::days('2027-01-01', 10, fn ($d) => $empty($d, ['mealsLogged' => 3, 'steps' => 10000])));
        $this->assertSame(['b', 'a'], array_map(fn ($s) => $s->id, $this->scorer->standings([$a, $b], $jan)));

        // 2. Both 28 points over 6 days: C has 2 perfect days, D has none.
        $c = self::employee('c', [
            ...self::days('2027-01-01', 2, fn ($d) => self::perfectDay($d)),
            ...self::days('2027-01-03', 4, fn ($d) => $empty($d, ['mealsLogged' => 3])),
        ]);
        $d = self::employee('d', self::days('2027-01-01', 6, fn ($dd, $i) => $empty($dd, ['mealsLogged' => 3, 'steps' => 10000, 'waterOz' => $i < 4 ? 64 : 0])));
        $s = $this->scorer->standings([$d, $c], $jan);
        $this->assertSame([['c', 28], ['d', 28]], array_map(fn ($x) => [$x->id, $x->total()], $s));

        // 3. Same everything; a late joiner has the higher share of eligible days.
        $early = self::employee('early', self::days('2027-01-01', 10, fn ($d) => self::perfectDay($d)));
        $late = self::employee('late', self::days('2027-01-22', 10, fn ($d) => self::perfectDay($d)), enrolled: '2027-01-22');
        $s = $this->scorer->standings([$early, $late], $jan);
        $this->assertSame('late', $s[0]->id);
        $this->assertSame(1.0, $s[0]->pctDaysWithAnyPoint());
    }

    public function test_an_unbreakable_tie_shares_a_rank_and_needs_an_admin(): void
    {
        $jan = Scorer::contestPeriod('monthly', '2027-01-15');
        $s = $this->scorer->standings([
            self::employee('a', self::days('2027-01-01', 3, fn ($d) => self::perfectDay($d))),
            self::employee('b', self::days('2027-01-01', 3, fn ($d) => self::perfectDay($d))),
        ], $jan);
        $this->assertSame([1, 1], array_map(fn ($x) => $x->rank, $s));
        $this->assertTrue($this->scorer->selectWinner($s, 'monthly', $jan, [])['needs_admin_tie_break']);
    }

    public function test_mid_period_standings_only_count_days_so_far(): void
    {
        $jan = Scorer::contestPeriod('monthly', '2027-01-15');
        $a = self::employee('a', self::days('2027-01-01', 31, fn ($d) => self::perfectDay($d)));
        $this->assertSame(80, $this->scorer->standings([$a], $jan, asOf: '2027-01-10')[0]->total());
    }

    /** Five people, A highest. */
    private function januaryStandings(): array
    {
        $people = [];
        foreach (['a', 'b', 'c', 'd', 'e'] as $i => $id) {
            $people[] = self::employee($id, self::days('2027-01-01', 31 - $i, fn ($d) => self::perfectDay($d)));
        }

        return $this->scorer->standings($people, Scorer::contestPeriod('monthly', '2027-01-15'));
    }

    public function test_winner_and_alternates(): void
    {
        $pick = $this->scorer->selectWinner($this->januaryStandings(), 'monthly', Scorer::contestPeriod('monthly', '2027-01-15'), []);
        $this->assertSame('a', $pick['winner']->id);
        $this->assertSame(['b', 'c', 'd'], array_map(fn ($s) => $s->id, $pick['alternates']));
    }

    public function test_monthly_prize_at_most_once_per_quarter(): void
    {
        $wins = [['id' => 'a', 'tier' => 'monthly', 'period_date' => '2027-01-15']];
        $feb = $this->scorer->selectWinner($this->januaryStandings(), 'monthly', Scorer::contestPeriod('monthly', '2027-02-15'), $wins);
        $this->assertSame('b', $feb['winner']->id);
        $this->assertSame('a', $feb['skipped'][0]['id']);

        $april = $this->scorer->selectWinner($this->januaryStandings(), 'monthly', Scorer::contestPeriod('monthly', '2027-04-15'), $wins);
        $this->assertSame('a', $april['winner']->id);
    }

    public function test_quarterly_prize_at_most_once_per_year(): void
    {
        $wins = [['id' => 'a', 'tier' => 'quarterly', 'period_date' => '2027-02-01']];
        $pick = $this->scorer->selectWinner($this->januaryStandings(), 'quarterly', Scorer::contestPeriod('quarterly', '2027-04-15'), $wins);
        $this->assertSame('b', $pick['winner']->id);
    }

    public function test_one_tier_per_year_blocks_a_monthly_winner_from_the_annual_prize(): void
    {
        $wins = [['id' => 'a', 'tier' => 'monthly', 'period_date' => '2027-01-15']];
        $pick = $this->scorer->selectWinner($this->januaryStandings(), 'annual', Scorer::contestPeriod('annual', '2027-06-01'), $wins);
        $this->assertSame('b', $pick['winner']->id);
    }

    public function test_up_tier_allowed_policy(): void
    {
        $upTier = new Scorer(RuleSet::current()->with(['stacking' => 'up_tier_allowed']));
        $standings = $this->januaryStandings();

        $monthlyWin = [['id' => 'a', 'tier' => 'monthly', 'period_date' => '2027-01-15']];
        $this->assertSame('a', $upTier->selectWinner($standings, 'annual', Scorer::contestPeriod('annual', '2027-06-01'), $monthlyWin)['winner']->id);

        $quarterlyWin = [['id' => 'a', 'tier' => 'quarterly', 'period_date' => '2027-03-31']];
        $this->assertSame('b', $upTier->selectWinner($standings, 'monthly', Scorer::contestPeriod('monthly', '2027-05-15'), $quarterlyWin)['winner']->id);
    }

    public function test_employees_flagged_ineligible_are_skipped(): void
    {
        $pick = $this->scorer->selectWinner($this->januaryStandings(), 'monthly', Scorer::contestPeriod('monthly', '2027-01-15'), [], ['a' => 'No wearable connected']);
        $this->assertSame('b', $pick['winner']->id);
        $this->assertSame([['id' => 'a', 'reason' => 'No wearable connected']], $pick['skipped']);
    }
}
