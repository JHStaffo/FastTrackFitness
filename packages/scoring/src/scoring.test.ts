import { describe, expect, it } from "vitest";
import {
  DEFAULT_RULES,
  addDays,
  computeStandings,
  contestPeriod,
  evaluateWeeklyReward,
  mergedMinutes,
  periodFor,
  scoreDay,
  scoreOtherEvents,
  selectWinner,
  type DayActivity,
  type EmployeeHistory,
  type RuleSet,
} from "./index";

const rules = DEFAULT_RULES;

function perfectDay(date: string): DayActivity {
  return {
    date,
    mealsLogged: 3,
    sleepMinutes: 7 * 60,
    workouts: [{ start: `${date}T12:00:00Z`, end: `${date}T12:45:00Z` }],
    steps: 12_000,
    waterOz: 80,
    mealPlanConfirmed: true,
  };
}

function emptyDay(date: string): DayActivity {
  return { date, mealsLogged: 0, sleepMinutes: 0, workouts: [], steps: 0, waterOz: 0, mealPlanConfirmed: false };
}

function days(start: string, count: number, make: (d: string, i: number) => DayActivity): DayActivity[] {
  return Array.from({ length: count }, (_, i) => make(addDays(start, i), i));
}

describe("dates", () => {
  it("starts weeks on Sunday", () => {
    // 2027-01-06 is a Wednesday
    expect(periodFor("week", "2027-01-06")).toMatchObject({ start: "2027-01-03", end: "2027-01-09" });
    expect(periodFor("week", "2027-01-03").start).toBe("2027-01-03");
    expect(periodFor("week", "2027-01-09").start).toBe("2027-01-03");
  });

  it("computes month, quarter and year bounds", () => {
    expect(periodFor("month", "2028-02-10")).toMatchObject({ start: "2028-02-01", end: "2028-02-29" });
    expect(periodFor("quarter", "2027-05-15")).toMatchObject({ key: "2027-Q2", start: "2027-04-01", end: "2027-06-30" });
    expect(periodFor("year", "2027-07-04")).toMatchObject({ start: "2027-01-01", end: "2027-12-31" });
  });
});

describe("daily scoring", () => {
  it("awards 8 points for a perfect day", () => {
    expect(scoreDay(perfectDay("2027-01-04"), rules).dailyPoints).toBe(8);
  });

  it("caps meals at 3 points and the day at 8", () => {
    const s = scoreDay({ ...perfectDay("2027-01-04"), mealsLogged: 6 }, rules);
    expect(s.categories.meals).toBe(3);
    expect(s.dailyPoints).toBe(8);
  });

  it("enforces the daily maximum even if rules would allow more", () => {
    const loose: RuleSet = { ...rules, daily: { ...rules.daily, maxMealPoints: 6 } };
    expect(scoreDay({ ...perfectDay("2027-01-04"), mealsLogged: 6 }, loose).dailyPoints).toBe(8);
  });

  it("applies each threshold", () => {
    const s = scoreDay(
      { ...perfectDay("2027-01-04"), sleepMinutes: 359, steps: 9_999, waterOz: 63, mealPlanConfirmed: false },
      rules,
    );
    expect(s.categories).toMatchObject({ sleep: 0, steps: 0, water: 0, mealPlan: 0, meals: 3, workout: 1 });
    expect(s.dailyPoints).toBe(4);
  });

  it("merges overlapping workouts so duplicates from two sources count once", () => {
    const dup = [
      { start: "2027-01-04T12:00:00Z", end: "2027-01-04T12:20:00Z" },
      { start: "2027-01-04T12:00:00Z", end: "2027-01-04T12:20:00Z" }, // same run from Strava
    ];
    expect(mergedMinutes(dup)).toBe(20);
    expect(scoreDay({ ...perfectDay("2027-01-04"), workouts: dup }, rules).categories.workout).toBe(0);
  });

  it("adds separate workouts in the same day", () => {
    const two = [
      { start: "2027-01-04T08:00:00Z", end: "2027-01-04T08:15:00Z" },
      { start: "2027-01-04T18:00:00Z", end: "2027-01-04T18:15:00Z" },
    ];
    expect(scoreDay({ ...perfectDay("2027-01-04"), workouts: two }, rules).categories.workout).toBe(1);
  });
});

describe("campaigns", () => {
  const stepMonth = {
    id: "steps-feb",
    name: "Step month",
    start: "2027-02-01",
    end: "2027-02-28",
    category: "steps" as const,
    mode: "replace" as const,
    points: 2,
  };

  it("replace mode makes steps worth 2 without changing the standard daily score", () => {
    const s = scoreDay(perfectDay("2027-02-10"), rules, [stepMonth]);
    expect(s.dailyPoints).toBe(8);
    expect(s.campaignPoints).toBe(1);
  });

  it("applies only one campaign per category per day", () => {
    const bonus = { ...stepMonth, id: "steps-bonus", mode: "bonus" as const, points: 5 };
    const s = scoreDay(perfectDay("2027-02-10"), rules, [stepMonth, bonus]);
    expect(s.appliedCampaignIds).toHaveLength(1);
  });

  it("does nothing outside its dates or when the goal was missed", () => {
    expect(scoreDay(perfectDay("2027-03-01"), rules, [stepMonth]).campaignPoints).toBe(0);
    expect(scoreDay({ ...perfectDay("2027-02-10"), steps: 100 }, rules, [stepMonth]).campaignPoints).toBe(0);
  });
});

describe("weekly reward", () => {
  it("requires 56 standard points in a Sunday-Saturday week", () => {
    const week = days("2027-01-03", 7, perfectDay).map((d) => scoreDay(d, rules));
    const r = evaluateWeeklyReward(week, "2027-01-05", rules);
    expect(r).toMatchObject({ weekStart: "2027-01-03", weekEnd: "2027-01-09", points: 56, qualifies: true, amountUsd: 10 });
  });

  it("does not count days outside the week", () => {
    // Monday through the next Sunday: 7 perfect days, but split across two weeks.
    const split = days("2027-01-04", 7, perfectDay).map((d) => scoreDay(d, rules));
    expect(evaluateWeeklyReward(split, "2027-01-04", rules).points).toBe(48);
    expect(evaluateWeeklyReward(split, "2027-01-04", rules).qualifies).toBe(false);
  });

  it("other-category and campaign points cannot replace missing daily points", () => {
    const week = days("2027-01-03", 7, (d, i) => (i === 0 ? { ...perfectDay(d), waterOz: 0 } : perfectDay(d)));
    const scored = week.map((d) =>
      scoreDay(d, rules, [
        { id: "c", name: "bonus", start: "2027-01-01", end: "2027-01-31", category: "steps", mode: "bonus", points: 5 },
      ]),
    );
    const r = evaluateWeeklyReward(scored, "2027-01-03", rules);
    expect(r.points).toBe(55);
    expect(r.qualifies).toBe(false);
  });
});

describe("other-category limits", () => {
  it("enforces per-category limits", () => {
    const awards = scoreOtherEvents(
      [
        { id: "d1", date: "2027-02-01", category: "dentist", documentApproved: true },
        { id: "d2", date: "2027-08-01", category: "dentist", documentApproved: true },
        { id: "d3", date: "2027-11-01", category: "dentist", documentApproved: true },
        { id: "s1", date: "2027-01-04", category: "selfCare", documentApproved: true },
        { id: "s2", date: "2027-01-06", category: "selfCare", documentApproved: true },
        { id: "s3", date: "2027-01-10", category: "selfCare", documentApproved: true },
      ],
      rules,
    );
    const pts = Object.fromEntries(awards.map((a) => [a.eventId, a.points]));
    expect(pts).toEqual({ d1: 185, d2: 185, d3: 0, s1: 10, s2: 0, s3: 10 });
  });

  it("awards nothing until the document is approved", () => {
    const [a] = scoreOtherEvents([{ id: "p", date: "2027-03-01", category: "primaryCare" }], rules);
    expect(a).toMatchObject({ points: 0, limitedBy: "awaitingDocument" });
  });

  it("weigh-ins need no document", () => {
    const [a] = scoreOtherEvents([{ id: "w", date: "2027-03-01", category: "weighIn" }], rules);
    expect(a?.points).toBe(10);
  });
});

function employee(id: string, dayList: DayActivity[], otherEvents: EmployeeHistory["otherEvents"] = []): EmployeeHistory {
  return { employeeId: id, name: id.toUpperCase(), enrolledFrom: "2027-01-01", days: dayList, otherEvents };
}

describe("contest standings", () => {
  const jan = contestPeriod("monthly", "2027-01-15");

  it("caps other-category points that count toward a contest", () => {
    const appointments = employee("appt", days("2027-01-01", 31, emptyDay), [
      { id: "pc", date: "2027-01-05", category: "primaryCare", documentApproved: true },
      { id: "dn", date: "2027-01-06", category: "dentist", documentApproved: true },
      { id: "ey", date: "2027-01-07", category: "eyeDoctor", documentApproved: true },
    ]);
    const habits = employee("habits", days("2027-01-01", 31, perfectDay));
    const [first, second] = computeStandings([appointments, habits], jan, rules);
    expect(first?.employeeId).toBe("habits");
    expect(first?.total).toBe(248);
    expect(second).toMatchObject({ otherPointsEarned: 650, otherPointsCounted: 60, total: 60 });
  });

  it("supports an uncapped configuration without code changes", () => {
    const uncapped: RuleSet = { ...rules, contestOtherCaps: { month: null, quarter: null, year: null } };
    const e = employee("a", [], [{ id: "pc", date: "2027-01-05", category: "primaryCare", documentApproved: true }]);
    expect(computeStandings([e], jan, uncapped)[0]?.total).toBe(365);
  });

  it("breaks ties by days with any point, then perfect days, then percentage", () => {
    // Both total 40. A: 5 perfect days. B: 10 days of 4 points.
    const a = employee("a", days("2027-01-01", 5, perfectDay));
    const b = employee(
      "b",
      days("2027-01-01", 10, (d) => ({ ...emptyDay(d), mealsLogged: 3, steps: 10_000 })),
    );
    const s = computeStandings([a, b], jan, rules);
    expect(s.map((x) => x.employeeId)).toEqual(["b", "a"]);
    expect(s[0]?.total).toBe(s[1]?.total);

    // Both 28 points over 6 days; C has 2 perfect days, D has none.
    const c = employee("c", [
      ...days("2027-01-01", 2, perfectDay),
      ...days("2027-01-03", 4, (dd) => ({ ...emptyDay(dd), mealsLogged: 3 })),
    ]);
    const d = employee(
      "d",
      days("2027-01-01", 6, (dd, i) => ({ ...emptyDay(dd), mealsLogged: 3, steps: 10_000, waterOz: i < 4 ? 64 : 0 })),
    );
    const s2 = computeStandings([d, c], jan, rules);
    expect(s2.map((x) => [x.employeeId, x.total, x.daysWithAnyPoint])).toEqual([
      ["c", 28, 6],
      ["d", 28, 6],
    ]);
  });

  it("uses the percentage of eligible days for late joiners", () => {
    const early = employee("early", days("2027-01-01", 10, perfectDay));
    const late = { ...employee("late", days("2027-01-22", 10, perfectDay)), enrolledFrom: "2027-01-22" };
    const s = computeStandings([early, late], jan, rules);
    // Same total, days and perfect days; late joiner has 10/10 eligible days vs 10/31.
    expect(s[0]?.employeeId).toBe("late");
    expect(s[0]?.pctDaysWithAnyPoint).toBe(1);
  });

  it("gives a shared rank and flags a tie no rule can break", () => {
    const a = employee("a", days("2027-01-01", 3, perfectDay));
    const b = employee("b", days("2027-01-01", 3, perfectDay));
    const s = computeStandings([a, b], jan, rules);
    expect(s.map((x) => x.rank)).toEqual([1, 1]);
    const pick = selectWinner({ standings: s, tier: "monthly", period: jan, priorWins: [], rules });
    expect(pick.needsAdminTieBreak).toBe(true);
  });

  it("only scores days up to asOf for mid-period standings", () => {
    const a = employee("a", days("2027-01-01", 31, perfectDay));
    expect(computeStandings([a], jan, rules, [], "2027-01-10")[0]?.total).toBe(80);
  });
});

describe("winner selection", () => {
  const people = ["a", "b", "c", "d", "e"].map((id, i) => employee(id, days("2027-01-01", 31 - i, perfectDay)));

  function standingsFor(tier: "monthly" | "quarterly" | "annual", date: string) {
    const period = contestPeriod(tier, date);
    return { period, standings: computeStandings(people, period, rules) };
  }

  it("picks the top scorer and lists alternates", () => {
    const { period, standings } = standingsFor("monthly", "2027-01-15");
    const pick = selectWinner({ standings, tier: "monthly", period, priorWins: [], rules });
    expect(pick.winner?.employeeId).toBe("a");
    expect(pick.alternates.map((x) => x.employeeId)).toEqual(["b", "c", "d"]);
  });

  it("limits the monthly prize to once per quarter", () => {
    const { standings } = standingsFor("monthly", "2027-01-15");
    const febPeriod = contestPeriod("monthly", "2027-02-15");
    const pick = selectWinner({
      standings,
      tier: "monthly",
      period: febPeriod,
      priorWins: [{ employeeId: "a", tier: "monthly", periodDate: "2027-01-15" }],
      rules,
    });
    expect(pick.winner?.employeeId).toBe("b");
    expect(pick.skipped[0]).toMatchObject({ employeeId: "a" });
    // A new quarter makes them eligible again.
    const april = selectWinner({
      standings,
      tier: "monthly",
      period: contestPeriod("monthly", "2027-04-15"),
      priorWins: [{ employeeId: "a", tier: "monthly", periodDate: "2027-01-15" }],
      rules,
    });
    expect(april.winner?.employeeId).toBe("a");
  });

  it("limits the quarterly prize to once per year", () => {
    const { standings } = standingsFor("quarterly", "2027-01-15");
    const pick = selectWinner({
      standings,
      tier: "quarterly",
      period: contestPeriod("quarterly", "2027-04-15"),
      priorWins: [{ employeeId: "a", tier: "quarterly", periodDate: "2027-02-01" }],
      rules,
    });
    expect(pick.winner?.employeeId).toBe("b");
  });

  it("one-tier-per-year: a monthly winner can't win the annual prize", () => {
    const { period, standings } = standingsFor("annual", "2027-06-01");
    const pick = selectWinner({
      standings,
      tier: "annual",
      period,
      priorWins: [{ employeeId: "a", tier: "monthly", periodDate: "2027-01-15" }],
      rules,
    });
    expect(pick.winner?.employeeId).toBe("b");
  });

  it("up-tier-allowed: a monthly winner can win annual, a quarterly winner can't win monthly", () => {
    const upTier: RuleSet = { ...rules, stacking: "upTierAllowed" };
    const annual = standingsFor("annual", "2027-06-01");
    expect(
      selectWinner({
        ...annual,
        tier: "annual",
        priorWins: [{ employeeId: "a", tier: "monthly", periodDate: "2027-01-15" }],
        rules: upTier,
      }).winner?.employeeId,
    ).toBe("a");

    const monthly = standingsFor("monthly", "2027-01-15");
    expect(
      selectWinner({
        ...monthly,
        tier: "monthly",
        period: contestPeriod("monthly", "2027-05-15"),
        priorWins: [{ employeeId: "a", tier: "quarterly", periodDate: "2027-03-31" }],
        rules: upTier,
      }).winner?.employeeId,
    ).toBe("b");
  });

  it("skips employees flagged ineligible outside scoring", () => {
    const { period, standings } = standingsFor("monthly", "2027-01-15");
    const pick = selectWinner({
      standings,
      tier: "monthly",
      period,
      priorWins: [],
      rules,
      otherIneligible: { a: "No wearable connected" },
    });
    expect(pick.winner?.employeeId).toBe("b");
    expect(pick.skipped).toEqual([{ employeeId: "a", reason: "No wearable connected" }]);
  });
});
