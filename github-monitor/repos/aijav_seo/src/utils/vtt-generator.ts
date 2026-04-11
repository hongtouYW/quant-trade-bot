import { useEffect, useState } from "react";

/**
 * Generate VTT content for video thumbnails
 */
interface VTTGeneratorOptions {
  spriteUrl: string; // URL or path to the sprite image
  videoDurationSeconds: number; // Total video duration in seconds
  spriteWidth?: number; // Total width of sprite image (default: 64000)
  thumbnailWidth?: number; // Width of each thumbnail (default: 640)
  thumbnailHeight?: number; // Height of each thumbnail (default: 360)
}

/**
 * Format seconds to VTT timestamp format (HH:MM:SS.mmm)
 */
function formatVTTTime(seconds: number): string {
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const secs = Math.floor(seconds % 60);
  const ms = Math.floor((seconds % 1) * 1000);

  return `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}:${String(secs).padStart(2, "0")}.${String(ms).padStart(3, "0")}`;
}

/**
 * Generate VTT content as a string
 */
export function generateVTTContent(options: VTTGeneratorOptions): string {
  const {
    spriteUrl,
    videoDurationSeconds,
    spriteWidth = 64000,
    thumbnailWidth = 640,
    thumbnailHeight = 360,
  } = options;

  // Calculate number of thumbnails
  const numThumbnails = Math.floor(spriteWidth / thumbnailWidth);

  // Calculate time interval for each thumbnail
  const interval = videoDurationSeconds / numThumbnails;

  let vtt = "WEBVTT\n\n";

  for (let i = 0; i < numThumbnails; i++) {
    const startTime = i * interval;
    const endTime = (i + 1) * interval;
    const xPosition = i * thumbnailWidth;

    vtt += `${formatVTTTime(startTime)} --> ${formatVTTTime(endTime)}\n`;
    vtt += `${spriteUrl}#xywh=${xPosition},0,${thumbnailWidth},${thumbnailHeight}\n\n`;
  }

  return vtt;
}

/**
 * Generate VTT as a Blob URL for use in video players
 */
export function generateVTTBlobUrl(options: VTTGeneratorOptions): string {
  const vttContent = generateVTTContent(options);
  const blob = new Blob([vttContent], { type: "text/vtt" });
  return URL.createObjectURL(blob);
}

/**
 * Generate VTT and download as file
 */
export function downloadVTT(
  options: VTTGeneratorOptions,
  filename = "thumbnails.vtt",
): void {
  const vttContent = generateVTTContent(options);
  const blob = new Blob([vttContent], { type: "text/vtt" });
  const url = URL.createObjectURL(blob);

  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

/**
 * React hook for generating VTT blob URL
 * Remember to clean up the blob URL when component unmounts!
 */
export function useVTTBlobUrl(
  options: VTTGeneratorOptions | null,
): string | null {
  const [blobUrl, setBlobUrl] = useState<string | null>(null);

  useEffect(() => {
    if (!options) {
      setBlobUrl(null);
      return;
    }

    const url = generateVTTBlobUrl(options);
    setBlobUrl(url);

    // Cleanup function to revoke blob URL
    return () => {
      URL.revokeObjectURL(url);
    };
  }, [
    // Use stable references - only recreate if the actual values change
    options?.spriteUrl,
    options?.videoDurationSeconds,
    // Use defaults if not provided to prevent unnecessary recreations
    options?.spriteWidth ?? 64000,
    options?.thumbnailWidth ?? 640,
    options?.thumbnailHeight ?? 360,
  ]);

  return blobUrl;
}
