import { addDays, type DayActivity, type EmployeeHistory } from "@ftf/scoring";

// Placeholder data until the database and wearable sync are connected.
export const DEMO_TODAY = "2027-01-13";

function day(date: string, overrides: Partial<DayActivity> = {}): DayActivity {
  return {
    date,
    mealsLogged: 3,
    sleepMinutes: 7 * 60,
    workouts: [{ start: `${date}T17:00:00Z`, end: `${date}T17:40:00Z` }],
    steps: 11_000,
    waterOz: 70,
    mealPlanConfirmed: true,
    ...overrides,
  };
}

function history(id: string, name: string, tweak: (i: number) => Partial<DayActivity>): EmployeeHistory {
  return {
    employeeId: id,
    name,
    enrolledFrom: "2027-01-01",
    days: Array.from({ length: 13 }, (_, i) => day(addDays("2027-01-01", i), tweak(i))),
    otherEvents: [],
  };
}

export const DEMO_EMPLOYEES: EmployeeHistory[] = [
  history("e1", "Alex Rivera", () => ({})),
  history("e2", "Sam Patel", (i) => (i % 3 === 0 ? { steps: 6_000 } : {})),
  history("e3", "Jordan Lee", (i) => (i % 2 === 0 ? { waterOz: 30, mealsLogged: 2 } : {})),
  {
    ...history("e4", "Casey Brooks", (i) => ({ sleepMinutes: i % 4 === 0 ? 300 : 420 })),
    otherEvents: [{ id: "v1", date: "2027-01-08", category: "dentist", documentApproved: true }],
  },
];

export const DEMO_ME = DEMO_EMPLOYEES[1]!;
