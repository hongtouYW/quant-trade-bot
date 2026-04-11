<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;

class DailyReport extends Command
{
    protected function configure()
    {
        $this->setName('report:daily')
            ->setDescription('发送指定日期或昨日的数据统计到Telegram')
            ->addOption(
                'date',
                null,
                Option::VALUE_OPTIONAL,
                '指定日期 (格式: YYYY-MM-DD)'
            );
    }

    /**
     * 发送 Telegram 消息
     */
    protected function sendTelegram(string $text, bool $isImport = false)
    {
        $botToken = env('telegram.bot_token');
        $chatId = env('telegram.chat_id');

        // 语义化 thread_id
        $threadId = $isImport
            ? env('telegram.thread_id_import')  // 采集状态
            : env('telegram.thread_id');        // 日报

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'message_thread_id' => $threadId,
            'text' => $text
        ];

        $options = [
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/x-www-form-urlencoded",
                "content" => http_build_query($data)
            ]
        ];

        file_get_contents($url, false, stream_context_create($options));
    }

    protected function execute(Input $input, Output $output)
    {
        // 获取日期参数
        $date = $input->getOption('date');
        if ($date) {
            $start = strtotime("{$date} 00:00:00");
            $end = strtotime("{$date} 23:59:59");
        } else {
            $start = strtotime('yesterday 00:00:00');
            $end = strtotime('yesterday 23:59:59');
            $date = date('Y-m-d', strtotime('-1 day'));
        }

        // -------------------
        // 1️⃣ 日报数据统计
        // -------------------

        $registerCount = db('member')->whereBetween('register_time', [$start, $end])->count();
        $loginCount = db('member')->whereBetween('last_time', [$start, $end])->count();

        $totalOrders = db('order')
            ->whereBetween('addtime2', [$start, $end])
            ->where('is_kl', 0)
            ->count();

        $successOrders = db('order')
            ->whereBetween('addtime2', [$start, $end])
            ->where('orderswitch', 1)
            ->where('is_kl', 0)
            ->count();

        $orderAmount = db('order')
            ->whereBetween('addtime2', [$start, $end])
            ->where('orderswitch', 1)
            ->where('is_kl', 0)
            ->sum('money');

        $historyCount = db('history')
            ->whereBetween('addtime', [$start, $end])
            ->count();

        $dailyMessage =
            "📊 数据统计 ({$date})\n" .
            "注册量: {$registerCount}\n" .
            "登入量: {$loginCount}\n" .
            "订单总数: {$totalOrders}\n" .
            "成功订单数: {$successOrders}\n" .
            "成功金额: {$orderAmount}\n" .
            "阅读次数: {$historyCount}";

        $this->sendTelegram($dailyMessage, false);

        // -------------------
        // 2️⃣ 18Toon 采集状态汇报
        // -------------------

        $importStats18Toon = db('import_manhua')
            ->field('COUNT(*) as total, SUM(is_converted) as converted')
            ->find();

        $importComics18Toon = $importStats18Toon['total'] ?? 0;
        $convertedComics18Toon = $importStats18Toon['converted'] ?? 0;
        $failedComics18Toon = $importComics18Toon - $convertedComics18Toon;

        $todayImport18Toon = db('import_manhua')
            ->whereBetween('create_time', [$start, $end])
            ->count();

        $importMessage18Toon =
            "🔞 18漫（国内版） 导入采集状态 ({$date})\n" .
            "累计采集漫画: {$importComics18Toon}\n" .
            "累计成功转换: {$convertedComics18Toon}\n" .
            "累计未转换/失败: {$failedComics18Toon}\n" .
            "今日新增采集: {$todayImport18Toon}";

        $this->sendTelegram($importMessage18Toon, true);

        $output->writeln("日报 + 18Toon 采集状态 已发送: " . $date);
    }
}
