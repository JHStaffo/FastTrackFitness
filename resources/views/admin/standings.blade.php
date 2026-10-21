@extends('layouts.app', ['title' => 'Standings'])

@php($tierLabels = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual'])

@section('band')
    <h1>{{ $tierLabels[$tier] }} contest standings</h1>
    <p>{{ \Carbon\Carbon::parse($period->start)->format('M j') }} to {{ \Carbon\Carbon::parse($period->end)->format('M j, Y') }}, as of {{ \Carbon\Carbon::parse($today)->format('M j') }}.
        Other-category points count up to {{ $cap ?? 'any number of' }} per {{ $period->kind }}.</p>
@endsection

@section('content')
<nav class="tabs">
    @foreach ($tierLabels as $key => $label)
        <a href="{{ route('admin.standings', ['tier' => $key]) }}" @class(['active' => $key === $tier])>{{ $label }}</a>
    @endforeach
</nav>

<div class="grid grid-4">
    <div class="card">
        <div class="stat-label">Proposed winner</div>
        <div class="stat-value">{{ $pick['winner']?->name ?? 'None yet' }}</div>
        @if ($pick['needs_admin_tie_break'])<span class="pill pill-red">Tied: needs a tie-break</span>@endif
    </div>
    <div class="card">
        <div class="stat-label">Alternates</div>
        <div class="stat-sub">{{ collect($pick['alternates'])->pluck('name')->join(', ') ?: 'None' }}</div>
    </div>
    <div class="card">
        <div class="stat-label">Participants</div>
        <div class="stat-value">{{ count($standings) }}</div>
    </div>
</div>

<div class="card mt">
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Rank</th><th>Name</th><th class="num">Daily</th><th class="num">Campaign</th>
                <th class="num">Other counted / earned</th><th class="num">Total</th>
                <th class="num">Days with points</th><th class="num">Perfect days</th><th class="num">% of days</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($standings as $s)
            <tr>
                <td>{{ $s->rank }}</td>
                <td>{{ $s->name }}</td>
                <td class="num">{{ $s->dailyPoints }}</td>
                <td class="num">{{ $s->campaignPoints }}</td>
                <td class="num">{{ $s->otherPointsCounted }} / {{ $s->otherPointsEarned }}</td>
                <td class="num"><strong>{{ $s->total() }}</strong></td>
                <td class="num">{{ $s->daysWithAnyPoint }}</td>
                <td class="num">{{ $s->perfectDays }}</td>
                <td class="num">{{ round($s->pctDaysWithAnyPoint() * 100) }}%</td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty">No one is enrolled yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <p class="hint mt">Ties are broken by days with any points, then perfect days, then the share of eligible days with points.</p>
</div>
@endsection
