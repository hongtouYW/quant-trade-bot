/**
 * VTT Subtitle Validation Utility
 * Validates VTT files to prevent subtitle stacking and display issues
 */

export interface VTTCue {
  id?: string;
  startTime: number;
  endTime: number;
  text: string;
}

export interface VTTValidationResult {
  isValid: boolean;
  errors: string[];
  warnings: string[];
  cues: VTTCue[];
}

/**
 * Parse timestamp string to seconds
 */
function parseTimestamp(timestamp: string): number {
  const match = timestamp.match(/(\d{2}):(\d{2}):(\d{2})\.(\d{3})/);
  if (!match) throw new Error(`Invalid timestamp format: ${timestamp}`);
  
  const [, hours, minutes, seconds, milliseconds] = match;
  return (
    parseInt(hours) * 3600 +
    parseInt(minutes) * 60 +
    parseInt(seconds) +
    parseInt(milliseconds) / 1000
  );
}

/**
 * Validate VTT file content
 */
export function validateVTT(vttContent: string): VTTValidationResult {
  const result: VTTValidationResult = {
    isValid: true,
    errors: [],
    warnings: [],
    cues: [],
  };

  // Check if file starts with WEBVTT
  if (!vttContent.trim().startsWith('WEBVTT')) {
    result.errors.push('VTT file must start with "WEBVTT"');
    result.isValid = false;
  }

  // Split into blocks
  const blocks = vttContent.split(/\n\s*\n/);
  const cueBlocks = blocks.slice(1); // Skip WEBVTT header

  for (let i = 0; i < cueBlocks.length; i++) {
    const block = cueBlocks[i].trim();
    if (!block || block.startsWith('NOTE')) continue;

    const lines = block.split('\n');
    let cueId: string | undefined;
    let timingLine: string;
    let textLines: string[];

    // Check if first line is cue ID or timing
    if (lines[0].includes('-->')) {
      timingLine = lines[0];
      textLines = lines.slice(1);
    } else {
      cueId = lines[0];
      timingLine = lines[1];
      textLines = lines.slice(2);
    }

    // Validate timing line
    const timingMatch = timingLine.match(/(\d{2}:\d{2}:\d{2}\.\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2}\.\d{3})/);
    if (!timingMatch) {
      result.errors.push(`Invalid timing format in cue ${i + 1}: ${timingLine}`);
      result.isValid = false;
      continue;
    }

    const [, startTimeStr, endTimeStr] = timingMatch;
    
    try {
      const startTime = parseTimestamp(startTimeStr);
      const endTime = parseTimestamp(endTimeStr);

      // Validate timing logic
      if (startTime >= endTime) {
        result.errors.push(`Invalid timing in cue ${i + 1}: start time must be before end time`);
        result.isValid = false;
      }

      // Check for reasonable duration (not too short or too long)
      const duration = endTime - startTime;
      if (duration < 0.5) {
        result.warnings.push(`Very short duration (${duration}s) in cue ${i + 1}`);
      }
      if (duration > 10) {
        result.warnings.push(`Very long duration (${duration}s) in cue ${i + 1}`);
      }

      // Validate text content
      const text = textLines.join('\n');
      if (!text.trim()) {
        result.warnings.push(`Empty text in cue ${i + 1}`);
      }

      // Check for excessive text length
      if (text.length > 200) {
        result.warnings.push(`Very long text (${text.length} chars) in cue ${i + 1} may cause display issues`);
      }

      // Check for overlapping with previous cue
      const lastCue = result.cues[result.cues.length - 1];
      if (lastCue && startTime < lastCue.endTime) {
        result.errors.push(`Overlapping timestamps: cue ${i + 1} starts before previous cue ends`);
        result.isValid = false;
      }

      result.cues.push({
        id: cueId,
        startTime,
        endTime,
        text,
      });

    } catch (error) {
      result.errors.push(`Error parsing cue ${i + 1}: ${error instanceof Error ? error.message : 'Unknown error'}`);
      result.isValid = false;
    }
  }

  return result;
}

/**
 * Fetch and validate VTT file from URL
 */
export async function fetchAndValidateVTT(url: string): Promise<VTTValidationResult> {
  try {
    const response = await fetch(url);
    if (!response.ok) {
      return {
        isValid: false,
        errors: [`Failed to fetch VTT file: ${response.status} ${response.statusText}`],
        warnings: [],
        cues: [],
      };
    }

    const vttContent = await response.text();
    return validateVTT(vttContent);
  } catch (error) {
    return {
      isValid: false,
      errors: [`Network error: ${error instanceof Error ? error.message : 'Unknown error'}`],
      warnings: [],
      cues: [],
    };
  }
}

/**
 * Clean and fix common VTT issues
 */
export function cleanVTT(vttContent: string): string {
  let cleaned = vttContent.trim();

  // Ensure WEBVTT header
  if (!cleaned.startsWith('WEBVTT')) {
    cleaned = 'WEBVTT\n\n' + cleaned;
  }

  // Fix line endings and ensure proper spacing
  cleaned = cleaned
    .replace(/\r\n/g, '\n')
    .replace(/\r/g, '\n')
    .replace(/\n{3,}/g, '\n\n'); // Reduce multiple newlines to double

  // Split into blocks and clean each
  const blocks = cleaned.split(/\n\s*\n/);
  const cleanedBlocks = blocks.map(block => block.trim()).filter(block => block);

  return cleanedBlocks.join('\n\n');
}