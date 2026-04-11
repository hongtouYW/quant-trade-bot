/**
 * VTT Processor Web Worker
 * Handles heavy VTT parsing, validation, and correction operations off the main thread
 */

interface VTTCue {
  startTime: number;
  endTime: number;
  text: string;
  id?: string;
}

interface CorrectionInfo {
  type: 'overlap' | 'invalid_timing' | 'backward_timing' | 'empty_text' | 'duration_adjustment';
  cueIndex: number;
  original?: {
    startTime?: number;
    endTime?: number;
    text?: string;
  };
  corrected?: {
    startTime?: number;
    endTime?: number;
    text?: string;
  };
  action: string;
  details?: string;
}

interface ProcessorResult {
  success: boolean;
  correctedVtt: string;
  corrections: CorrectionInfo[];
  stats: {
    totalCues: number;
    correctedCues: number;
    processingTimeMs: number;
  };
  error?: string;
}

interface WorkerMessage {
  type: 'process' | 'validate';
  vttContent: string;
  id?: string;
}

// Constants
const MIN_DURATION = 0.5; // Minimum subtitle duration in seconds
const MIN_GAP = 0.1; // Minimum gap between cues
const CHAR_PER_SECOND = 20; // For estimating duration from text length

/**
 * Parse timestamp string to seconds
 */
function parseTimestamp(timestamp: string): number {
  const match = timestamp.match(/(\d{1,2}):(\d{2}):(\d{2})[\.,](\d{3})/);
  if (!match) {
    throw new Error(`Invalid timestamp format: ${timestamp}`);
  }

  const [, hours, minutes, seconds, milliseconds] = match;
  return (
    parseInt(hours) * 3600 +
    parseInt(minutes) * 60 +
    parseInt(seconds) +
    parseInt(milliseconds) / 1000
  );
}

/**
 * Format seconds to VTT timestamp format
 */
function formatTimestamp(seconds: number): string {
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const secs = Math.floor(seconds % 60);
  const ms = Math.round((seconds % 1) * 1000);

  return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}.${String(ms).padStart(3, '0')}`;
}

/**
 * Estimate subtitle duration from text length
 */
function estimateDuration(text: string): number {
  // Remove HTML tags for length calculation
  const cleanText = text.replace(/<[^>]*>/g, '').trim();
  const charCount = cleanText.length;
  const estimatedDuration = charCount / CHAR_PER_SECOND;
  return Math.max(estimatedDuration, MIN_DURATION);
}

/**
 * Parse VTT content into cues
 */
function parseVTTContent(vttContent: string): VTTCue[] {
  const cues: VTTCue[] = [];

  // Remove BOM if present
  let content = vttContent.replace(/^\ufeff/, '');

  // Normalize line endings
  content = content.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

  // Split into blocks
  const blocks = content.split(/\n\s*\n/);

  for (let i = 0; i < blocks.length; i++) {
    const block = blocks[i].trim();

    // Skip empty blocks and header
    if (!block || i === 0 || block.startsWith('NOTE') || block.startsWith('WEBVTT')) {
      continue;
    }

    const lines = block.split('\n').map(l => l.trim()).filter(l => l);
    if (lines.length < 2) continue;

    let cueId: string | undefined;
    let timingLine: string;
    let textLines: string[];

    // Determine if first line is cue ID or timing
    if (lines[0].includes('-->')) {
      timingLine = lines[0];
      textLines = lines.slice(1);
    } else {
      cueId = lines[0];
      timingLine = lines[1];
      textLines = lines.slice(2);
    }

    try {
      // Parse timing line - handle both --> and -- > variations
      const timingMatch = timingLine.match(/([0-9:.,]+)\s*-->\s*([0-9:.,]+)/);

      if (!timingMatch) {
        console.warn(`Invalid timing format: ${timingLine}`);
        continue;
      }

      const startTime = parseTimestamp(timingMatch[1]);
      const endTime = parseTimestamp(timingMatch[2]);
      const text = textLines.join('\n');

      if (text.trim()) {
        cues.push({
          id: cueId,
          startTime,
          endTime,
          text,
        });
      }
    } catch (error) {
      console.warn(`Failed to parse cue: ${error instanceof Error ? error.message : 'Unknown error'}`);
      continue;
    }
  }

  return cues;
}

/**
 * Detect and fix timing issues
 */
function correctTimings(cues: VTTCue[]): { correctedCues: VTTCue[]; corrections: CorrectionInfo[] } {
  const corrections: CorrectionInfo[] = [];
  const correctedCues: VTTCue[] = [];

  for (let i = 0; i < cues.length; i++) {
    const cue = { ...cues[i] };
    const original = { ...cue };

    // Fix 1: Invalid timing (end before start)
    if (cue.startTime > cue.endTime) {
      corrections.push({
        type: 'backward_timing',
        cueIndex: i,
        original: { startTime: original.startTime, endTime: original.endTime },
        corrected: { startTime: cue.endTime, endTime: cue.startTime },
        action: 'Swapped start and end times',
      });
      [cue.startTime, cue.endTime] = [cue.endTime, cue.startTime];
    }

    // Fix 2: Equal start and end times
    if (cue.startTime === cue.endTime) {
      const estimatedDuration = estimateDuration(cue.text);
      corrections.push({
        type: 'invalid_timing',
        cueIndex: i,
        original: { endTime: original.endTime },
        corrected: { endTime: cue.startTime + estimatedDuration },
        action: `Extended end time by ${estimatedDuration.toFixed(2)}s based on text length`,
      });
      cue.endTime = cue.startTime + estimatedDuration;
    }

    // Fix 3: Duration too short
    const duration = cue.endTime - cue.startTime;
    if (duration < MIN_DURATION) {
      const newEndTime = cue.startTime + MIN_DURATION;
      corrections.push({
        type: 'duration_adjustment',
        cueIndex: i,
        original: { endTime: original.endTime },
        corrected: { endTime: newEndTime },
        action: `Extended duration from ${duration.toFixed(2)}s to ${MIN_DURATION}s (minimum)`,
      });
      cue.endTime = newEndTime;
    }

    // Fix 4: Overlapping with previous cue
    if (correctedCues.length > 0) {
      const prevCue = correctedCues[correctedCues.length - 1];
      if (cue.startTime < prevCue.endTime) {
        // Add minimum gap
        const gap = MIN_GAP;
        const newStartTime = prevCue.endTime + gap;

        // Check if this creates invalid timing
        if (newStartTime >= cue.endTime) {
          // Shift both start and end
          const shift = newStartTime - cue.startTime;
          corrections.push({
            type: 'overlap',
            cueIndex: i,
            original: { startTime: original.startTime, endTime: original.endTime },
            corrected: { startTime: cue.startTime + shift, endTime: cue.endTime + shift },
            action: `Shifted cue by ${shift.toFixed(2)}s to prevent overlap with previous cue`,
            details: `Previous cue ends at ${formatTimestamp(prevCue.endTime)}`,
          });
          cue.startTime += shift;
          cue.endTime += shift;
        } else {
          // Just adjust start time
          corrections.push({
            type: 'overlap',
            cueIndex: i,
            original: { startTime: original.startTime },
            corrected: { startTime: newStartTime },
            action: `Adjusted start time to ${formatTimestamp(newStartTime)} to prevent overlap`,
            details: `Previous cue ends at ${formatTimestamp(prevCue.endTime)}`,
          });
          cue.startTime = newStartTime;
        }
      }
    }

    // Fix 5: Empty text
    if (!cue.text.trim()) {
      corrections.push({
        type: 'empty_text',
        cueIndex: i,
        action: 'Skipped cue with empty text',
      });
      continue;
    }

    correctedCues.push(cue);
  }

  return { correctedCues, corrections };
}

/**
 * Generate VTT content from cues
 */
function generateVTT(cues: VTTCue[]): string {
  let vtt = 'WEBVTT\n\n';

  for (const cue of cues) {
    if (cue.id) {
      vtt += `${cue.id}\n`;
    }
    vtt += `${formatTimestamp(cue.startTime)} --> ${formatTimestamp(cue.endTime)}\n`;
    vtt += `${cue.text}\n\n`;
  }

  return vtt;
}

/**
 * Main processing function
 */
function processVTT(vttContent: string): ProcessorResult {
  const startTime = performance.now();

  try {
    // Parse VTT content
    const cues = parseVTTContent(vttContent);

    if (cues.length === 0) {
      return {
        success: true,
        correctedVtt: vttContent,
        corrections: [],
        stats: {
          totalCues: 0,
          correctedCues: 0,
          processingTimeMs: performance.now() - startTime,
        },
      };
    }

    // Correct timings
    const { correctedCues, corrections } = correctTimings(cues);

    // Generate corrected VTT
    const correctedVtt = generateVTT(correctedCues);

    const processingTimeMs = performance.now() - startTime;

    return {
      success: true,
      correctedVtt,
      corrections,
      stats: {
        totalCues: cues.length,
        correctedCues: correctedCues.length,
        processingTimeMs: Math.round(processingTimeMs * 100) / 100,
      },
    };
  } catch (error) {
    const processingTimeMs = performance.now() - startTime;
    return {
      success: false,
      correctedVtt: vttContent,
      corrections: [],
      stats: {
        totalCues: 0,
        correctedCues: 0,
        processingTimeMs: Math.round(processingTimeMs * 100) / 100,
      },
      error: error instanceof Error ? error.message : 'Unknown error during VTT processing',
    };
  }
}

/**
 * Worker message handler
 */
self.onmessage = (event: MessageEvent<WorkerMessage>) => {
  const { type, vttContent, id } = event.data;

  if (type === 'process') {
    const result = processVTT(vttContent);
    self.postMessage({
      id,
      result,
    });
  }
};

export type { ProcessorResult, CorrectionInfo, VTTCue, WorkerMessage };
