<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| user_id columns point at the roster's users table, which in production lives
| in Draw Box's database, so they are plain indexed ids without foreign keys.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        // Enrollment in the wellness program and the person's role in it.
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('program_role')->default('employee'); // employee, wellness_admin, system_admin
            $table->date('enrolled_from');
            $table->timestamp('consent_accepted_at')->nullable();
            $table->timestamps();
        });

        // A linked wearable. Replacing a device adds a row; earned points stay in the ledger.
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('provider'); // apple_health, fitbit
            $table->string('status')->default('connected'); // connected, sync_error, disconnected
            $table->text('tokens')->nullable(); // Fitbit OAuth tokens, stored with the encrypted cast
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });

        // One imported sample from Apple Health or Fitbit, with its source kept (spec 6).
        Schema::create('activity_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('device_id')->constrained();
            $table->string('type'); // steps, sleep, workout, water, weight
            $table->decimal('value', 10, 2); // steps, minutes, ounces or pounds
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->date('scoring_date'); // program-time-zone date; sleep scores on the wake-up date
            $table->string('external_id'); // provider's id, so re-imports are ignored
            $table->string('source_app')->nullable();
            $table->string('source_device')->nullable();
            $table->boolean('user_entered')->default(false); // typed into the Health app by hand; never scored
            $table->string('workout_kind')->nullable();
            $table->timestamps();
            $table->unique(['device_id', 'external_id']);
            $table->index(['user_id', 'scoring_date']);
        });

        // What wearables can't measure: meals, water and the meal-plan check-in.
        Schema::create('manual_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('scoring_date');
            $table->unsignedTinyInteger('meals_logged')->default(0);
            $table->decimal('water_oz', 6, 1)->default(0);
            $table->boolean('meal_plan_confirmed')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'scoring_date']);
        });

        // Visit notes and InBody sheets. Only what eligibility needs: no diagnoses or results.
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('category'); // self_care, in_body, eye_doctor, dentist, primary_care, weigh_in
            $table->date('event_date');
            $table->string('document_path')->nullable();
            $table->decimal('body_fat_pct', 5, 2)->nullable(); // InBody only
            $table->decimal('muscle_mass_lb', 6, 2)->nullable(); // InBody only
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->foreignId('appeal_of_id')->nullable()->constrained('submissions');
            $table->timestamps();
        });

        // Append-only points ledger. Corrections are new rows with a reason (spec 9).
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('scoring_date');
            $table->string('kind'); // daily, campaign, other, adjustment, reversal
            $table->string('category');
            $table->integer('points');
            $table->string('rule_version');
            $table->json('source_ids')->nullable();
            $table->string('reason')->nullable(); // required for adjustments and reversals
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_test')->default(false); // soft-launch points; excluded from official contests
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'scoring_date']);
        });

        Schema::create('rule_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->date('effective_from');
            $table->json('rules');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('mode'); // replace, bonus
            $table->integer('points');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamp('published_at')->nullable(); // rules must show before the campaign starts
            $table->timestamps();
        });

        Schema::create('contests', function (Blueprint $table) {
            $table->id();
            $table->string('tier'); // monthly, quarterly, annual
            $table->string('period_key');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('open'); // open, closing, winner_confirmed
            $table->json('results')->nullable(); // standings, alternates and skip reasons at close
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamps();
            $table->unique(['tier', 'period_key']);
        });

        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type'); // weekly_gift_card, monthly, quarterly, annual
            $table->string('period_key');
            $table->unsignedInteger('amount_cents');
            $table->foreignId('contest_id')->nullable()->constrained();
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->string('provider_ref')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'type', 'period_key']);
        });

        // The $200 wearable or wellness-item allowance.
        Schema::create('allowance_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedInteger('amount_cents');
            $table->string('description');
            $table->string('receipt_path')->nullable();
            $table->string('method'); // reimbursement, direct_payment
            $table->string('status')->default('requested'); // requested, approved, paid, rejected
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        // Grace periods and exemptions for device failures, outages or medical reasons.
        Schema::create('wearable_exceptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('reason');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedBigInteger('approved_by');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('action');
            $table->string('entity');
            $table->unsignedBigInteger('entity_id');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity', 'entity_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'wearable_exceptions', 'allowance_transactions', 'awards', 'contests', 'campaigns',
            'rule_versions', 'ledger_entries', 'submissions', 'manual_entries', 'activity_records', 'devices',
            'participants', 'login_codes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
