# Fast Track Fitness

Laravel 13 app for Fast Track EMS's wellness program, deployed on the same WHM/cPanel server as Draw Box (PHP + MySQL). Read README.md for the rules and the deploy.

- No frontend build step, same as Draw Box: plain `public/css/app.css` and Blade views. Don't add Vite or npm assets to the web app. The phone app in `mobile/` is the only Node project.
- The roster is Draw Box's users table, read through the `drawbox` connection. Never write to it: `User` cancels saves when the roster is shared, and sign-in uses no remember-me cookie for the same reason.
- Every rule value lives in `config/wellness.php`; scoring logic lives in `app/Scoring` with no database access. Add a test in `tests/Feature/ScorerTest.php` for any rule change.
- Points go in the append-only ledger; corrections are new rows with a reason.
- Run `php artisan test` and `vendor/bin/pint` before pushing.
