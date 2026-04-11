<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\facade\Log;

class DeletePlayLog extends Command
{
    protected function configure()
    {
        $this->setName('delete:play_log')
            ->setDescription('Delete old play log records (3 months before) with limit');
    }

    protected function execute(Input $input, Output $output)
    {
        $startTime = microtime(true);
        
        try {
            $threeMonthsAgo     = strtotime('-3 months');
            $dateThreshold      = date('Y-m-d 00:00:00', $threeMonthsAgo);
            $timestampThreshold = strtotime($dateThreshold);
            
            $output->writeln("Deleting records older than: {$dateThreshold} (timestamp: {$timestampThreshold})");
            
            // Process each log table
            $table         = 'video_play_log';
            $deletedTotal  = 0;
            $deletedCount  = $this->deleteFromTable($table, $timestampThreshold, $output);
            $deletedTotal += $deletedCount;
            $endTime       = microtime(true);
            $executionTime = round($endTime - $startTime, 2);
            
            $output->writeln("Total deleted: {$deletedTotal} records");
            $output->writeln("Execution time: {$executionTime} seconds");
            
            // Log the operation
            Log::info("Play log cleanup completed: {$deletedTotal} records deleted, took {$executionTime}s");
            
        } catch (\Exception $e) {
            $output->writeln("Error: " . $e->getMessage());
            Log::error("Play log cleanup failed: " . $e->getMessage());
        }
    }
    
    /**
     * Delete records from a specific table
     */
    private function deleteFromTable($tableName, $timestampThreshold, $output)
    {
        $deletedCount = 0;
        $maxPerRun    = 50000;
        $batchSize    = 2000;
        
        while ($deletedCount < $maxPerRun) {
            $remaining        = $maxPerRun - $deletedCount;
            $currentBatchSize = min($batchSize, $remaining);
            
            $sql = "DELETE FROM `{$tableName}` 
                    WHERE `add_time` < {$timestampThreshold} 
                    LIMIT {$currentBatchSize}";
            
            $affectedRows = Db::execute($sql);
            $deletedCount += $affectedRows;
            // Give DB time to breathe (shorter sleep)
            usleep(200000); // 0.2 seconds
            if ($affectedRows === 0) break;
        }
        return $deletedCount;
    }
}