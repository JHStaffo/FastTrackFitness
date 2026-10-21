import { daysBetweenInclusive, inPeriod, periodFor, type LocalDate, type Period } from "./dates";
import { scoreDay, type Campaign, type DayActivity } from "./daily";
import { scoreOtherEvents, type OtherEvent } from "./other";
import type { RuleSet } from "./rules";

export type ContestTier = "monthly" | "quarterly" | "annual";

const TIER_PERIOD = { monthly: "month", quarterly: "quarter", annual: "year" } as const;
const TIER_RANK: Record<ContestTier, number> = { monthly: 1, quarterly: 2, annual: 3 };

export interface EmployeeHistory {
  employeeId: string;
  name: string;
  /** First day the employee could score (enrollment or hire date). */
  enrolledFrom: LocalDate;
  days: DayActivity[];
  /** Full history, so yearly category limits see events before the period. */
  otherEvents: OtherEvent[];
}

export interface Standing {
  employeeId: string;
  name: string;
  dailyPoints: number;
  campaignPoints: number;
  /** Other-category points earned in the period, before the contest cap. */
  otherPointsEarned: number;
  /** Other-category points that count after the contest cap (spec 3C). */
  otherPointsCounted: number;
  total: number;
  // Tie-breakers, in spec order.
  daysWithAnyPoint: number;
  perfectDays: number;
  eligibleDays: number;
  pctDaysWithAnyPoint: number;
  rank: number;
}

export function contestPeriod(tier: ContestTier, anyDate: LocalDate): Period {
  return periodFor(TIER_PERIOD[tier], anyDate);
}

/**
 * Ranks employees for a contest period. `asOf` limits scoring to days that
 * have happened, so standings can be shown mid-period.
 */
export function computeStandings(
  employees: EmployeeHistory[],
  period: Period,
  rules: RuleSet,
  campaigns: Campaign[] = [],
  asOf: LocalDate = period.end,
): Standing[] {
  if (period.kind === "week") throw new Error("Contests run monthly, quarterly or annually");
  const cap = rules.contestOtherCaps[period.kind];
  const lastDay = asOf < period.end ? asOf : period.end;

  const rows = employees.map((emp): Omit<Standing, "rank"> => {
    const window = { start: period.start, end: lastDay };
    const dayScores = emp.days.filter((d) => inPeriod(d.date, window)).map((d) => scoreDay(d, rules, campaigns));
    const dailyPoints = dayScores.reduce((s, d) => s + d.dailyPoints, 0);
    const campaignPoints = dayScores.reduce((s, d) => s + d.campaignPoints, 0);

    const otherPointsEarned = scoreOtherEvents(emp.otherEvents, rules)
      .filter((a) => inPeriod(a.date, window))
      .reduce((s, a) => s + a.points, 0);
    const otherPointsCounted = cap === null ? otherPointsEarned : Math.min(otherPointsEarned, cap);

    const firstDay = emp.enrolledFrom > period.start ? emp.enrolledFrom : period.start;
    const eligibleDays = firstDay > lastDay ? 0 : daysBetweenInclusive(firstDay, lastDay);
    const daysWithAnyPoint = dayScores.filter((d) => d.date >= firstDay && d.dailyPoints > 0).length;
    const perfectDays = dayScores.filter(
      (d) => d.date >= firstDay && d.dailyPoints >= rules.daily.maxPointsPerDay,
    ).length;

    return {
      employeeId: emp.employeeId,
      name: emp.name,
      dailyPoints,
      campaignPoints,
      otherPointsEarned,
      otherPointsCounted,
      total: dailyPoints + campaignPoints + otherPointsCounted,
      daysWithAnyPoint,
      perfectDays,
      eligibleDays,
      pctDaysWithAnyPoint: eligibleDays === 0 ? 0 : daysWithAnyPoint / eligibleDays,
    };
  });

  rows.sort(compareStandings);
  // Employees still tied after every tie-breaker share a rank; an admin resolves it (spec 5).
  return rows.map((row, i, all) => {
    let rank = i + 1;
    for (let j = i - 1; j >= 0 && compareStandings(all[j]!, row) === 0; j--) rank = j + 1;
    return { ...row, rank };
  });
}

type Comparable = Pick<Standing, "total" | "daysWithAnyPoint" | "perfectDays" | "pctDaysWithAnyPoint">;

/** Sort order: points, then the spec's tie-breakers in order. */
export function compareStandings(a: Comparable, b: Comparable): number {
  return (
    b.total - a.total ||
    b.daysWithAnyPoint - a.daysWithAnyPoint ||
    b.perfectDays - a.perfectDays ||
    b.pctDaysWithAnyPoint - a.pctDaysWithAnyPoint
  );
}

export interface PriorWin {
  employeeId: string;
  tier: ContestTier;
  /** Any date inside the period that was won. */
  periodDate: LocalDate;
}

export interface WinnerSelection {
  winner: Standing | null;
  alternates: Standing[];
  skipped: { employeeId: string; reason: string }[];
  /** True when the winner is still tied with the next eligible employee after all tie-breakers. */
  needsAdminTieBreak: boolean;
}

/**
 * Applies prize restrictions and picks the winner plus next-highest eligible
 * alternates. `otherIneligible` carries reasons from outside scoring, such as
 * an inactive employee or no connected wearable.
 */
export function selectWinner(opts: {
  standings: Standing[];
  tier: ContestTier;
  period: Period;
  priorWins: PriorWin[];
  rules: RuleSet;
  otherIneligible?: Record<string, string>;
  alternates?: number;
}): WinnerSelection {
  const { standings, tier, period, priorWins, rules, otherIneligible = {}, alternates = 3 } = opts;
  const year = period.start.slice(0, 4);
  const quarterKey = periodFor("quarter", period.start).key;

  const reasonFor = (s: Standing): string | null => {
    if (otherIneligible[s.employeeId]) return otherIneligible[s.employeeId]!;
    if (s.total <= 0) return "No points in this period";
    for (const w of priorWins) {
      if (w.employeeId !== s.employeeId || w.periodDate.slice(0, 4) !== year) continue;
      if (w.tier === tier) {
        if (tier === "monthly" && periodFor("quarter", w.periodDate).key === quarterKey) {
          return "Already won a monthly prize this quarter";
        }
        if (tier === "quarterly") return "Already won a quarterly prize this year";
        if (tier === "annual") return "Already won the annual prize this year";
        continue;
      }
      if (rules.stacking === "oneTierPerYear") return `Already won a ${w.tier} prize this year`;
      if (TIER_RANK[w.tier] > TIER_RANK[tier]) return `Already won a higher (${w.tier}) prize this year`;
    }
    return null;
  };

  const eligible: Standing[] = [];
  const skipped: WinnerSelection["skipped"] = [];
  for (const s of standings) {
    const reason = reasonFor(s);
    if (reason) skipped.push({ employeeId: s.employeeId, reason });
    else eligible.push(s);
  }

  const [winner = null, ...rest] = eligible;
  const next = rest[0];
  return {
    winner,
    alternates: rest.slice(0, alternates),
    skipped,
    needsAdminTieBreak: !!winner && !!next && compareStandings(winner, next) === 0,
  };
}
