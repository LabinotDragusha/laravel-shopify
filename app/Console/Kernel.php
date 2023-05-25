<?php

namespace App\Console;

use App\Jobs\Shopify\Sync\Order;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
//        $schedule->job(new Order)->everyMinute();

        $schedule_task = $schedule->call(function () {
            $orderShopify = new \App\Http\Controllers\ShopifyController();
            $orderShopify->syncOrders();
        })->everyMinute();

        Log::info(json_encode($schedule_task));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
