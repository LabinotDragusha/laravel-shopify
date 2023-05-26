<?php
// App\Console\Commands\SyncOrdersCommand.php
namespace  App\Console\Commands;

use App\Http\Requests\FulfillOrder;
use App\Jobs\Shopify\Sync\Customer;
use App\Jobs\Shopify\Sync\Locations;
use App\Jobs\Shopify\Sync\OneOrder;
use App\Jobs\Shopify\Sync\Order;
use App\Jobs\Shopify\Sync\OrderFulfillments;
use App\Jobs\Shopify\Sync\Product;
use App\Models\MollieOrders;
use App\Models\User;
use App\Traits\RequestTrait;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mollie\Laravel\Facades\Mollie;
use Mollie\Api\Resources\Payment;
use Mollie\Api\MollieApiClient;
use Symfony\Component\HttpFoundation\Response;

class SyncOrdersCommand extends Command {

    protected $signature = 'sync:orders';

    protected $description = 'Synchronize orders with Shopify store';

    public function handle()
    {
        $users = User::has('getShopifyStore')->with('getShopifyStore')->get();


        foreach ($users as $user) {
            $store = $user->getShopifyStore;
                    // Order::dispatch($user, $store, 'GraphQL'); //For using GraphQL API
                    Order::dispatch($user, $store); //For using REST API



        }
        Log::info('Order sync successful');
    }
}
