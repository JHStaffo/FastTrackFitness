@extends('layouts.app', ['title' => 'Log today'])

@section('band')
    <h1>Log today</h1>
    <p>{{ \Carbon\Carbon::parse($today)->format('l, F j') }}. Steps, sleep and workouts come from your wearable.</p>
@endsection

@section('content')
<form class="card" method="post" action="{{ route('entry') }}" style="max-width: 520px">
    @csrf
    <div class="field">
        <label for="meals_logged">Meals logged in your food app</label>
        <input id="meals_logged" type="number" name="meals_logged" min="0" max="6" value="{{ old('meals_logged', $entry->meals_logged ?? 0) }}" required>
        <div class="hint">One point per meal, up to 3.</div>
    </div>
    <div class="field">
        <label for="water_oz">Water (oz)</label>
        <input id="water_oz" type="number" name="water_oz" min="0" max="400" step="1" value="{{ old('water_oz', (int) ($entry->water_oz ?? 0)) }}" required>
        <div class="hint">64 oz or more earns a point.</div>
    </div>
    <div class="field">
        <label class="check"><input type="checkbox" name="meal_plan_confirmed" value="1" @checked(old('meal_plan_confirmed', $entry->meal_plan_confirmed))> I followed my meal plan today</label>
    </div>
    <button class="btn btn-block" type="submit">Save</button>
</form>
@endsection
