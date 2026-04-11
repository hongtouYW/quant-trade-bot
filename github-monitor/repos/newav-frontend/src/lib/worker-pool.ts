/**
 * Worker Pool Manager
 * Manages a pool of Web Workers to handle CPU-intensive tasks
 * Prevents spawning excessive workers and reuses existing ones
 */

interface WorkerTask<T, R = unknown> {
  id: string;
  data: T;
  resolve: (value: R) => void;
  reject: (error: Error) => void;
  timeout: NodeJS.Timeout;
}

export class WorkerPool {
  private workers: Worker[] = [];
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  private taskQueue: WorkerTask<any, any>[] = [];
  private activeTasksCount = 0;
  private workerScript: string;
  private poolSize: number;
  private taskTimeout: number;

  constructor(
    workerScript: string,
    poolSize: number = 2,
    taskTimeout: number = 30000,
  ) {
    this.workerScript = workerScript;
    // Use 2-4 workers, not more than CPU cores
    this.poolSize = Math.min(
      poolSize,
      Math.max(2, navigator.hardwareConcurrency ? navigator.hardwareConcurrency / 2 : 2),
    );
    this.taskTimeout = taskTimeout;
    this.initializeWorkers();
  }

  private initializeWorkers(): void {
    for (let i = 0; i < this.poolSize; i++) {
      try {
        const worker = new Worker(new URL(this.workerScript, import.meta.url), {
          type: "module",
        });
        worker.onmessage = (event) => this.handleWorkerMessage(worker, event);
        worker.onerror = (error) => this.handleWorkerError(worker, error);
        this.workers.push(worker);
      } catch (error) {
        console.error(`Failed to initialize worker ${i}:`, error);
      }
    }
  }

  private handleWorkerMessage(
    _worker: Worker,
    event: MessageEvent<{ id: string; result: unknown; error?: string }>,
  ): void {
    const { id, result, error } = event.data;

    const taskIndex = this.taskQueue.findIndex((task) => task.id === id);
    if (taskIndex === -1) return;

    const task = this.taskQueue.splice(taskIndex, 1)[0];
    clearTimeout(task.timeout);

    if (error) {
      task.reject(new Error(error));
    } else {
      task.resolve(result);
    }

    this.activeTasksCount--;
    this.processPendingTasks();
  }

  private handleWorkerError(_worker: Worker, error: ErrorEvent): void {
    console.error("Worker error:", error.message);
  }

  private processPendingTasks(): void {
    while (
      this.taskQueue.length > 0 &&
      this.activeTasksCount < this.workers.length
    ) {
      const task = this.taskQueue.shift();
      if (task) {
        this.executeTask(task);
      }
    }
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  private executeTask(task: WorkerTask<any, any>): void {
    if (this.workers.length === 0) {
      task.reject(new Error("No workers available"));
      return;
    }

    const workerIndex = this.activeTasksCount % this.workers.length;
    const worker = this.workers[workerIndex];

    this.activeTasksCount++;
    worker.postMessage({ id: task.id, data: task.data });
  }

  public async run<T, R>(data: T): Promise<R> {
    return new Promise((resolve, reject) => {
      const taskId = `${Date.now()}-${Math.random()}`;
      const timeout = setTimeout(() => {
        const taskIndex = this.taskQueue.findIndex((t) => t.id === taskId);
        if (taskIndex !== -1) {
          this.taskQueue.splice(taskIndex, 1);
        }
        this.activeTasksCount--;
        reject(
          new Error(`Task ${taskId} timed out after ${this.taskTimeout}ms`),
        );
      }, this.taskTimeout);

      const task: WorkerTask<T, R> = {
        id: taskId,
        data,
        resolve,
        reject,
        timeout,
      };

      this.taskQueue.push(task);
      this.processPendingTasks();
    });
  }

  public terminate(): void {
    this.workers.forEach((worker) => worker.terminate());
    this.workers = [];
    this.taskQueue = [];
  }

  public getStats() {
    return {
      poolSize: this.poolSize,
      activeWorkers: this.workers.length,
      queuedTasks: this.taskQueue.length,
      activeTasks: this.activeTasksCount,
    };
  }
}

const workerPools = new Map<string, WorkerPool>();

export function getWorkerPool(
  workerScript: string,
  poolSize?: number,
): WorkerPool {
  if (!workerPools.has(workerScript)) {
    workerPools.set(workerScript, new WorkerPool(workerScript, poolSize));
  }
  return workerPools.get(workerScript)!;
}

export function terminateAllWorkerPools(): void {
  workerPools.forEach((pool) => pool.terminate());
  workerPools.clear();
}
