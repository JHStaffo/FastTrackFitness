import { DEFAULT_RULES, computeStandings, contestPeriod, selectWinner } from "@ftf/scoring";
import { DEMO_EMPLOYEES, DEMO_TODAY } from "@/lib/demo-data";

export default function AdminOverview() {
  const rules = DEFAULT_RULES;
  const period = contestPeriod("monthly", DEMO_TODAY);
  const standings = computeStandings(DEMO_EMPLOYEES, period, rules, [], DEMO_TODAY);
  const pick = selectWinner({ standings, tier: "monthly", period, priorWins: [], rules });

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">Program admin</h1>
      <p className="text-sm text-zinc-500">
        Monthly contest {period.key}, standings as of {DEMO_TODAY} (sample data). Other-category points count up to{" "}
        {rules.contestOtherCaps.month ?? "unlimited"} per month.
      </p>

      <table className="w-full rounded-lg border border-zinc-200 bg-white text-sm">
        <thead className="bg-zinc-100 text-left">
          <tr>
            <th className="p-2">Rank</th>
            <th className="p-2">Employee</th>
            <th className="p-2 text-right">Daily</th>
            <th className="p-2 text-right">Other (counted / earned)</th>
            <th className="p-2 text-right">Total</th>
            <th className="p-2 text-right">Days with points</th>
            <th className="p-2 text-right">Perfect days</th>
          </tr>
        </thead>
        <tbody>
          {standings.map((s) => (
            <tr key={s.employeeId} className="border-t border-zinc-100">
              <td className="p-2">{s.rank}</td>
              <td className="p-2">{s.name}</td>
              <td className="p-2 text-right tabular-nums">{s.dailyPoints}</td>
              <td className="p-2 text-right tabular-nums">
                {s.otherPointsCounted} / {s.otherPointsEarned}
              </td>
              <td className="p-2 text-right font-medium tabular-nums">{s.total}</td>
              <td className="p-2 text-right tabular-nums">{s.daysWithAnyPoint}</td>
              <td className="p-2 text-right tabular-nums">{s.perfectDays}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <section className="rounded-lg border border-zinc-200 bg-white p-4 text-sm">
        <h2 className="mb-2 font-medium">Proposed winner</h2>
        <p>
          {pick.winner ? pick.winner.name : "No eligible employee"}
          {pick.needsAdminTieBreak && " (tied: needs an admin tie-break)"}
        </p>
        <p className="mt-1 text-zinc-600">Alternates: {pick.alternates.map((a) => a.name).join(", ") || "none"}</p>
      </section>
    </div>
  );
}
