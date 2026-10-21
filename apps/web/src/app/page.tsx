import {
  DEFAULT_RULES,
  computeStandings,
  contestPeriod,
  evaluateWeeklyReward,
  scoreDay,
  type DailyCategory,
} from "@ftf/scoring";
import { DEMO_EMPLOYEES, DEMO_ME, DEMO_TODAY } from "@/lib/demo-data";

const CATEGORY_LABELS: Record<DailyCategory, string> = {
  meals: "Meals logged",
  sleep: "Sleep 6+ hours",
  workout: "Workout 30+ min",
  steps: "10,000 steps",
  water: "64 oz water",
  mealPlan: "Meal plan",
};

export default function Dashboard() {
  const rules = DEFAULT_RULES;
  const scores = DEMO_ME.days.map((d) => scoreDay(d, rules));
  const today = scores.find((s) => s.date === DEMO_TODAY);
  const week = evaluateWeeklyReward(scores, DEMO_TODAY, rules);
  const month = computeStandings(DEMO_EMPLOYEES, contestPeriod("monthly", DEMO_TODAY), rules, [], DEMO_TODAY);
  const myRank = month.find((s) => s.employeeId === DEMO_ME.employeeId);

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">Hi, {DEMO_ME.name.split(" ")[0]}</h1>
      <p className="text-sm text-zinc-500">Sample data shown until wearables are connected.</p>

      <section className="grid gap-4 sm:grid-cols-3">
        <Stat label="Today" value={`${today?.dailyPoints ?? 0} / ${rules.daily.maxPointsPerDay}`} />
        <Stat
          label={`This week (${week.weekStart} to ${week.weekEnd})`}
          value={`${week.points} / ${week.pointsRequired}`}
          note={week.qualifies ? "$10 gift card earned" : `${week.pointsRequired - week.points} points to the $10 gift card`}
        />
        <Stat label="Monthly contest" value={`#${myRank?.rank ?? "-"}`} note={`${myRank?.total ?? 0} points`} />
      </section>

      <section className="rounded-lg border border-zinc-200 bg-white p-4">
        <h2 className="mb-3 font-medium">Today&apos;s habits</h2>
        <ul className="divide-y divide-zinc-100">
          {(Object.keys(CATEGORY_LABELS) as DailyCategory[]).map((c) => (
            <li key={c} className="flex justify-between py-2 text-sm">
              <span>{CATEGORY_LABELS[c]}</span>
              <span className="tabular-nums">{today?.categories[c] ?? 0}</span>
            </li>
          ))}
        </ul>
      </section>

      <section className="rounded-lg border border-zinc-200 bg-white p-4">
        <h2 className="mb-3 font-medium">Monthly leaderboard</h2>
        <ol className="space-y-1 text-sm">
          {month.map((s) => (
            <li key={s.employeeId} className="flex justify-between">
              <span>
                {s.rank}. {s.name}
              </span>
              <span className="tabular-nums">{s.total}</span>
            </li>
          ))}
        </ol>
      </section>
    </div>
  );
}

function Stat({ label, value, note }: { label: string; value: string; note?: string }) {
  return (
    <div className="rounded-lg border border-zinc-200 bg-white p-4">
      <div className="text-xs text-zinc-500">{label}</div>
      <div className="mt-1 text-2xl font-semibold tabular-nums">{value}</div>
      {note && <div className="mt-1 text-xs text-zinc-600">{note}</div>}
    </div>
  );
}
