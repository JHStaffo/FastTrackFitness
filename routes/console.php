<?php

use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('fitness:enroll {email} {--role=employee} {--from=}', function (string $email) {
    $user = User::where('email', strtolower($email))->first();
    if (! $user) {
        $this->error("{$email} is not on the roster. Add them in Draw Box first.");

        return 1;
    }
    Participant::updateOrCreate(['user_id' => $user->id], [
        'program_role' => $this->option('role'),
        'enrolled_from' => $this->option('from') ?: now(config('wellness.timezone'))->toDateString(),
    ]);
    $this->info("{$user->name} <{$user->email}> is enrolled as {$this->option('role')}.");
})->purpose('Enroll someone from the roster in the wellness program');
