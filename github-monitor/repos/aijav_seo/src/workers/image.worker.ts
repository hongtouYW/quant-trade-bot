/**
 * Image Processing Web Worker
 * Handles base64 conversion and image URL transformations off the main thread
 */

interface ImageProcessingMessage {
  id: string;
  data: {
    type: "transformUrl" | "processBase64" | "validateBase64";
    url?: string;
    base64String?: string;
    domain?: string;
  };
}

const DEFAULT_IMAGE_DOMAIN = "https://ijp.moyicp.com/";
const ORIGINAL_DOMAINS = [
  "https://mmjs.1vkx.cn/",
  "https://ijp.moyicp.com/", // Also handle if already transformed
];

/**
 * Transform banner URL to use correct domain and append .txt
 */
function transformBannerUrl(originalUrl: string, customDomain?: string): string {
  if (!originalUrl) return "";

  const domain = customDomain || DEFAULT_IMAGE_DOMAIN;
  const domainWithSlash = domain.endsWith("/") ? domain : `${domain}/`;

  // Try to replace with old domains
  let transformedUrl = originalUrl;
  for (const oldDomain of ORIGINAL_DOMAINS) {
    if (originalUrl.includes(oldDomain)) {
      transformedUrl = originalUrl.replace(oldDomain, domainWithSlash).trim();
      break;
    }
  }

  // If no domain was found to replace, ensure it uses the new domain
  if (transformedUrl === originalUrl) {
    transformedUrl = originalUrl.trim();
  }

  return `${transformedUrl}.txt`;
}

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

  if (cleanBase64.startsWith("data:")) {
    return cleanBase64;
  }

  return `data:image/jpeg;base64,${cleanBase64}`;
}

/**
 * Validate base64 string format
 */
function validateBase64(str: string): boolean {
  if (!str) return false;
  const base64Regex = /^[A-Za-z0-9+/]*={0,2}$/;
  return base64Regex.test(str.trim());
}

/**
 * Main worker message handler
 */
self.onmessage = (event: MessageEvent<ImageProcessingMessage>): void => {
  const { id, data } = event.data;

  try {
    let result: string | boolean;

    switch (data.type) {
      case "transformUrl": {
        if (!data.url) {
          throw new Error("URL is required for transformUrl");
        }
        result = transformBannerUrl(data.url, data.domain);
        break;
      }

      case "processBase64": {
        if (!data.base64String) {
          throw new Error("Base64 string is required for processBase64");
        }
        result = processBase64(data.base64String);
        break;
      }

      case "validateBase64": {
        if (!data.base64String) {
          throw new Error("Base64 string is required for validateBase64");
        }
        result = validateBase64(data.base64String);
        break;
      }

      default: {
        throw new Error(`Unknown operation type: ${(data as any).type}`);
      }
    }

    self.postMessage({ id, result });
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : String(error);
    self.postMessage({ id, error: errorMessage });
  }
};
