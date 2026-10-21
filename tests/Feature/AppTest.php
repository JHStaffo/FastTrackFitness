<?php

namespace Tests\Feature;

use App\Models\LoginCode;
use App\Models\ManualEntry;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\LoginCode as LoginCodeNotification;
use App\Services\ProgramData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppTest extends TestCase
{
    use RefreshDatabase;

    private function enrolled(string $role = 'employee', string $rosterRole = 'employee'): User
    {
        $user = User::factory()->role($rosterRole)->create();
        Participant::create(['user_id' => $user->id, 'program_role' => $role, 'enrolled_from' => '2026-01-01']);

        return $user;
    }

    public function test_sign_in_with_an_emailed_code(): void
    {
        Notification::fake();
        $user = $this->enrolled();

        $this->post('/login', ['email' => strtoupper($user->email)])->assertRedirect('/login/code');

        $code = null;
        Notification::assertSentTo($user, LoginCodeNotification::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });

        $this->post('/login/code', ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->post('/login/code', ['code' => $code])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_unknown_emails_get_the_same_answer_and_no_code(): void
    {
        Notification::fake();
        $this->post('/login', ['email' => 'nobody@fasttrackems.com'])->assertRedirect('/login/code');
        Notification::assertNothingSent();
        $this->assertSame(0, LoginCode::count());
    }

    public function test_dashboard_shows_todays_points(): void
    {
        $user = $this->enrolled();
        ManualEntry::create([
            'user_id' => $user->id, 'scoring_date' => ProgramData::today(),
            'meals_logged' => 3, 'water_oz' => 70, 'meal_plan_confirmed' => true,
        ]);

        $this->actingAs($user)->get('/')->assertOk()->assertSee('5 / 8');
    }

    public function test_people_not_enrolled_see_a_notice(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertSee("aren't enrolled", escape: false);
    }

    public function test_logging_today(): void
    {
        $user = $this->enrolled();
        $this->actingAs($user)->post('/today', ['meals_logged' => 2, 'water_oz' => 64, 'meal_plan_confirmed' => '1'])
            ->assertRedirect('/');

        $entry = ManualEntry::where('user_id', $user->id)->sole();
        $this->assertSame([2, 64.0, true], [$entry->meals_logged, $entry->water_oz, $entry->meal_plan_confirmed]);
    }

    public function test_standings_are_for_wellness_admins_and_draw_box_admins(): void
    {
        $this->actingAs($this->enrolled())->get('/admin/standings')->assertForbidden();
        $this->actingAs($this->enrolled('wellness_admin'))->get('/admin/standings')->assertOk();
        $this->actingAs($this->enrolled(rosterRole: 'admin'))->get('/admin/standings?tier=annual')->assertOk();
    }

    public function test_the_shared_roster_is_read_only(): void
    {
        $user = User::factory()->create(['name' => 'Before']);
        config(['wellness.roster_connection' => 'drawbox']);

        $user->name = 'After';
        $this->assertFalse($user->save());
    }
}
