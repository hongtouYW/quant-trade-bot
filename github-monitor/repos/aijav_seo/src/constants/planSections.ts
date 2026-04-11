export const PLAN_STACKED_SECTION_IDS = {
  vip: 1,
  points: 2,
  diamonds: 3,
} as const;

export type PlanStackedSectionKey =
  keyof typeof PLAN_STACKED_SECTION_IDS;

export type PlanStackedSectionId =
  (typeof PLAN_STACKED_SECTION_IDS)[PlanStackedSectionKey];
