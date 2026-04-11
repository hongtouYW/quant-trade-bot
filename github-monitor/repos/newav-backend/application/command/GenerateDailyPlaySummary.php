<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\facade\Log;

class GenerateDailyPlaySummary extends Command
{
    protected function configure()
    {
        $this->setName('generate:daily_summary')
            ->setDescription('Generate daily play statistics summaries');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            // Run for yesterday (most stable data)
            $targetDate = date('Y-m-d', strtotime('-1 day'));
            $output->writeln("Generating summaries for date: {$targetDate}");
            
            // Process single log table
            $this->processDailySummary($targetDate, $output);
            
            // Clean old data (keep 180 days = 6 months)
            $this->cleanOldSummaries($output);
            
            $output->writeln("Daily summary generation completed!");
            
        } catch (\Exception $e) {
            $output->writeln("Error: " . $e->getMessage());
            Log::error("Daily summary generation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Process daily summary from video_play_log
     */
    private function processDailySummary($date, $output)
    {
        $startTime = strtotime($date . ' 00:00:00');
        $endTime = strtotime($date . ' 23:59:59');
        
        $logTable = 'video_play_log';
        $summaryTable = 'video_play_daily_summary';
        
        $output->writeln("Processing table: {$logTable}");
        
        // Check if summary already exists for this date
        $existing = Db::name($summaryTable)
            ->where('date', $date)
            ->count();
            
        if ($existing > 0) {
            $output->writeln("Summary for {$date} already exists, skipping...");
            return;
        }
        
        // Get top 1000 videos for the day
        $topVideos = Db::name($logTable)
            ->alias('log')
            ->field('log.vid, COUNT(log.id) as watch_count')
            ->where('log.add_time', 'between', [$startTime, $endTime])
            ->group('log.vid')
            ->order('watch_count', 'desc')
            ->limit(1000)
            ->select();
        
        if (empty($topVideos)) {
            $output->writeln("No data for {$date}");
            return;
        }
        
        // Insert into summary table
        $insertData = [];
        foreach ($topVideos as $video) {
            $insertData[] = [
                'date'        => $date,
                'vid'         => $video['vid'],
                'watch_count' => $video['watch_count'],
                'created_at'  => date('Y-m-d H:i:s')
            ];
        }
        
        Db::name($summaryTable)->insertAll($insertData);
        
        $output->writeln("Inserted " . count($topVideos) . " records for {$date}");
    }
    
    /**
     * Clean summaries older than 180 days
     */
    private function cleanOldSummaries($output)
    {
        $cutoffDate = date('Y-m-d', strtotime('-180 days'));
        
        $deleted = Db::name('video_play_daily_summary')
            ->where('date', '<', $cutoffDate)
            ->delete();
            
        $output->writeln("Deleted {$deleted} old summary records (older than {$cutoffDate})");
    }
}