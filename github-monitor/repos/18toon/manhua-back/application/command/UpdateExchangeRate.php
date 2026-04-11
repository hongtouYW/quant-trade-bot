<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class UpdateExchangeRate extends Command
{
    protected function configure()
    {
        $this->setName('update:exchange_rate')
            ->setDescription('获取当前数据库货币最新汇率');
    }

    protected function execute(Input $input, Output $output)
    {
        // 定义需要替换的映射表
        $map = [
            'CNY' => 'rmb',
            'USD' => 'usd',
            'HKD' => 'hkd',
        ];

        // 基准货币，从数据库取
        $baseCurrency = Db::table('exchange_rates')->where('id', 1)->value('currency');
        if (!$baseCurrency) {
            $output->writeln("❌ 未找到基准货币 (id=1)，请先插入 usd 数据");
            return;
        }

        // 转换为 API 需要的货币代码
        $apiBase = array_search($baseCurrency, $map) ?: strtoupper($baseCurrency);

        // 调用 API 获取最新汇率
        $url = "https://open.er-api.com/v6/latest/{$apiBase}";
        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if (!$data || $data['result'] !== 'success') {
            $output->writeln("❌ 获取汇率失败");
            return;
        }

        $rates = $data['conversion_rates'] ?? $data['rates'] ?? [];
        $now = time();

        // 获取数据库已有的 currency 列表
        $currencies = Db::table('exchange_rates')->column('id', 'currency');

        foreach ($currencies as $currency => $id) {
            $code = array_search($currency, $map) ?: strtoupper($currency);
            if (isset($rates[$code])) {
                $rate = $rates[$code];
                Db::table('exchange_rates')
                    ->where('id', $id)
                    ->update([
                        'rate'        => $rate,
                        'update_time' => $now
                    ]);
            }
        }

        $output->writeln("✅ 汇率已更新完成 (" . date('Y-m-d H:i:s') . ")");
    }
}
