<?php

namespace App\Listeners;

use App\Events\MegaEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Megacallback;

class MegaListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(MegaEvent $event): void
    {
        $response = $event->response;
        $tbl_megacallback = Megacallback::create([
            'response' => json_encode($response),
            'error' => isset($response['error']) ? 1 : 0,
            'created_on' => now(),
            'updated_on' => now(),
        ]);
    }
}
