import { addDays, weekStart, type LocalDate } from "./dates";
import type { DayScore } from "./daily";
import type { RuleSet } from "./rules";

export interface WeeklyRewardResult {
  weekStart: LocalDate;
  weekEnd: LocalDate;
  /** Standard daily-habit points only; other-category and campaign points never count (spec 4). */
  points: number;
  pointsRequired: number;
  qualifies: boolean;
  amountUsd: number;
}

/** Weekly $10 gift-card check for the Sunday-Saturday week containing `anyDateInWeek`. */
export function evaluateWeeklyReward(
  dayScores: DayScore[],
  anyDateInWeek: LocalDate,
  rules: RuleSet,
): WeeklyRewardResult {
  const start = weekStart(anyDateInWeek);
  const end = addDays(start, 6);
  const points = dayScores
    .filter((d) => d.date >= start && d.date <= end)
    .reduce((sum, d) => sum + d.dailyPoints, 0);
  const required = rules.weeklyReward.pointsRequired;
  const qualifies = points >= required;
  return {
    weekStart: start,
    weekEnd: end,
    points,
    pointsRequired: required,
    qualifies,
    amountUsd: qualifies ? rules.weeklyReward.amountUsd : 0,
  };
}
