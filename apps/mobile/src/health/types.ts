/**
 * Contract between the phone and the server for wearable data.
 *
 * iPhone: an Apple Health adapter reads HealthKit on the device and uploads samples.
 * Android: Fitbit data is pulled server-side from the Fitbit Web API after the
 * employee connects their account, so the phone only shows sync status.
 */

export type SampleType = "steps" | "sleep" | "workout" | "water" | "weight";

export interface HealthSample {
  /** The provider's own id, so re-uploads are ignored server-side. */
  externalId: string;
  type: SampleType;
  value: number;
  start: string;
  end: string;
  sourceApp?: string;
  sourceDevice?: string;
  /** Typed into the Health app by hand. Uploaded for transparency, never scored. */
  userEntered: boolean;
  workoutKind?: string;
}

export interface SyncStatus {
  provider: "apple_health" | "fitbit";
  connected: boolean;
  lastSyncAt: string | null;
  lastError: string | null;
}

export interface HealthAdapter {
  provider: SyncStatus["provider"];
  isAvailable(): Promise<boolean>;
  requestPermissions(): Promise<boolean>;
  /** Samples changed since the last anchor; the adapter stores its own anchor. */
  readChanges(): Promise<HealthSample[]>;
}
