@extends('layouts.app', ['title' => 'My dashboard'])

@php
    $habits = [
        'meals' => ['Meals logged', 'Up to 3 points, one per meal', 'You log it'],
        'sleep' => ['Sleep 6+ hours', null, 'From your wearable'],
        'workout' => ['Workout 30+ minutes', null, 'From your wearable'],
        'steps' => ['10,000 steps', null, 'From your wearable'],
        'water' => ['64 oz of water', null, 'You log it'],
        'meal_plan' => ['Meal plan', 'You confirm you followed it', 'You log it'],
    ];
    $weekPct = min(100, round($week['points'] / $week['required'] * 100));
    $tierLabels = ['monthly' => 'This month', 'quarterly' => 'This quarter', 'annual' => 'This year'];
@endphp

@section('band')
    <h1>Hi, {{ strtok($user->name, ' ') }}</h1>
    <p>{{ \Carbon\Carbon::parse($today)->format('l, F j') }}</p>
    <div class="band-stats">
        <div><div class="stat-label">Today</div><div class="stat-value">{{ $todayScore->dailyPoints }} / {{ $maxPerDay }}</div></div>
        <div><div class="stat-label">This week</div><div class="stat-value">{{ $week['points'] }} / {{ $week['required'] }}</div></div>
        <div><div class="stat-label">Monthly rank</div><div class="stat-value">#{{ $standings['monthly']?->rank ?? '–' }}</div></div>
    </div>
@endsection

@section('content')
<div class="grid grid-2">
    <div class="card">
        <h2>Today's habits</h2>
        <ul class="habit-list">
            @foreach ($habits as $key => [$label, $hint, $source])
                @php($pts = $todayScore->categories[$key])
                <li @class(['met' => $pts > 0])>
                    <span class="mark">{{ $pts > 0 ? '✓' : '' }}</span>
                    <span>{{ $label }}<br><span class="src">{{ $hint ? $hint.' · ' : '' }}{{ $source }}</span></span>
                    <span class="pts">{{ $pts }}</span>
                </li>
            @endforeach
        </ul>
        <a class="btn btn-block mt" href="{{ route('entry') }}">Log meals and water</a>
    </div>

    <div class="stack">
        <div class="card">
            <h2>Weekly gift card</h2>
            <div class="stat-value">{{ $week['points'] }} <span class="stat-sub">of {{ $week['required'] }} points</span></div>
            <div @class(['progress', 'done' => $week['qualifies']])><span style="width: {{ $weekPct }}%"></span></div>
            <p class="hint mt">
                @if ($week['qualifies'])
                    You earned this week's ${{ $week['amount_usd'] }} gift card.
                @else
                    Earn all {{ $maxPerDay }} points every day this week ({{ $week['required'] - $week['points'] }} to go) for a $10 gift card.
                @endif
            </p>
            <div class="week-strip">
                @foreach ($weekDays as $d)
                    @php($p = isset($scores[$d]) ? $scores[$d]->dailyPoints : 0)
                    <div @class(['full' => $p >= $maxPerDay, 'today' => $d === $today])>
                        {{ \Carbon\Carbon::parse($d)->format('D') }}
                        <strong>{{ $d > $today ? '–' : $p }}</strong>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <h2>Contests</h2>
            <table>
                <thead><tr><th>Period</th><th class="num">Points</th><th class="num">Rank</th></tr></thead>
                <tbody>
                @foreach ($tierLabels as $tier => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="num">{{ $standings[$tier]?->total() ?? 0 }}</td>
                        <td class="num">#{{ $standings[$tier]?->rank ?? '–' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2 mt">
    <div class="card">
        <h2>{{ \Carbon\Carbon::parse($month->start)->format('F') }} leaderboard</h2>
        <table>
            <thead><tr><th>#</th><th>Name</th><th class="num">Points</th></tr></thead>
            <tbody>
            @foreach (array_slice($leaderboard, 0, 10) as $row)
                <tr @class(['me' => $row->id === $user->id])>
                    <td>{{ $row->rank }}</td>
                    <td>{{ $row->name }}</td>
                    <td class="num">{{ $row->total() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>My wearable</h2>
        @forelse ($devices as $device)
            <p>
                <strong>{{ $providerLabels[$device->provider] }}</strong>
                @if ($device->status === 'connected')
                    <span class="pill pill-green">Connected</span>
                @else
                    <span class="pill pill-red">Sync problem</span>
                @endif
                <br><span class="hint">Last synced {{ $device->last_sync_at?->diffForHumans() ?? 'never' }}</span>
            </p>
        @empty
            <p class="empty">No wearable connected. Connect an Apple Watch or Fitbit in the Fast Track Fitness phone app to start earning automatic points.</p>
        @endforelse
    </div>
</div>
@endsection
