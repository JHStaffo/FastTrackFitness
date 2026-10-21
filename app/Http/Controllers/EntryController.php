<?php

namespace App\Http\Controllers;

use App\Models\ManualEntry;
use App\Services\ProgramData;
use Illuminate\Http\Request;

/** Today's manual check-in: meals logged, water and the meal-plan confirmation. */
class EntryController extends Controller
{
    public function edit(Request $request)
    {
        abort_unless($request->user()->participant, 403);
        $today = ProgramData::today();

        return view('entry', [
            'today' => $today,
            'entry' => ManualEntry::firstOrNew(['user_id' => $request->user()->id, 'scoring_date' => $today]),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->participant, 403);
        $data = $request->validate([
            'meals_logged' => 'required|integer|min:0|max:6',
            'water_oz' => 'required|numeric|min:0|max:400',
            'meal_plan_confirmed' => 'nullable|boolean',
        ]);

        ManualEntry::updateOrCreate(
            ['user_id' => $request->user()->id, 'scoring_date' => ProgramData::today()],
            [...$data, 'meal_plan_confirmed' => $request->boolean('meal_plan_confirmed')],
        );

        return redirect()->route('home')->with('status', 'Saved today\'s check-in.');
    }
}
