<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Scoring\DayActivity;
use App\Scoring\Period;
use App\Scoring\Scorer;
use App\Services\ProgramData;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ProgramData $data)
    {
        $user = $request->user();
        if (! $user->participant) {
            return view('not-enrolled');
        }

        $scorer = Scorer::current();
        $today = ProgramData::today();
        $year = Period::for('year', $today);
        $month = Scorer::contestPeriod('monthly', $today);

        $everyone = $data->histories($year->start, $today);
        $me = collect($everyone)->firstWhere('id', $user->id);
        $scores = collect($me?->days ?? [])->map(fn ($d) => $scorer->scoreDay($d))->keyBy('date');
        $todayScore = $scores[$today] ?? $scorer->scoreDay(new DayActivity($today));
        $week = $scorer->weeklyReward($scores->values()->all(), $today);

        $standings = [];
        foreach (['monthly', 'quarterly', 'annual'] as $tier) {
            $rows = $scorer->standings($everyone, Scorer::contestPeriod($tier, $today), asOf: $today);
            $standings[$tier] = collect($rows)->firstWhere('id', $user->id);
        }

        return view('dashboard', [
            'user' => $user,
            'today' => $today,
            'todayScore' => $todayScore,
            'maxPerDay' => $scorer->rules->daily('max_points_per_day'),
            'week' => $week,
            'weekDays' => Period::datesBetween($week['week']->start, $week['week']->end),
            'scores' => $scores,
            'standings' => $standings,
            'leaderboard' => $scorer->standings($everyone, $month, asOf: $today),
            'month' => $month,
            'devices' => $user->devices()->whereNull('disconnected_at')->get(),
            'providerLabels' => Device::PROVIDER_LABELS,
        ]);
    }
}
