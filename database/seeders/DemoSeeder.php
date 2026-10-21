<?php

namespace Database\Seeders;

use App\Models\ActivityRecord;
use App\Models\Device;
use App\Models\ManualEntry;
use App\Models\Participant;
use App\Models\Submission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/** Sample people and three weeks of activity for local development: php artisan db:seed --class=DemoSeeder */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(User::rosterIsLocal(), 500, 'DemoSeeder only runs against a local roster.');

        $today = CarbonImmutable::now(config('wellness.timezone'))->startOfDay();
        $people = [
            ['Alex Rivera', 'admin', 1.0],
            ['Sam Patel', 'employee', 0.85],
            ['Jordan Lee', 'employee', 0.7],
            ['Casey Brooks', 'employee', 0.8],
            ['Morgan Hayes', 'employee', 0.6],
            ['Taylor Nguyen', 'employee', 0.9],
        ];

        foreach ($people as $i => [$name, $role, $effort]) {
            $user = User::create([
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)).'@fasttrackems.com',
                'role' => $role,
            ]);
            Participant::create(['user_id' => $user->id, 'enrolled_from' => $today->subDays(30)->toDateString()]);
            $device = Device::create([
                'user_id' => $user->id,
                'provider' => $i % 2 ? 'fitbit' : 'apple_health',
                'last_sync_at' => now()->subMinutes(10 + $i * 7),
            ]);

            mt_srand($i + 1);
            for ($d = 21; $d >= 0; $d--) {
                $date = $today->subDays($d);
                $hit = fn () => mt_rand(0, 100) / 100 <= $effort;
                $record = fn (string $type, float $value, string $start, string $end, ?string $kind = null) => ActivityRecord::create([
                    'user_id' => $user->id, 'device_id' => $device->id, 'type' => $type, 'value' => $value,
                    'start_at' => $date->setTimeFromTimeString($start), 'end_at' => $date->setTimeFromTimeString($end),
                    'scoring_date' => $date->toDateString(), 'external_id' => "{$type}-{$date->toDateString()}",
                    'source_app' => $device->provider === 'fitbit' ? 'Fitbit' : 'Apple Watch', 'workout_kind' => $kind,
                ]);

                $record('steps', $hit() ? mt_rand(10000, 15000) : mt_rand(4000, 9800), '00:00', '23:59');
                $record('sleep', $hit() ? mt_rand(370, 480) : mt_rand(240, 350), '00:00', '07:00');
                if ($hit()) {
                    $record('workout', 40, '17:00', '17:40', 'Running');
                }
                ManualEntry::create([
                    'user_id' => $user->id,
                    'scoring_date' => $date->toDateString(),
                    'meals_logged' => $hit() ? 3 : mt_rand(1, 2),
                    'water_oz' => $hit() ? 72 : 40,
                    'meal_plan_confirmed' => $hit(),
                ]);
            }
        }

        Submission::create(['user_id' => 4, 'category' => 'dentist', 'event_date' => $today->subDays(5)->toDateString(), 'status' => 'approved']);
        Submission::create(['user_id' => 2, 'category' => 'weigh_in', 'event_date' => $today->subDays(2)->toDateString(), 'status' => 'approved']);
    }
}
