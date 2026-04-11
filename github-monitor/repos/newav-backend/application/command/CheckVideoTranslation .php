<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use app\index\model\Configs;

class CheckVideoTranslation extends Command
{
    protected function configure()
    {
        $this->setName('translate:check')
            ->setDescription('Check videos that failed translation and notify admin');
    }

    protected function execute(Input $input, Output $output)
    {
        $delayHours     = Configs::get('video_delay_hours', 12);
        $delayTimestamp = time() - ($delayHours * 3600);
        $totalCount     = Db::name('video')
                ->where(function ($query) {
                    $query->whereNull('title_en')
                        ->whereOr('title_en', '')
                        ->whereOr('title_en', 'like', '%Default Title%')
                        ->whereOr('title_en', 'like', '%Translation Error%');
                })
                ->where('insert_time', '<=', $delayTimestamp)
                ->count();

        // 1: Find failed translation videos with titles
        $failed = Db::name('video')
            ->where(function ($query) {
                $query->whereNull('title_en')
                    ->whereOr('title_en', '')
                    ->whereOr('title_en', 'like', '%Default Title%')
                    ->whereOr('title_en', 'like', '%Translation Error%');
            })
            ->where('insert_time', '<=', $delayTimestamp)
            ->field('id, title, mash')
            ->limit(20) // set limit due avoid too many failed to lead telegram return too long msg error
            ->select();

        // 2: Build message with titles
        $msg  = "⚠️ *Video Translation Check*\n";
        $msg .= "Failed translation videos found.\n";
        $msg .= "Total failed: *{$totalCount}*\n\n";
        
        if ($totalCount > 0) {
            $msg .= "ID list:\n";
            
            // Format: ID:title (with truncation)
            $idList = [];
            foreach ($failed as $video) {
                $title = $this->truncateTitle($video['title']);
                $idList[] = "{$video['id']}: {$video['mash']},{$title}";
            }
            $msg .= implode(",\n", $idList);
        } else {
            $msg .= "No failed translations found. ✅";
        }

        // 3: Get all admin chat IDs and send to each
        $this->sendToAllAdmins($msg);

        $output->writeln("Sent Telegram {$delayTimestamp} alert to all admins. Count: {$totalCount}");
    }

    private function sendToAllAdmins($message)
    {
        $botToken      = Configs::get('video_translate_bot_token');
        $chatIdsConfig = Configs::get('video_translate_bot_chat');

        if (empty($chatIdsConfig)) {
            file_put_contents(__DIR__.'/telegram_error.log', "No admin chat IDs found\n", FILE_APPEND);
            return;
        }

        // Split comma-separated chat IDs and clean them
        $chatIds = array_map('trim', explode(',', $chatIdsConfig));
        
        // Remove any empty values
        $chatIds = array_filter($chatIds);

        if (empty($chatIds)) {
            file_put_contents(__DIR__.'/telegram_error.log', "No valid admin chat IDs found\n", FILE_APPEND);
            return;
        }

        // Send to each admin
        foreach ($chatIds as $chatId) {
            $this->sendTelegram($botToken, $chatId, $message);
        }
    }

    /**
     * Send Telegram message to specific chat ID
     */
    private function sendTelegram($botToken, $chatId, $message)
    {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text'    => $message,
            'parse_mode' => 'Markdown'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // prevent hang

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            file_put_contents(__DIR__.'/telegram_error.log', "Chat ID {$chatId}: " . curl_error($ch) . "\n", FILE_APPEND);
        }

        curl_close($ch);

        return $result;
    }

    /**
     * Truncate title to max 15 characters
     */
    private function truncateTitle($title, $maxLength = 15)
    {
        if (mb_strlen($title) > $maxLength) {
            return mb_substr($title, 0, $maxLength) . '...';
        }
        return $title;
    }
}