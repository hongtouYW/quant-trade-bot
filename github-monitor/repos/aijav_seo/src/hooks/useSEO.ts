export const SITE_NAME = "AI JAV";
export const DEFAULT_DESCRIPTION =
  "AI JAV 提供高清日本成人视频在线观看，海量资源每日更新，支持多语言字幕，VIP专享内容。";
export const BASE_URL: string =
  import.meta.env.VITE_SEO_BASE_URL || "https://aij-av.com";

/**
 * Convert duration string like "01:30:45" to ISO 8601 format "PT1H30M45S"
 */
export function durationToISO8601(duration: string): string {
  if (!duration) return "PT0S";

  const parts = duration.split(":").map(Number);
  let hours = 0,
    minutes = 0,
    seconds = 0;

  if (parts.length === 3) {
    [hours, minutes, seconds] = parts;
  } else if (parts.length === 2) {
    [minutes, seconds] = parts;
  } else if (parts.length === 1) {
    [seconds] = parts;
  }

  let iso = "PT";
  if (hours > 0) iso += `${hours}H`;
  if (minutes > 0) iso += `${minutes}M`;
  if (seconds > 0) iso += `${seconds}S`;

  return iso === "PT" ? "PT0S" : iso;
}
