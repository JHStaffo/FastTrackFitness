# Fast Track Fitness

Employee wellness platform for Fast Track EMS: automatic activity tracking from Apple Health and Fitbit, daily habit points, a weekly $10 gift-card reward, and monthly, quarterly and annual contests.

Build plan: https://claude.ai/artifact/MygcXq1GE7TLmSqmwhBWSz

## Layout

| Path | What it is |
| --- | --- |
| `packages/scoring` | The rules engine: daily points, weekly reward, other-category limits, contest standings, tie-breakers and winner selection. Pure TypeScript, fully tested. |
| `packages/db` | PostgreSQL schema (Prisma): employees, devices, imported records, manual entries, submissions, the append-only points ledger, contests, awards, allowance and audit log. |
| `apps/web` | Next.js web app: employee dashboard on desktop and the admin portal. |
| `apps/mobile` | Expo (React Native) app for iPhone and Android. The iPhone app reads Apple Health; Fitbit is read server-side. |

## Getting started

Requires Node 22 and pnpm 10.

```bash
pnpm install
pnpm test          # scoring engine tests
pnpm typecheck
pnpm dev:web       # http://localhost:3000
pnpm dev:mobile    # Expo dev server
```

For the database, copy `packages/db/.env.example` to `packages/db/.env`, point `DATABASE_URL` at a Postgres instance, then run `pnpm --filter @ftf/db migrate:dev`.

## Program rules

All point values, caps and policies live in `packages/scoring/src/rules.ts` (`DEFAULT_RULES`), and admins will edit versions of them stored in the database. Values still waiting on a decision:

- `contestOtherCaps`: how many other-category points count toward each contest (recommended 60 / 180 / 730).
- `stacking`: `oneTierPerYear` (spec as written) or `upTierAllowed`.
- InBody improvement thresholds.

Scoring conventions: days are in the company time zone, weeks run Sunday to Saturday, sleep counts on the day you wake up, overlapping workouts from two sources count once, and themed-campaign points count toward contests but never toward the weekly 56.

## Status

The current pages and app screens use sample data. Next steps: sign-in, database wiring, Fitbit OAuth and sync, the Apple Health adapter (needs an Expo development build and the Apple Developer account), manual entry and document upload, and the admin review queue.
