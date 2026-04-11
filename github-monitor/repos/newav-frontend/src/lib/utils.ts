import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function formatDurationToMinutes(
  duration: string,
  t?: (key: string, options?: any) => string,
): string {
  const [hours, minutes, seconds] = duration.split(":").map(Number);
  const totalMinutes = hours * 60 + minutes + Math.round(seconds / 60);

  if (t) {
    return t("duration.minutes", { count: totalMinutes });
  }

  return `${totalMinutes}分钟`;
}

export function getObjectPosition(
  position?: "right" | "left" | "centre",
): string {
  switch (position) {
    case "left":
      return "object-left";
    case "right":
      return "object-right";
    case "centre":
      return "object-center";
    default:
      return "object-right";
  }
}
