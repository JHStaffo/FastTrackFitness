<?php

/*
| Program rules from the requirements spec. Admins will be able to publish new
| versions from the settings page; until then these are the values in force.
*/

return [

    // Connection that holds the employee roster. Production points this at
    // Draw Box's database ("drawbox") so both apps share one list of people.
    // Leave it empty to use this app's own users table (local dev and tests).
    'roster_connection' => env('ROSTER_DB_CONNECTION') ?: null,

    // Scoring days run midnight to midnight in this time zone.
    'timezone' => env('PROGRAM_TIMEZONE', 'America/Chicago'),

    'rules' => [
        'version' => 'spec-draft-2026-10-21',
        'effective_from' => '2026-12-01',

        'daily' => [
            'points_per_meal' => 1,
            'max_meal_points' => 3,
            'sleep_min_minutes' => 360,
            'workout_min_minutes' => 30,
            'steps_min' => 10000,
            'water_min_oz' => 64,
            'max_points_per_day' => 8,
        ],

        'weekly_reward' => ['points_required' => 56, 'amount_usd' => 10],

        // limit_period: week, month or year.
        'other' => [
            'self_care' => ['points_per_event' => 10, 'max_points_per_period' => 10, 'limit_period' => 'week', 'requires_document' => true],
            'in_body' => ['points_per_event' => 30, 'max_points_per_period' => 30, 'limit_period' => 'month', 'requires_document' => true],
            'eye_doctor' => ['points_per_event' => 100, 'max_points_per_period' => 100, 'limit_period' => 'year', 'requires_document' => true],
            'dentist' => ['points_per_event' => 185, 'max_points_per_period' => 370, 'limit_period' => 'year', 'requires_document' => true],
            'primary_care' => ['points_per_event' => 365, 'max_points_per_period' => 365, 'limit_period' => 'year', 'requires_document' => true],
            'weigh_in' => ['points_per_event' => 10, 'max_points_per_period' => 10, 'limit_period' => 'week', 'requires_document' => false],
        ],

        // Most other-category points that count toward a contest (null = no cap).
        // Recommended values from the build plan; not yet confirmed.
        'contest_other_caps' => ['month' => 60, 'quarter' => 180, 'year' => 730],

        // one_tier_per_year (spec as written) or up_tier_allowed.
        'stacking' => 'one_tier_per_year',

        // Days a day stays open for late wearable syncs.
        'late_sync_grace_days' => 3,
    ],
];
