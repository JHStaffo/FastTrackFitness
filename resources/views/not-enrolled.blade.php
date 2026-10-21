@extends('layouts.app', ['title' => 'Not enrolled yet'])

@section('band')
    <h1>Welcome, {{ strtok(auth()->user()->name, ' ') }}</h1>
    <p>You're signed in, but you aren't enrolled in the wellness program yet.</p>
@endsection

@section('content')
<div class="card">
    <p>Ask a wellness administrator to enroll you. Once you're enrolled, you'll connect your Apple Watch or Fitbit and start earning points.</p>
</div>
@endsection
