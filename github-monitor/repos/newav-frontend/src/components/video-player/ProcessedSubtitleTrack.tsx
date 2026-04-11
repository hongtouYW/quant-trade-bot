/**
 * Processed Subtitle Track Component
 * Wraps the subtitle track with error correction and caching
 */

import { Track } from '@vidstack/react';
import { useSubtitleProcessor } from '@/hooks/video/useSubtitleProcessor';
import { getSubtitleLabel } from '@/utils/subtitle-utils';
import type { TFunction } from 'i18next';

interface ProcessedSubtitleTrackProps {
  langCode: string;
  url: string;
  videoId: number;
  default: boolean;
  t: TFunction;
}

export function ProcessedSubtitleTrack({
  langCode,
  url,
  videoId,
  default: isDefault,
  t,
}: ProcessedSubtitleTrackProps) {
  // Process the subtitle with error correction
  const { processedUrl, error } = useSubtitleProcessor({
    subtitleUrl: url,
    videoId,
    langCode,
    enabled: true,
  });

  // Log errors for debugging
  if (error) {
    console.warn(
      `[ProcessedSubtitleTrack] Error processing ${langCode} subtitles for video ${videoId}:`,
      error.message,
      'Falling back to original URL'
    );
  }

  // Use processed URL if available, fallback to original
  const finalUrl = processedUrl || url;

  return (
    <Track
      src={finalUrl}
      kind="subtitles"
      label={getSubtitleLabel(langCode, t)}
      type="vtt"
      default={isDefault}
    />
  );
}
