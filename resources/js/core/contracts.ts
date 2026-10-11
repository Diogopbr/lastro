export type SourceKind = 'orders' | 'payouts';
export type Classification = 'matched' | 'amountMismatch' | 'missingPayout' | 'missingOrder';
export type ReviewStatus = 'pending' | 'reviewed' | null;
export interface SourceProjection {
  id: string; kind: SourceKind; normalizedHash: string; displayFilename: string;
  rowCount: number; grossTotal: string; confirmedAt: string;
}
export interface ConferenceProjection {
  id: string; code: string; name: string; startDate: string; endDate: string;
  version: number; createdAt: string; updatedAt: string;
  sources: { orders: SourceProjection | null; payouts: SourceProjection | null };
  run: { id: string; createdAt: string } | null;
  progress: { divergences: number; reviewed: number } | null;
}
export interface ImportRow {
  line: number; orderId: string; payoutId: string | null; businessDate: string;
  gross: string; fee: string | null; net: string | null;
}
export interface ImportPreview {
  valid: boolean; normalizedHash: string | null; rowCount: number; grossTotal: string;
  rows: ImportRow[]; errors: { line: number; field: string; code: string; message: string }[];
}
export interface SourceRowProjection extends ImportRow {
  sourceId: string; normalizedHash: string; displayFilename: string;
}
export interface ReviewEvent {
  id: string; action: 'reviewed' | 'reopened'; note: string; actorName: string; createdAt: string;
}
export interface ResultProjection {
  id: string; orderId: string; classification: Classification; difference: string | null;
  reviewStatus: ReviewStatus; version: number;
  order: SourceRowProjection | null; payout: SourceRowProjection | null;
  reviewEvents: ReviewEvent[];
}
export interface RunSummary {
  counts: Record<Classification, number>; orderGross: string; payoutGross: string;
  fees: string; net: string; divergences: number; reviewed: number;
}
export interface RunProjection {
  id: string; conferenceId: string; createdAt: string; summary: RunSummary;
}
export interface PageOfResults {
  items: ResultProjection[]; total: number; page: number; pageSize: number;
}
export interface AuthProps {
  user: { id: string; displayName: string } | null;
  workspace: { id: string; kind: 'normal' | 'demo'; expiresAt: string | null } | null;
  demoEnabled: boolean;
}
export interface SharedProps {
  auth: AuthProps;
  flash: { success: string | null };
  [key: string]: unknown;
}
export interface ApiError {
  code: string; message: string; errors?: Record<string, string[]>;
}
