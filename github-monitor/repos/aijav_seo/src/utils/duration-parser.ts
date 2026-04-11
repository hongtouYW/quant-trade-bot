/**
 * Parse duration string to seconds
 * Supports formats: "HH:MM:SS", "MM:SS", "SS"
 *
 * @param duration - Duration string (e.g., "02:15:10", "15:30", "45")
 * @returns Total duration in seconds
 *
 * @example
 * parseDurationToSeconds("02:15:10") // 8110 (2h 15m 10s)
 * parseDurationToSeconds("15:30")    // 930 (15m 30s)
 * parseDurationToSeconds("45")       // 45 (45s)
 * parseDurationToSeconds("")         // 0
 */
export function parseDurationToSeconds(duration: string): number {
  if (!duration || duration.trim() === "") {
    return 0;
  }

  const parts = duration.split(":").map((part) => parseInt(part.trim(), 10));

  // Filter out NaN values
  const validParts = parts.filter((part) => !isNaN(part));

  if (validParts.length === 0) {
    return 0;
  }

  // Handle different formats
  if (validParts.length === 3) {
    // HH:MM:SS
    const [hours, minutes, seconds] = validParts;
    return hours * 3600 + minutes * 60 + seconds;
  } else if (validParts.length === 2) {
    // MM:SS
    const [minutes, seconds] = validParts;
    return minutes * 60 + seconds;
  } else {
    // SS (single number)
    return validParts[0];
  }
}
