<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use App\Http\Controllers\FpayController;

class TestFpayCallback extends Command
{
    protected $signature = 'test:fpay';
    protected $description = 'Simulate Fpay callback';

    public function handle()
    {
        // Your real payload
        $payload = [
            "order_id" => "fpay_224694_31_5224",
            "amount" => "100.0000",
            "currency" => "MYR",
            "order_status" => "completed",
            "status" => true,
            "charge" => "1.8000",
            "token" => "fd8a023a05b21e8e9ffd70bdebbf975a",
            "name" => "",
            "type" => "deposit",
            "ccno" => null,
            "mode" => "",
            "payment_type" => "QR",
            "username" => "1302200712",
            "utr" => ""
        ];

        // Simulate raw request (IMPORTANT)
        $request = Request::create(
            '/fpay/callback',
            'POST',
            [],
            [],
            [],
            [],
            json_encode($payload)
        );

        $controller = new FpayController();
        $response = $controller->callback($request);

        $this->info('Response:');
        $this->line(print_r($response, true));

        return Command::SUCCESS;
    }
}
