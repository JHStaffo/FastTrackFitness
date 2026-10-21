/**
 * Calendar helpers for local dates written as "YYYY-MM-DD".
 *
 * All scoring works on dates already converted to the company time zone, so
 * these helpers use UTC arithmetic purely as a calendar and never touch clocks.
 */

export type LocalDate = string;

export type PeriodKind = "week" | "month" | "quarter" | "year";

function toUtc(date: LocalDate): Date {
  const [y, m, d] = date.split("-").map(Number);
  if (!y || !m || !d) throw new Error(`Invalid date: ${date}`);
  return new Date(Date.UTC(y, m - 1, d));
}

function fromUtc(d: Date): LocalDate {
  return d.toISOString().slice(0, 10);
}

export function addDays(date: LocalDate, days: number): LocalDate {
  const d = toUtc(date);
  d.setUTCDate(d.getUTCDate() + days);
  return fromUtc(d);
}

/** 0 = Sunday ... 6 = Saturday */
export function dayOfWeek(date: LocalDate): number {
  return toUtc(date).getUTCDay();
}

/** Scoring weeks run Sunday through Saturday. */
export function weekStart(date: LocalDate): LocalDate {
  return addDays(date, -dayOfWeek(date));
}

export function daysBetweenInclusive(start: LocalDate, end: LocalDate): number {
  return Math.round((toUtc(end).getTime() - toUtc(start).getTime()) / 86_400_000) + 1;
}

export interface Period {
  kind: PeriodKind;
  /** Stable key, e.g. "W2027-01-03" (week starting that Sunday), "2027-01", "2027-Q1", "2027". */
  key: string;
  start: LocalDate;
  end: LocalDate;
}

export function periodFor(kind: PeriodKind, date: LocalDate): Period {
  const year = Number(date.slice(0, 4));
  const month = Number(date.slice(5, 7));
  switch (kind) {
    case "week": {
      const start = weekStart(date);
      return { kind, key: `W${start}`, start, end: addDays(start, 6) };
    }
    case "month": {
      const start = `${date.slice(0, 7)}-01`;
      const end = fromUtc(new Date(Date.UTC(year, month, 0)));
      return { kind, key: date.slice(0, 7), start, end };
    }
    case "quarter": {
      const q = Math.floor((month - 1) / 3);
      const start = fromUtc(new Date(Date.UTC(year, q * 3, 1)));
      const end = fromUtc(new Date(Date.UTC(year, q * 3 + 3, 0)));
      return { kind, key: `${year}-Q${q + 1}`, start, end };
    }
    case "year":
      return { kind, key: String(year), start: `${year}-01-01`, end: `${year}-12-31` };
  }
}

export function inPeriod(date: LocalDate, period: Pick<Period, "start" | "end">): boolean {
  return date >= period.start && date <= period.end;
}
