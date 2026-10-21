import { periodFor, type LocalDate } from "./dates";
import type { OtherCategory, RuleSet } from "./rules";

export interface OtherEvent {
  id: string;
  date: LocalDate;
  category: OtherCategory;
  /** For categories that need a document: true once an admin approved it. */
  documentApproved?: boolean;
}

export interface OtherAward {
  eventId: string;
  date: LocalDate;
  category: OtherCategory;
  points: number;
  /** Why the event earned fewer points than its full value, if it did. */
  limitedBy?: "categoryLimit" | "awaitingDocument";
}

/**
 * Awards other-category points in date order, enforcing each category's
 * limit (for example dentist: 185 per visit, at most 370 per year).
 * Pass the employee's full history so yearly limits see earlier events.
 */
export function scoreOtherEvents(events: OtherEvent[], rules: RuleSet): OtherAward[] {
  const usedByBucket = new Map<string, number>();
  const sorted = [...events].sort((a, b) => a.date.localeCompare(b.date) || a.id.localeCompare(b.id));

  return sorted.map((e) => {
    const rule = rules.other[e.category];
    if (rule.requiresDocument && !e.documentApproved) {
      return { eventId: e.id, date: e.date, category: e.category, points: 0, limitedBy: "awaitingDocument" };
    }
    const bucket = `${e.category}:${periodFor(rule.limitPeriod, e.date).key}`;
    const used = usedByBucket.get(bucket) ?? 0;
    const points = Math.max(0, Math.min(rule.pointsPerEvent, rule.maxPointsPerPeriod - used));
    usedByBucket.set(bucket, used + points);
    const award: OtherAward = { eventId: e.id, date: e.date, category: e.category, points };
    if (points < rule.pointsPerEvent) award.limitedBy = "categoryLimit";
    return award;
  });
}
