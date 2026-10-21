<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Scoring\Period;
use App\Scoring\RuleSet;
use App\Scoring\Scorer;
use App\Services\ProgramData;
use Illuminate\Http\Request;

class StandingsController extends Controller
{
    public function __invoke(Request $request, ProgramData $data)
    {
        $tier = $request->query('tier', 'monthly');
        abort_unless(isset(RuleSet::TIERS[$tier]), 404);

        $scorer = Scorer::current();
        $today = ProgramData::today();
        $period = Scorer::contestPeriod($tier, $today);
        $standings = $scorer->standings($data->histories(Period::for('year', $today)->start, $today), $period, asOf: $today);

        // Prior wins come from confirmed contests once contest closing is built.
        $pick = $scorer->selectWinner($standings, $tier, $period, []);

        return view('admin.standings', [
            'tier' => $tier,
            'period' => $period,
            'today' => $today,
            'standings' => $standings,
            'pick' => $pick,
            'cap' => $scorer->rules->contestOtherCap($period->kind),
        ]);
    }
}
