<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use app\index\model\Configs;

class ZimuDailySummary extends Command
{
    protected function configure()
    {
        $this->setName('zimu:daily_summary')
            ->setDescription('Send daily transcribe and translate summary to Telegram');
    }

    protected function execute(Input $input, Output $output)
    {
        $startOfYesterday = strtotime(date('Y-m-d 00:00:00', strtotime('-1 day')));
        $endOfYesterday   = strtotime(date('Y-m-d 23:59:59', strtotime('-1 day')));

        // Transcribe stats (type=1)
        $transcribeRequested = Db::name('zimu_log')->where('type', 1)->where('event', 1)->whereBetween('created_at', [$startOfYesterday, $endOfYesterday])->count();
        $transcribeSuccess   = Db::name('zimu_log')->where('type', 1)->where('event', 2)->whereBetween('created_at', [$startOfYesterday, $endOfYesterday])->count();
        $transcribeFailed    = Db::name('zimu_log')->where('type', 1)->where('event', 3)->whereBetween('created_at', [$startOfYesterday, $endOfYesterday])->count();
        $transcribePending   = Db::name('video')->where('zimu_status', 1)->count();

        // Translate stats (type=2)
        $translateRequested  = Db::name('zimu_log')->where('type', 2)->where('event', 1)->whereBetween('created_at', [$startOfYesterday, $endOfYesterday])->count();
        $translateSuccess    = Db::name('zimu_log')->where('type', 2)->where('event', 2)->whereBetween('created_at', [$startOfYesterday, $endOfYesterday])->count();
        $translateFailed     = Db::name('zimu_log')->where('type', 2)->where('event', 3)->whereBetween('created_at', [$startOfYesterday, $endOfYesterday])->count();
        $translatePending    = Db::name('video')->where('zimu_status', 3)->count();

        $date = date('Y-m-d', strtotime('-1 day'));
        $msg  = "📊 *Zimu Daily Summary* ({$date})\n\n";

        $msg .= "🎙 *提取字幕 (Transcribe)*\n";
        $msg .= "  昨日请求: *{$transcribeRequested}*\n";
        $msg .= "  成功回调: *{$transcribeSuccess}*\n";
        $msg .= "  处理中:   *{$transcribePending}*\n";
        $msg .= "  昨日失败: *{$transcribeFailed}*\n\n";

        $msg .= "🌐 *翻译字幕 (Translate)*\n";
        $msg .= "  昨日请求: *{$translateRequested}*\n";
        $msg .= "  成功回调: *{$translateSuccess}*\n";
        $msg .= "  处理中:   *{$translatePending}*\n";
        $msg .= "  昨日失败: *{$translateFailed}*\n";

        $this->sendToGroup($msg);

        $output->writeln("Zimu daily summary sent.");
    }

    private function sendToGroup($message)
    {
        $botToken      = Configs::get('video_translate_bot_token');
        $chatIdsConfig = Configs::get('video_transcribe_n_translate_job', '-1003500829950,6201644496');

        $chatIds = array_filter(array_map('trim', explode(',', $chatIdsConfig)));

        foreach ($chatIds as $chatId) {
            $this->sendTelegram($botToken, $chatId, $message);
        }
    }

    private function sendTelegram($botToken, $chatId, $message)
    {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $ch  = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'Markdown']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            file_put_contents(__DIR__ . '/telegram_error.log', "Chat ID {$chatId}: " . curl_error($ch) . "\n", FILE_APPEND);
        }
        curl_close($ch);
        return $result;
    }
}
