import type { LocalDate, PeriodKind } from "./dates";

/**
 * Every number in the program rules lives here so admins can change it
 * without a code change (spec 3C). The database stores versions of this
 * object with an effective date; DEFAULT_RULES mirrors the requirements spec.
 */

export type DailyCategory = "meals" | "sleep" | "workout" | "steps" | "water" | "mealPlan";

export type OtherCategory =
  | "selfCare"
  | "inBody"
  | "eyeDoctor"
  | "dentist"
  | "primaryCare"
  | "weighIn";

export interface OtherRule {
  pointsPerEvent: number;
  /** Most points this category can award within one `limitPeriod`. */
  maxPointsPerPeriod: number;
  limitPeriod: Exclude<PeriodKind, "quarter">;
  /** Needs an approved document (attendance note, InBody sheet) before points count. */
  requiresDocument: boolean;
}

export type StackingPolicy =
  /** Spec 5 as written: a win in one tier blocks every other tier for the rest of the year. */
  | "oneTierPerYear"
  /** Alternative: a win blocks the same and lower tiers only. */
  | "upTierAllowed";

export interface RuleSet {
  version: string;
  effectiveFrom: LocalDate;
  daily: {
    pointsPerMeal: number;
    maxMealPoints: number;
    sleepMinMinutes: number;
    workoutMinMinutes: number;
    stepsMin: number;
    waterMinOz: number;
    maxPointsPerDay: number;
  };
  weeklyReward: {
    /** Standard daily-habit points needed in a Sunday-Saturday week. */
    pointsRequired: number;
    amountUsd: number;
  };
  other: Record<OtherCategory, OtherRule>;
  /**
   * Most other-category points that count toward a contest period.
   * `null` means uncapped. Spec 3C requires a cap; the value is still open.
   */
  contestOtherCaps: Record<Exclude<PeriodKind, "week">, number | null>;
  stacking: StackingPolicy;
  /** Days a day stays open for late wearable syncs before it is final. */
  lateSyncGraceDays: number;
}

export const DEFAULT_RULES: RuleSet = {
  version: "spec-draft-2026-10-21",
  effectiveFrom: "2026-12-01",
  daily: {
    pointsPerMeal: 1,
    maxMealPoints: 3,
    sleepMinMinutes: 6 * 60,
    workoutMinMinutes: 30,
    stepsMin: 10_000,
    waterMinOz: 64,
    maxPointsPerDay: 8,
  },
  weeklyReward: { pointsRequired: 56, amountUsd: 10 },
  other: {
    selfCare: { pointsPerEvent: 10, maxPointsPerPeriod: 10, limitPeriod: "week", requiresDocument: true },
    inBody: { pointsPerEvent: 30, maxPointsPerPeriod: 30, limitPeriod: "month", requiresDocument: true },
    eyeDoctor: { pointsPerEvent: 100, maxPointsPerPeriod: 100, limitPeriod: "year", requiresDocument: true },
    dentist: { pointsPerEvent: 185, maxPointsPerPeriod: 370, limitPeriod: "year", requiresDocument: true },
    primaryCare: { pointsPerEvent: 365, maxPointsPerPeriod: 365, limitPeriod: "year", requiresDocument: true },
    weighIn: { pointsPerEvent: 10, maxPointsPerPeriod: 10, limitPeriod: "week", requiresDocument: false },
  },
  // Recommended values from the build plan; not yet confirmed.
  contestOtherCaps: { month: 60, quarter: 180, year: 730 },
  stacking: "oneTierPerYear",
  lateSyncGraceDays: 3,
};
