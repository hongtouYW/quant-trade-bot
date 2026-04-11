/**
 * Image utility functions for handling banner thumbnails and base64 conversion
 */

const DEFAULT_IMAGE_DOMAIN = "https://ijp.moyicp.com/";

export type ImageErrorSource =
  | "transformBannerUrl"
  | "fetchBase64Image"
  | "getBannerImageSrc";

export interface ImageErrorLogEntry {
  message: string;
  source: ImageErrorSource;
  url: string;
  originalUrl?: string;
  timestamp: string;
  details?: string;
}

const imageErrorLog: ImageErrorLogEntry[] = [];

/**
 * Records image processing errors so the frontend can review and forward them to the backend.
 */
export const logImageError = ({
  error,
  source,
  url,
  originalUrl,
  details,
}: {
  error: unknown;
  source: ImageErrorSource;
  url: string;
  originalUrl?: string;
  details?: string;
}): void => {
  const message = error instanceof Error ? error.message : String(error);
  const entry: ImageErrorLogEntry = {
    message,
    source,
    url,
    originalUrl,
    timestamp: new Date().toISOString(),
    details,
  };

  imageErrorLog.push(entry);

  console.error(
    `[ImageError][${source}] url: ${url}${originalUrl ? ` (original: ${originalUrl})` : ""}`,
    error,
    details,
  );
};

export const getImageErrorLog = (): ImageErrorLogEntry[] => [...imageErrorLog];

export const clearImageErrorLog = (): void => {
  imageErrorLog.length = 0;
};

/**
 * Transforms a banner thumbnail URL to use the correct domain and append .txt
 * @param originalUrl - The original thumbnail URL
 * @returns Transformed URL with .txt extension
 */
export const transformBannerUrl = (originalUrl: string): string => {
  if (!originalUrl) return "";

  const envDomain =
    import.meta.env.VITE_STATIC_IMAGE_HOST || DEFAULT_IMAGE_DOMAIN;

  try {
    // Ensure the domain ends with a trailing slash
    const domain = envDomain.endsWith("/") ? envDomain : `${envDomain}/`;

    // Replace the original domain with the new domain
    const transformedUrl = originalUrl.replace(/https:\/\/[^/]+\//, domain).trim();

    // Append .txt to get base64 response
    return `${transformedUrl}.txt`;
  } catch (error) {
    logImageError({
      error,
      source: "transformBannerUrl",
      url: originalUrl,
      details: "Failed to transform URL",
    });
    return "";
  }
};

/**
 * Process base64 string - add data URL prefix and validate
 */
function processBase64(base64String: string): string {
  if (!base64String) {
    throw new Error("Empty response from server");
  }

  const cleanBase64 = base64String.trim();

  if (!cleanBase64) {
    throw new Error("Empty base64 string after cleanup");
  }

  // If it already starts with data:, return as is
  if (cleanBase64.startsWith("data:")) {
    return cleanBase64;
  }

  // Otherwise, assume it's a base64 string and add the data URL prefix
  return `data:image/jpeg;base64,${cleanBase64}`;
}

/**
 * Fetches and converts a transformed banner URL to a base64 image data URL
 * @param transformedUrl - The transformed URL that returns base64 string
 * @param options
 * @param signal - Optional AbortSignal for request cancellation
 * @returns Promise that resolves to base64 data URL or empty string on error
 */
export const fetchBase64Image = async (
  transformedUrl: string,
  options?: { originalUrl?: string },
  signal?: AbortSignal,
): Promise<string> => {
  if (!transformedUrl) return "";

  try {
    const response = await fetch(transformedUrl, { signal });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const base64String = await response.text();

    // Process base64 inline (minimal string operation)
    return processBase64(base64String);
  } catch (error) {
    logImageError({
      error,
      source: "fetchBase64Image",
      url: transformedUrl,
      originalUrl: options?.originalUrl,
    });
    return "";
  }
};

/**
 * Combines URL transformation and base64 fetching
 * @param originalUrl - The original thumbnail URL
 * @param signal - Optional AbortSignal for request cancellation
 * @returns Promise that resolves to base64 data URL
 */
export const getBannerImageSrc = async (
  originalUrl: string,
  signal?: AbortSignal,
): Promise<string> => {
  const transformedUrl = transformBannerUrl(originalUrl);
  return await fetchBase64Image(transformedUrl, { originalUrl }, signal);
};
