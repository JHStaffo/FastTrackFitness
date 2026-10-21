import type { LocalDate } from "./dates";
import type { DailyCategory, RuleSet } from "./rules";

export interface Interval {
  /** ISO timestamps. */
  start: string;
  end: string;
}

/**
 * One employee's inputs for one local day, after imports are de-duplicated.
 * Sleep is attributed to the day the employee woke up, so night-shift sleep
 * (for example 09:00-15:00) still counts.
 */
export interface DayActivity {
  date: LocalDate;
  mealsLogged: number;
  sleepMinutes: number;
  /** Workout sessions from wearables; overlapping sessions are merged before counting. */
  workouts: Interval[];
  steps: number;
  waterOz: number;
  mealPlanConfirmed: boolean;
}

export interface Campaign {
  id: string;
  name: string;
  start: LocalDate;
  end: LocalDate;
  category: DailyCategory;
  /** "replace": the category is worth `points` instead of its usual value. "bonus": `points` on top. */
  mode: "replace" | "bonus";
  points: number;
}

export interface DayScore {
  date: LocalDate;
  /** Standard points per category, before campaigns. */
  categories: Record<DailyCategory, number>;
  /** Standard daily-habit points, capped at the daily maximum. Used for the weekly reward. */
  dailyPoints: number;
  /** Extra points from themed campaigns. Count toward contests, never toward the weekly reward. */
  campaignPoints: number;
  appliedCampaignIds: string[];
}

/** Total minutes covered by the intervals, counting overlapping time once. */
export function mergedMinutes(intervals: Interval[]): number {
  const sorted = intervals
    .map((i) => [Date.parse(i.start), Date.parse(i.end)] as const)
    .filter(([s, e]) => Number.isFinite(s) && Number.isFinite(e) && e > s)
    .sort((a, b) => a[0] - b[0]);

  let total = 0;
  let curStart: number | null = null;
  let curEnd = 0;
  for (const [s, e] of sorted) {
    if (curStart === null || s > curEnd) {
      if (curStart !== null) total += curEnd - curStart;
      curStart = s;
      curEnd = e;
    } else {
      curEnd = Math.max(curEnd, e);
    }
  }
  if (curStart !== null) total += curEnd - curStart;
  return Math.floor(total / 60_000);
}

export function scoreDay(day: DayActivity, rules: RuleSet, campaigns: Campaign[] = []): DayScore {
  const r = rules.daily;
  const categories: Record<DailyCategory, number> = {
    meals: Math.min(Math.max(0, Math.floor(day.mealsLogged)) * r.pointsPerMeal, r.maxMealPoints),
    sleep: day.sleepMinutes >= r.sleepMinMinutes ? 1 : 0,
    workout: mergedMinutes(day.workouts) >= r.workoutMinMinutes ? 1 : 0,
    steps: day.steps >= r.stepsMin ? 1 : 0,
    water: day.waterOz >= r.waterMinOz ? 1 : 0,
    mealPlan: day.mealPlanConfirmed ? 1 : 0,
  };
  const raw = Object.values(categories).reduce((a, b) => a + b, 0);
  const dailyPoints = Math.min(raw, r.maxPointsPerDay);

  // At most one campaign per category per day, so overlapping campaigns can't double count.
  let campaignPoints = 0;
  const appliedCampaignIds: string[] = [];
  const used = new Set<DailyCategory>();
  const active = campaigns
    .filter((c) => day.date >= c.start && day.date <= c.end)
    .sort((a, b) => a.start.localeCompare(b.start) || a.id.localeCompare(b.id));
  for (const c of active) {
    if (used.has(c.category) || categories[c.category] === 0) continue;
    used.add(c.category);
    const extra = c.mode === "replace" ? Math.max(0, c.points - categories[c.category]) : c.points;
    if (extra > 0) {
      campaignPoints += extra;
      appliedCampaignIds.push(c.id);
    }
  }

  return { date: day.date, categories, dailyPoints, campaignPoints, appliedCampaignIds };
}
