<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Config;
use Carbon\Carbon;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\Log as FacadesLog;

class ServerDailyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:server-daily-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Server Daily Report';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $todayDate = Carbon::now()->format('Y-m-d');
        $command = 'php artisan dusk';
        $output = [];
        exec($command, $output);
        FacadesLog::channel('send_api')->info($command . "--" .json_encode($output));
        $chatId = Config::getCachedConfig('telegram_bot_chat_id');
        $fileName = 'ss-chart-' . $todayDate . '.png';
        $link = asset('storage/temp/' . $fileName);
        Telegram::sendPhoto([
            'chat_id' => $chatId,
            'photo' =>  InputFile::create($link, $fileName ) ,
            'caption' => ''
        ]);
        $fileName2 = 'ss-chart2-' . $todayDate . '.png';
        $link2 = asset('storage/temp/' . $fileName2);
        Telegram::sendPhoto([
            'chat_id' => $chatId,
            'photo' =>  InputFile::create($link2, $fileName2) ,
            'caption' => ''
        ]);
    }
}
