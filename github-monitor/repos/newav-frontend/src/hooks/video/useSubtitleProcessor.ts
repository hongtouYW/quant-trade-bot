/**
 * useSubtitleProcessor Hook
 * Manages subtitle processing with caching and WebWorker support
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import {
  getCachedSubtitle,
  cacheSubtitle,
} from '@/services/subtitle-cache.service';
import type { CorrectionInfo } from '@/workers/vtt-processor.worker';

interface UseSubtitleProcessorProps {
  subtitleUrl: string;
  videoId: number;
  langCode: string;
  enabled?: boolean;
}

interface UseSubtitleProcessorResult {
  processedUrl: string | null;
  isProcessing: boolean;
  error: Error | null;
  corrections: CorrectionInfo[];
  originalUrl: string;
  stats: {
    processingTimeMs: number;
    totalCues: number;
    correctedCues: number;
  };
}

// Global worker instance
let processorWorker: Worker | null = null;

/**
 * Initialize the worker (lazy initialization)
 */
function initializeWorker(): Worker {
  if (processorWorker) return processorWorker;

  // Create worker from the built worker file
  // Use relative path from hooks/video/ to src/workers/
  try {
    processorWorker = new Worker(
      new URL('../../workers/vtt-processor.worker.ts', import.meta.url),
      { type: 'module' }
    );
  } catch (error) {
    // Fallback: if module worker fails, try classic worker mode
    console.warn('Module worker failed, falling back to classic mode:', error);
    processorWorker = new Worker(
      new URL('../../workers/vtt-processor.worker.ts', import.meta.url)
    );
  }

  return processorWorker;
}

/**
 * Process VTT content using WebWorker
 */
interface ProcessorWorkerResult {
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

function processWithWorker(vttContent: string): Promise<ProcessorWorkerResult> {
  return new Promise((resolve, reject) => {
    try {
      const worker = initializeWorker();
      const messageId = Date.now() + Math.random();

      const timeout = setTimeout(() => {
        reject(new Error('VTT processing timeout (>30s)'));
      }, 30000); // 30 second timeout

      const handleMessage = (event: MessageEvent) => {
        if (event.data.id === messageId) {
          clearTimeout(timeout);
          worker.removeEventListener('message', handleMessage);
          resolve(event.data.result);
        }
      };

      worker.addEventListener('message', handleMessage);
      worker.postMessage({
        type: 'process',
        vttContent,
        id: messageId,
      });
    } catch (error) {
      reject(
        error instanceof Error
          ? error
          : new Error('Failed to initialize VTT processor'),
      );
    }
  });
}

/**
 * Custom hook for subtitle processing
 */
export function useSubtitleProcessor({
  subtitleUrl,
  videoId,
  langCode,
  enabled = true,
}: UseSubtitleProcessorProps): UseSubtitleProcessorResult {
  const [processedUrl, setProcessedUrl] = useState<string | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [error, setError] = useState<Error | null>(null);
  const [corrections, setCorrections] = useState<CorrectionInfo[]>([]);
  const [stats, setStats] = useState({
    processingTimeMs: 0,
    totalCues: 0,
    correctedCues: 0,
  });

  const blobUrlRef = useRef<string | null>(null);
  const processedRef = useRef(false);

  /**
   * Process subtitle
   */
  const processSubtitle = useCallback(async () => {
    if (!enabled || !subtitleUrl || processedRef.current) {
      return;
    }

    processedRef.current = true;
    setIsProcessing(true);
    setError(null);
    setCorrections([]);

    try {
      // Check cache first
      const cachedResult = await getCachedSubtitle(videoId, langCode, subtitleUrl);

      if (cachedResult) {
        // Use cached processed VTT
        const blob = new Blob([cachedResult.vtt], { type: 'text/vtt' });
        const url = URL.createObjectURL(blob);
        blobUrlRef.current = url;
        setProcessedUrl(url);
        setCorrections(cachedResult.corrections);
        setStats({
          processingTimeMs: 0, // From cache
          totalCues: 0,
          correctedCues: 0,
        });
        setIsProcessing(false);
        return;
      }

      // Fetch original VTT content with CORS support
      const response = await fetch(subtitleUrl, {
        method: 'GET',
        headers: {
          'Accept': 'text/vtt,application/json',
        },
        mode: 'cors',
        credentials: 'omit',
      });
      if (!response.ok) {
        throw new Error(
          `Failed to fetch subtitle: ${response.status} ${response.statusText}. URL: ${subtitleUrl}`,
        );
      }

      const originalVttContent = await response.text();

      if (!originalVttContent.trim()) {
        throw new Error('Subtitle file is empty');
      }

      // Calculate original file hash for cache validation
      const originalFileHash = await calculateHash(originalVttContent);

      // Process with WebWorker
      const result = await processWithWorker(originalVttContent);

      if (!result.success) {
        throw new Error(result.error || 'Unknown error during VTT processing');
      }

      // Cache the processed result
      await cacheSubtitle(
        videoId,
        langCode,
        subtitleUrl,
        result.correctedVtt,
        result.corrections,
        originalFileHash,
      );

      // Create blob URL from processed VTT
      const blob = new Blob([result.correctedVtt], { type: 'text/vtt' });
      const url = URL.createObjectURL(blob);
      blobUrlRef.current = url;

      setProcessedUrl(url);
      setCorrections(result.corrections);
      setStats({
        processingTimeMs: result.stats.processingTimeMs,
        totalCues: result.stats.totalCues,
        correctedCues: result.stats.correctedCues,
      });

      // Log corrections if any
      if (result.corrections.length > 0) {
        console.info(
          `[Subtitle Processor] Fixed ${result.corrections.length} issues in ${langCode} subtitle for video ${videoId}`,
          {
            corrections: result.corrections,
            stats: result.stats,
            processingTimeMs: result.stats.processingTimeMs,
          },
        );
      }
    } catch (err) {
      const errorObj = err instanceof Error ? err : new Error(String(err));
      setError(errorObj);
      console.error(
        `[Subtitle Processor] Error processing subtitle: ${errorObj.message}`,
        { videoId, langCode, subtitleUrl },
      );

      // Fallback to original URL on error
      setProcessedUrl(subtitleUrl);
    } finally {
      setIsProcessing(false);
    }
  }, [videoId, langCode, subtitleUrl, enabled]);

  // Effect to trigger processing
  useEffect(() => {
    processedRef.current = false;
    void processSubtitle();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [subtitleUrl, videoId, langCode, enabled]);

  // Cleanup blob URL on unmount
  useEffect(() => {
    return () => {
      if (blobUrlRef.current) {
        URL.revokeObjectURL(blobUrlRef.current);
      }
    };
  }, []);

  return {
    processedUrl: processedUrl || subtitleUrl, // Fallback to original URL
    isProcessing,
    error,
    corrections,
    originalUrl: subtitleUrl,
    stats,
  };
}

/**
 * Helper function to calculate hash
 */
async function calculateHash(content: string): Promise<string> {
  try {
    const encoder = new TextEncoder();
    const data = encoder.encode(content);
    const hashBuffer = await crypto.subtle.digest('SHA-256', data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
  } catch {
    // Fallback
    return simpleHash(content);
  }
}

/**
 * Simple hash fallback
 */
function simpleHash(str: string): string {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    const char = str.charCodeAt(i);
    hash = (hash << 5) - hash + char;
    hash = hash & hash;
  }
  return Math.abs(hash).toString(16);
}

export type { UseSubtitleProcessorResult, UseSubtitleProcessorProps };
