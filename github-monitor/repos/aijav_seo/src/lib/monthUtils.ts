import type { Month } from "@/pages/Latest";

/**
 * Generates an array of months going back 5 years from the current month
 * @param t - i18next translation function
 * @returns Array of Month objects with translated labels and YYYY-MM values
 */
export function generateMonthlyFilters(t: (key: string) => string): Month[] {
  const monthKeys = [
    "january",
    "february",
    "march",
    "april",
    "may",
    "june",
    "july",
    "august",
    "september",
    "october",
    "november",
    "december",
  ];

  const currentDate = new Date();
  const currentYear = currentDate.getFullYear();
  const currentMonth = currentDate.getMonth(); // 0-indexed (0 = January)

  const filters: Month[] = [];

  // Generate 60 months (5 years) going backwards from current month
  for (let i = 0; i < 120; i++) {
    // Calculate the year and month for this iteration
    const monthsAgo = currentMonth - i;
    const year = currentYear + Math.floor(monthsAgo / 12);
    const monthIndex = ((monthsAgo % 12) + 12) % 12; // Handle negative modulo

    const monthValue = (monthIndex + 1).toString().padStart(2, "0");
    const monthKey = monthKeys[monthIndex];

    // First month (current month) gets special label
    const label =
      i === 0
        ? t("latest.updated_month")
        : `${year} ${t(`latest.months.${monthKey}`)}`;

    filters.push({
      label,
      value: `${year}-${monthValue}`,
    });
  }

  return filters;
}

/**
 * Get the current month value in YYYY-MM format
 */
export function getCurrentMonthValue(): string {
  const currentDate = new Date();
  const currentYear = currentDate.getFullYear();
  const currentMonth = currentDate.getMonth(); // 0-indexed (0 = January)
  const monthValue = (currentMonth + 1).toString().padStart(2, "0");
  return `${currentYear}-${monthValue}`;
}
