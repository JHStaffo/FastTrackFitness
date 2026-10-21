# Fast Track Fitness

Employee wellness program for Fast Track EMS: automatic activity tracking from Apple Health and Fitbit, daily habit points, a weekly $10 gift card, and monthly, quarterly and annual contests.

It runs next to [Draw Box](https://github.com/JHStaffo/Drawbox) on the same cPanel server, built the same way (Laravel 13, Blade, plain CSS, no frontend build step). It reads Draw Box's users table as its roster, so both apps share one list of people and the same emailed sign-in code flow.

Build plan: https://claude.ai/artifact/MygcXq1GE7TLmSqmwhBWSz

## Layout

| Path | What it is |
| --- | --- |
| `app/Scoring` | The rules engine: daily points, weekly reward, other-category limits, contest standings, tie-breakers and winner selection. No database access; covered by `tests/Feature/ScorerTest.php`. |
| `config/wellness.php` | Every point value, cap and policy, plus the roster connection and program time zone. |
| `app/Services/ProgramData.php` | Turns stored records (wearable imports, manual check-ins, approved visit notes) into scoring inputs. |
| `database/migrations` | Program tables: participants, devices, activity records, manual entries, submissions, the append-only points ledger, contests, awards, allowance and audit log. |
| `public/css/app.css` | Draw Box's stylesheet with the wellness additions. |
| `mobile/` | Expo (React Native) app for iPhone and Android. The iPhone app will read Apple Health; Fitbit is read server-side. |
| `brand/` | The Fast Track EMS logo as SVG and PNG, converted from the original EPS, and the Star of Life app icon. |

## Local development

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder   # sample people and three weeks of activity
php artisan serve
```

Sign in as `sam.patel@fasttrackems.com` (employee) or `alex.rivera@fasttrackems.com` (admin). With `MAIL_MAILER=log`, the sign-in code is written to `storage/logs/laravel.log`.

Run `php artisan test` and `vendor/bin/pint` before pushing.

For the phone app: `cd mobile && npm install && npx expo start`.

## Program rules

Values still waiting on a decision, all in `config/wellness.php`:

- `contest_other_caps`: how many other-category points count toward each contest (recommended 60 / 180 / 730).
- `stacking`: `one_tier_per_year` (spec as written) or `up_tier_allowed`.
- InBody improvement thresholds.
- `PROGRAM_TIMEZONE`: defaults to America/Chicago; set it to the company's time zone.

Scoring conventions: weeks run Sunday to Saturday, sleep counts on the day you wake up, overlapping workouts from two sources count once, workouts typed into the Health app by hand don't count, and themed-campaign points count toward contests but never toward the weekly 56.

## Deploy on cPanel (next to Draw Box)

Follow Draw Box's README for PHP, the subdomain (`fitness.fasttrackems.com`, document root `fasttrackfitness/public`), mail, Composer and cron. The differences:

1. **Database:** create a MySQL database for this app. In cPanel's MySQL Databases, also add this app's database user to **Draw Box's** database with only the `SELECT` privilege. This app never writes to Draw Box's tables.
2. **`.env`:** in addition to Draw Box's settings:
   ```ini
   APP_NAME="Fast Track Fitness"
   APP_URL=https://fitness.fasttrackems.com
   MAIL_FROM_ADDRESS=fitness@fasttrackems.com
   PROGRAM_TIMEZONE=America/Chicago
   ROSTER_DB_CONNECTION=drawbox
   DRAWBOX_DB_DATABASE=cpaneluser_drawbox
   ```
   The `DRAWBOX_DB_*` host, user and password default to this app's own `DB_*` values.
3. **Enroll people:** everyone on Draw Box's roster can sign in, and Draw Box admins are admins here. Enroll participants with
   ```sh
   php artisan fitness:enroll someone@fasttrackems.com
   php artisan fitness:enroll manager@fasttrackems.com --role=wellness_admin
   ```

## Status

Working now: email-code sign-in against the shared roster, the employee dashboard, the daily check-in for meals, water and meal plan, and admin contest standings with a proposed winner and alternates.

Next: Fitbit connection and nightly sync, the phone app's sign-in and Apple Health upload, visit-note uploads and the review queue, weekly gift-card and contest closing, and reports.
