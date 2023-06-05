<?php
// App\Console\Commands\SyncOrdersCommand.php
namespace App\Console\Commands;

use App\Jobs\Shopify\Sync\MollieOrder;
use App\Jobs\Shopify\Sync\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOrdersCommand extends Command
{

    protected $signature = 'sync:orders';
    protected $description = 'Synchronize orders with Shopify store';

    public function handle()
    {
        $users = User::has('getShopifyStore')->with('getShopifyStore')->get();

        foreach ($users as $user) {
            $store = $user->getShopifyStore;
            // Order::dispatch($user, $store, 'GraphQL'); //For using GraphQL API
            Order::dispatch($user, $store); //For using REST API

//            get all mollie orders
            MollieOrder::dispatch($user, $store);
        }
        Log::info('Order sync successful');
    }
}
