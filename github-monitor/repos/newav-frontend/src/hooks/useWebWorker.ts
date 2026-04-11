/**
 * React hook for Web Worker integration
 * Provides a simple interface to delegate work to Web Workers
 */

import { useCallback, useRef, useEffect } from "react";
import { WorkerPool, getWorkerPool } from "@/lib/worker-pool";

interface UseWebWorkerOptions {
  poolSize?: number;
  taskTimeout?: number;
}

export function useWebWorker<T, R>(
  workerScript: string,
  options?: UseWebWorkerOptions,
) {
  const poolRef = useRef<WorkerPool | null>(null);

  useEffect(() => {
    // Initialize worker pool on mount
    poolRef.current = getWorkerPool(workerScript, options?.poolSize);

    return () => {
      // Cleanup is handled by getWorkerPool singleton
      // Workers persist across component mounts for efficiency
    };
  }, [workerScript, options?.poolSize]);

  const run = useCallback(
    async (data: T): Promise<R> => {
      if (!poolRef.current) {
        throw new Error("Worker pool not initialized");
      }
      return poolRef.current.run<T, R>(data);
    },
    [],
  );

  const getStats = useCallback(() => {
    return poolRef.current?.getStats();
  }, []);

  return {
    run,
    getStats,
  };
}
