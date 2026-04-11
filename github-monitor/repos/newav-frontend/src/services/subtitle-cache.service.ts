/**
 * Subtitle Cache Service
 * Manages IndexedDB storage for processed VTT subtitles
 */

import type { CorrectionInfo } from '@/workers/vtt-processor.worker';

interface CacheEntry {
  id: string; // Unique key: `${videoId}_${langCode}_${urlHash}`
  videoId: number;
  langCode: string;
  originalUrl: string;
  urlHash: string; // SHA256 hash of original URL
  processedVtt: string;
  correctionsMade: CorrectionInfo[];
  originalFileHash: string; // SHA256 hash of original VTT content
  timestamp: number;
  version: number;
}

interface CacheStats {
  totalEntries: number;
  totalSizeBytes: number;
  oldestEntry: number | null;
  newestEntry: number | null;
}

const DB_NAME = 'InsAV_SubtitleCache';
const STORE_NAME = 'processed_vtt';
const DB_VERSION = 1;
const CACHE_DURATION_DAYS = 7;

/**
 * Calculate SHA256 hash (simple version using SubtleCrypto)
 */
async function calculateHash(content: string): Promise<string> {
  try {
    const encoder = new TextEncoder();
    const data = encoder.encode(content);
    const hashBuffer = await crypto.subtle.digest('SHA-256', data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
  } catch {
    // Fallback for browsers without SubtleCrypto
    return simpleHash(content);
  }
}

/**
 * Simple fallback hash function
 */
function simpleHash(str: string): string {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    const char = str.charCodeAt(i);
    hash = (hash << 5) - hash + char;
    hash = hash & hash; // Convert to 32bit integer
  }
  return Math.abs(hash).toString(16);
}

/**
 * Initialize IndexedDB
 */
function initDB(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);

    request.onupgradeneeded = (event) => {
      const db = (event.target as IDBOpenDBRequest).result;

      // Create object store if it doesn't exist
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        const store = db.createObjectStore(STORE_NAME, { keyPath: 'id' });
        store.createIndex('videoId', 'videoId', { unique: false });
        store.createIndex('timestamp', 'timestamp', { unique: false });
        store.createIndex('urlHash', 'urlHash', { unique: false });
      }
    };
  });
}

/**
 * Generate cache key
 */
async function generateCacheKey(
  videoId: number,
  langCode: string,
  originalUrl: string,
): Promise<string> {
  const urlHash = await calculateHash(originalUrl);
  return `${videoId}_${langCode}_${urlHash}`;
}

/**
 * Check if cache is expired
 */
function isCacheExpired(timestamp: number): boolean {
  const now = Date.now();
  const ageMs = now - timestamp;
  const ageDays = ageMs / (1000 * 60 * 60 * 24);
  return ageDays > CACHE_DURATION_DAYS;
}

/**
 * Get cached processed VTT
 */
export async function getCachedSubtitle(
  videoId: number,
  langCode: string,
  originalUrl: string,
): Promise<{ vtt: string; corrections: CorrectionInfo[] } | null> {
  try {
    if (!indexedDB) return null;

    const db = await initDB();
    const cacheKey = await generateCacheKey(videoId, langCode, originalUrl);

    return new Promise((resolve) => {
      const request = db
        .transaction(STORE_NAME, 'readonly')
        .objectStore(STORE_NAME)
        .get(cacheKey);

      request.onsuccess = () => {
        const entry = request.result as CacheEntry | undefined;

        if (!entry) {
          resolve(null);
          return;
        }

        // Check if cache is expired
        if (isCacheExpired(entry.timestamp)) {
          // Don't return expired cache, but keep it for statistics
          resolve(null);
          return;
        }

        resolve({
          vtt: entry.processedVtt,
          corrections: entry.correctionsMade,
        });
      };

      request.onerror = () => {
        console.warn('Failed to retrieve from cache:', request.error);
        resolve(null);
      };
    });
  } catch (error) {
    console.warn('Cache retrieval error:', error);
    return null;
  }
}

/**
 * Store processed VTT in cache
 */
export async function cacheSubtitle(
  videoId: number,
  langCode: string,
  originalUrl: string,
  processedVtt: string,
  corrections: CorrectionInfo[],
  originalFileHash: string,
): Promise<boolean> {
  try {
    if (!indexedDB) return false;

    const db = await initDB();
    const cacheKey = await generateCacheKey(videoId, langCode, originalUrl);

    const entry: CacheEntry = {
      id: cacheKey,
      videoId,
      langCode,
      originalUrl,
      urlHash: cacheKey.split('_')[2], // Extract hash from key
      processedVtt,
      correctionsMade: corrections,
      originalFileHash,
      timestamp: Date.now(),
      version: 1,
    };

    return new Promise((resolve) => {
      const request = db
        .transaction(STORE_NAME, 'readwrite')
        .objectStore(STORE_NAME)
        .put(entry);

      request.onsuccess = () => resolve(true);
      request.onerror = () => {
        console.warn('Failed to cache subtitle:', request.error);
        resolve(false);
      };
    });
  } catch (error) {
    console.warn('Cache storage error:', error);
    return false;
  }
}

/**
 * Invalidate cache for a specific subtitle
 */
export async function invalidateCache(
  videoId: number,
  langCode: string,
  originalUrl: string,
): Promise<boolean> {
  try {
    if (!indexedDB) return false;

    const db = await initDB();
    const cacheKey = await generateCacheKey(videoId, langCode, originalUrl);

    return new Promise((resolve) => {
      const request = db
        .transaction(STORE_NAME, 'readwrite')
        .objectStore(STORE_NAME)
        .delete(cacheKey);

      request.onsuccess = () => resolve(true);
      request.onerror = () => {
        console.warn('Failed to invalidate cache:', request.error);
        resolve(false);
      };
    });
  } catch (error) {
    console.warn('Cache invalidation error:', error);
    return false;
  }
}

/**
 * Clear all expired cache entries
 */
export async function clearExpiredCache(): Promise<number> {
  try {
    if (!indexedDB) return 0;

    const db = await initDB();
    let deletedCount = 0;

    return new Promise((resolve) => {
      const request = db
        .transaction(STORE_NAME, 'readonly')
        .objectStore(STORE_NAME)
        .getAll();

      request.onsuccess = async () => {
        const entries = request.result as CacheEntry[];
        const expiredEntries = entries.filter(entry =>
          isCacheExpired(entry.timestamp),
        );

        if (expiredEntries.length === 0) {
          resolve(0);
          return;
        }

        const deleteTransaction = db.transaction(STORE_NAME, 'readwrite');
        const store = deleteTransaction.objectStore(STORE_NAME);

        for (const entry of expiredEntries) {
          store.delete(entry.id);
          deletedCount++;
        }

        deleteTransaction.oncomplete = () => resolve(deletedCount);
        deleteTransaction.onerror = () => resolve(deletedCount);
      };

      request.onerror = () => resolve(0);
    });
  } catch (error) {
    console.warn('Error clearing expired cache:', error);
    return 0;
  }
}

/**
 * Get cache statistics
 */
export async function getCacheStats(): Promise<CacheStats> {
  try {
    if (!indexedDB) {
      return {
        totalEntries: 0,
        totalSizeBytes: 0,
        oldestEntry: null,
        newestEntry: null,
      };
    }

    const db = await initDB();

    return new Promise((resolve) => {
      const request = db
        .transaction(STORE_NAME, 'readonly')
        .objectStore(STORE_NAME)
        .getAll();

      request.onsuccess = () => {
        const entries = request.result as CacheEntry[];

        let totalSizeBytes = 0;
        let oldestEntry: number | null = null;
        let newestEntry: number | null = null;

        for (const entry of entries) {
          // Rough size estimation
          totalSizeBytes += entry.processedVtt.length * 2; // UTF-16 encoding

          if (oldestEntry === null || entry.timestamp < oldestEntry) {
            oldestEntry = entry.timestamp;
          }
          if (newestEntry === null || entry.timestamp > newestEntry) {
            newestEntry = entry.timestamp;
          }
        }

        resolve({
          totalEntries: entries.length,
          totalSizeBytes,
          oldestEntry,
          newestEntry,
        });
      };

      request.onerror = () => {
        resolve({
          totalEntries: 0,
          totalSizeBytes: 0,
          oldestEntry: null,
          newestEntry: null,
        });
      };
    });
  } catch (error) {
    console.warn('Error getting cache stats:', error);
    return {
      totalEntries: 0,
      totalSizeBytes: 0,
      oldestEntry: null,
      newestEntry: null,
    };
  }
}

/**
 * Clear all cache (nuclear option)
 */
export async function clearAllCache(): Promise<boolean> {
  try {
    if (!indexedDB) return false;

    const db = await initDB();

    return new Promise((resolve) => {
      const request = db
        .transaction(STORE_NAME, 'readwrite')
        .objectStore(STORE_NAME)
        .clear();

      request.onsuccess = () => resolve(true);
      request.onerror = () => {
        console.warn('Failed to clear all cache:', request.error);
        resolve(false);
      };
    });
  } catch (error) {
    console.warn('Error clearing cache:', error);
    return false;
  }
}

/**
 * Get all cached subtitles for a video
 */
export async function getCachedSubtitlesByVideoId(
  videoId: number,
): Promise<CacheEntry[]> {
  try {
    if (!indexedDB) return [];

    const db = await initDB();

    return new Promise((resolve) => {
      const index = db
        .transaction(STORE_NAME, 'readonly')
        .objectStore(STORE_NAME)
        .index('videoId');

      const request = index.getAll(videoId);

      request.onsuccess = () => {
        const entries = request.result as CacheEntry[];
        resolve(entries);
      };

      request.onerror = () => {
        console.warn('Failed to retrieve cached entries:', request.error);
        resolve([]);
      };
    });
  } catch (error) {
    console.warn('Error retrieving cached entries:', error);
    return [];
  }
}
