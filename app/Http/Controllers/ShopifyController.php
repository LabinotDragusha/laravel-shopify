<?php

namespace App\Http\Controllers;

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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mollie\Laravel\Facades\Mollie;
use Mollie\Api\Resources\Payment;
use Mollie\Api\MollieApiClient;
use Symfony\Component\HttpFoundation\Response;

class ShopifyController extends Controller
{

    use RequestTrait;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function orders()
    {
        $user = Auth::user();
        $store = $user->getShopifyStore;
        $orders = $store->getOrders()
            ->select(['table_id', 'financial_status', 'name', 'email', 'phone', 'created_at', 'payment_details'])
            ->orderBy('table_id', 'desc')
            ->paginate(15);

        return view('orders.index', ['orders' => $orders]);
    }

    public function listOrders(Request $request)
    {
        try {
            if ($request->ajax()) {
                $request = $request->all();
                $user = Auth::user();
                $store = $user->getShopifyStore;
                $orders = $store->getOrders()
                    ->select(['table_id', 'financial_status', 'name', 'email', 'phone', 'created_at', 'payment_details','fulfillments']);//Select columns
                if(isset($request['search']) && isset($request['search']['value']))
                    $orders = $this->filterOrders($orders, $request); //Filter customers based on the search term
                $count = $orders->count(); //Take the total count returned so far
                $limit = $request['length'];
                $offset = $request['start'];
                $orders = $orders->offset($offset)->limit($limit); //LIMIT and OFFSET logic for MySQL
//                if(isset($request['order']) && isset($request['order'][0]))
//                    $customers = $this->orderCustomers($customers, $request); //Order customers based on the column
                $data = [];
                $query = $orders->toSql(); //For debugging the SQL query generated so far
                $rows = $orders->get(); //Fetch from DB by using get() function
                if($rows !== null)

                    foreach ($rows as $key => $item)
                        $data[] = array_merge(
                            ['#' => $key + 1], //To show the first column, NOTE: Do not show the table_id column to the viewer
                            $item->toArray()
                        );

                return response()->json([
                    "draw" => intval(request()->query('draw')),
                    "recordsTotal" => intval($count),
                    "recordsFiltered" => intval($count),
                    "data" => $data,
                    "debug" => [
                        "request" => $request,
                        "sqlQuery" => $query
                    ]
                ], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage() . ' ' . $e->getLine()], 500);
        }
    }
    public function filterOrders($orders, $request)
    {
        $term = $request['search']['value'];
        return $orders->where(function ($query) use ($term) {
            $query->where('name', 'LIKE', '%' . $term . '%');

        });
    }
    public function showOrder($id) {
        $user = Auth::user();
        $store = $user->getShopifyStore;
        $order = $store->getOrders()->where('table_id', $id)->first();
        if ($order->getFulfillmentOrderDataInfo()->doesntExist())
            OrderFulfillments::dispatch($user, $store, $order);
        $product_images = $store->getProductImagesForOrder($order);

        return view('orders.show', [
            'order_currency' => getCurrencySymbol($order->currency),
            'product_images' => $product_images,
            'order' => $order
        ]);
    }


    private function getFulfillmentLineItem($request, $order)
    {
        try {
            $search = (int)$request['lineItemId'];
            $fulfillment_orders = $order->getFulfillmentOrderDataInfo;
            foreach ($fulfillment_orders as $fulfillment_order) {
                $line_items = $fulfillment_order->line_items;
                foreach ($line_items as $item) {
                    if ($item['line_item_id'] === $search) // Found it!
                        return $fulfillment_order;
                }
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function getPayloadForFulfillment($line_items, $request)
    {
        return [
            'fulfillment' => [
                'message' => $request['message'],
                'notify_customer' => $request['notify_customer'] === 'on',
                'tracking_info' => [
                    'number' => $request['number'],
                    'url' => $request['tracking_url'],
                    'company' => $request['shipping_company']
                ],
                'line_items_by_fulfillment_order' => $this->getFulfillmentOrderArray($line_items, $request)
            ]
        ];
    }

    private function getFulfillmentOrderArray($line_items, $request)
    {
        $temp_payload = [];
        $search = (int)$request['lineItemId'];
        foreach ($line_items as $line_item)
            if ($line_item['line_item_id'] === $search)
                $temp_payload[] = [
                    'fulfillment_order_id' => $line_item['fulfillment_order_id'],
                    'fulfillment_order_line_items' => [[
                        'id' => $line_item['id'],
                        'quantity' => (int)$request['no_of_packages']
                    ]]
                ];
        return $temp_payload;
    }


    private function checkIfCanBeFulfilledDirectly($fulfillment_order)
    {
        return in_array('request_fulfillment', $fulfillment_order->supported_actions);
    }

    private function getLineItemsByFulifllmentOrderPayload($line_items, $request)
    {
        $search = (int)$request['lineItemId'];
        foreach ($line_items as $line_item)
            if ($line_item['line_item_id'] === $search)
                return implode(',', [
                    'fulfillmentOrderId: "gid://shopify/FulfillmentOrder/' . $line_item['fulfillment_order_id'] . '"',
                    'fulfillmentOrderLineItems: { id: "gid://shopify/FulfillmentOrderLineItem/' . $line_item['id'] . '", quantity: ' . (int)$request['no_of_packages'] . ' }'
                ]);
    }

    private function getGraphQLPayloadForFulfillment($line_items, $request)
    {
        $temp = [];
        $temp[] = 'notifyCustomer: ' . ($request['notify_customer'] === 'on' ? 'true' : 'false');
        $temp[] = 'trackingInfo: { company: "' . $request['shipping_company'] . '", number: "' . $request['number'] . '", url: "' . $request['tracking_url'] . '"}';
        $temp[] = 'lineItemsByFulfillmentOrder: [{ ' . $this->getLineItemsByFulifllmentOrderPayload($line_items, $request) . ' }]';
        return implode(',', $temp);
    }

    private function getFulfillmentV2PayloadForFulfillment($line_items, $request)
    {
        $fulfillmentV2Mutation = 'fulfillmentCreateV2 (fulfillment: {' . $this->getGraphQLPayloadForFulfillment($line_items, $request) . '}) {
            fulfillment { id }
            userErrors { field message }
        }';
        $mutation = 'mutation MarkAsFulfilledSubmit{ ' . $fulfillmentV2Mutation . ' }';
        return ['query' => $mutation];
    }

    public function fulfillOrder(FulfillOrder $request)
    {
        try {
            $sendAndAcceptresponse = null;
            $request = $request->all();
            $user = Auth::user();
            $store = $user->getShopifyStore;
            $order = $store->getOrders()->where('table_id', (int)$request['order_id'])->first();
            $fulfillment_order = $this->getFulfillmentLineItem($request, $order);

            if ($fulfillment_order !== null) {
                $check = $this->checkIfCanBeFulfilledDirectly($fulfillment_order);
                if (!$check) {
                    $payload = $this->getFulfillmentV2PayloadForFulfillment($fulfillment_order->line_items, $request);
                    $api_endpoint = 'graphql.json';
                } else {
                    if ($store->hasRegisteredForFulfillmentService())
                        $sendAndAcceptresponse = $this->sendAndAcceptFulfillmentRequests($store, $fulfillment_order);
                    $payload = $this->getPayloadForFulfillment($fulfillment_order->line_items, $request);
                    $api_endpoint = 'fulfillments.json';
                }

                $endpoint = getShopifyURLForStore($api_endpoint, $store);
                $headers = getShopifyHeadersForStore($store);
                $response = $this->makeAnAPICallToShopify('POST', $endpoint, null, $headers, $payload);

                if ($response['statusCode'] === 201 || $response['statusCode'] === 200)
                    OneOrder::dispatch($user, $store, $order->id);

                Log::info('Response for fulfillment');
                Log::info(json_encode($response));
                return response()->json(['response' => $response, 'sendAndAcceptresponse' => $sendAndAcceptresponse ?? null]);
            }
            return response()->json(['status' => false]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage() . ' ' . $e->getLine()]);
        }
    }

    private function sendAndAcceptFulfillmentRequests($store, $fulfillment_order)
    {
        try {
            $responses = [];
            $responses[] = $this->callFulfillmentRequestEndpoint($store, $fulfillment_order);
            $responses[] = $this->callAcceptRequestEndpoint($store, $fulfillment_order);
            return ['status' => true, 'message' => 'Done', 'responses' => $responses];
        } catch (Exception $e) {
            return ['status' => false, 'error' => $e->getMessage() . ' ' . $e->getLine()];
        }
    }

    private function callFulfillmentRequestEndpoint($store, $fulfillment_order)
    {
        $endpoint = getShopifyURLForStore('fulfillment_orders/' . $fulfillment_order->id . '/fulfillment_request.json', $store);
        $headers = getShopifyHeadersForStore($store);
        $payload = [
            'fulfillment_request' => [
                'message' => 'Please fulfill ASAP'
            ]
        ];
        return $this->makeAnAPICallToShopify('POST', $endpoint, null, $headers, $payload);
    }

    private function callAcceptRequestEndpoint($store, $fulfillment_order)
    {
        $endpoint = getShopifyURLForStore('fulfillment_orders/' . $fulfillment_order->id . '/fulfillment_request/accept.json', $store);
        $headers = getShopifyHeadersForStore($store);
        $payload = [
            'fulfillment_request' => [
                'message' => 'Accepted the request on ' . date('F d, Y')
            ]
        ];
        return $this->makeAnAPICallToShopify('POST', $endpoint, null, $headers, $payload);
    }

    public function products()
    {
        $user = Auth::user();
        $store = $user->getShopifyStore;
        $products = $store->getProducts()
            ->select(['title', 'product_type', 'vendor', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->get();
        return view('products.index', ['products' => $products]);
    }

    public function syncProducts()
    {
        try {
            $user = Auth::user();
            $store = $user->getShopifyStore;
            Product::dispatch($user, $store);
            return back()->with('success', 'Product sync successful');
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error :' . $e->getMessage() . ' ' . $e->getLine()]);
        }
    }

    public function syncCustomers()
    {
        try {
            $user = Auth::user();
            $store = $user->getShopifyStore;
            Customer::dispatch($user, $store);
            return back()->with('success', 'Customer sync successful');
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error :' . $e->getMessage() . ' ' . $e->getLine()]);
        }
    }


    //Sync orders for Store using either GraphQL or REST API
    public static function syncOrders()
    {
        try {
            $user = Auth::user();
            $store = $user->getShopifyStore;
            //Order::dispatch($user, $store, 'GraphQL'); //For using GraphQL API
            Order::dispatch($user, $store); //For using REST API
            return back()->with('success', 'Order sync successful');
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error :']);
        }
    }

    public function acceptCharge(Request $request)
    {
        try {
            $user = Auth::user();
            $store = $user->getShopifyStore;
            $charge_id = $request->charge_id;
            $user_id = $request->user_id;
            $endpoint = getShopifyURLForStore('application_charges/' . $charge_id . '.json', $store);
            $headers = getShopifyHeadersForStore($store);
            $response = $this->makeAnAPICallToShopify('GET', $endpoint, null, $headers);
            if ($response['statusCode'] === 200) {
                $body = $response['body']['application_charge'];
                if ($body['status'] === 'active') {
                    return redirect()->route('members.create')->with('success', 'Sub user created!');
                }
            }
            User::where('id', $user_id)->delete();
            return redirect()->route('members.create')->with('error', 'Some problem occurred while processing the transaction. Please try again.');
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error :' . $e->getMessage() . ' ' . $e->getLine()]);
        }
    }

    public function customers()
    {
        return view('customers.index');
    }

    public function list(Request $request)
    {
        try {
            if ($request->ajax()) {
                $request = $request->all();
                $store = Auth::user()->getShopifyStore; //Take the auth user's shopify store
                $customers = $store->getCustomers(); //Load the relationship (Query builder)
                $customers = $customers->select(['first_name', 'last_name', 'email', 'phone', 'created_at']); //Select columns
                if (isset($request['search']) && isset($request['search']['value']))
                    $customers = $this->filterCustomers($customers, $request); //Filter customers based on the search term
                $count = $customers->count(); //Take the total count returned so far
                $limit = $request['length'];
                $offset = $request['start'];
                $customers = $customers->offset($offset)->limit($limit); //LIMIT and OFFSET logic for MySQL
                if (isset($request['order']) && isset($request['order'][0]))
                    $customers = $this->orderCustomers($customers, $request); //Order customers based on the column
                $data = [];
                $query = $customers->toSql(); //For debugging the SQL query generated so far
                $rows = $customers->get(); //Fetch from DB by using get() function
                if ($rows !== null)
                    foreach ($rows as $key => $item)
                        $data[] = array_merge(
                            ['#' => $key + 1], //To show the first column, NOTE: Do not show the table_id column to the viewer
                            $item->toArray()
                        );
                return response()->json([
                    "draw" => intval(request()->query('draw')),
                    "recordsTotal" => intval($count),
                    "recordsFiltered" => intval($count),
                    "data" => $data,
                    "debug" => [
                        "request" => $request,
                        "sqlQuery" => $query
                    ]
                ], 200);
            }

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage() . ' ' . $e->getLine()], 500);
        }
    }


    //Returns a Query builders after setting the logic for ordering customers by specified column
    public function orderCustomers($customers, $request)
    {
        $column = $request['order'][0]['column'];
        $dir = $request['order'][0]['dir'];
        $db_column = null;
        switch ($column) {
            case 0:
                $db_column = 'table_id';
                break;
            case 1:
                $db_column = 'first_name';
                break;
            case 2:
                $db_column = 'email';
                break;
            case 3:
                $db_column = 'phone';
                break;
            case 4:
                $db_column = 'created_at';
                break;
            default:
                $db_column = 'table_id';
        }
        return $customers->orderBy($db_column, $dir);
    }

    //Returns a Query builder after setting the logic for filtering customers by the search term
    public function filterCustomers($customers, $request)
    {
        $term = $request['search']['value'];
        return $customers->where(function ($query) use ($term) {
            $query->where(
                DB::raw("CONCAT(`first_name`, ' ', `last_name`)"), 'LIKE', "%" . $term . "%"
            )
                ->orWhere('email', 'LIKE', '%' . $term . '%')
                ->orWhere('phone', 'LIKE', '%' . $term . '%');
        });
    }

    public function syncLocations()
    {
        try {
            $user = Auth::user();
            $store = $user->getShopifyStore;
            Locations::dispatch($user, $store);
            return back()->with('success', 'Locations synced successfully');
        } catch (Exception $e) {
            dd($e->getMessage() . ' ' . $e->getLine());
        }
    }

    public function syncOrder($id)
    {
        $user = Auth::user();
        $store = $user->getShopifyStore;
        $order = $store->getOrders()->where('table_id', $id)->select('id')->first();
        OneOrder::dispatchNow($user, $store, $order->id);
        return redirect()->route('shopify.order.show', $id)->with('success', 'Order synced!');
    }


    public function syncOrdersMollie()
    {
//        try {
//            $user = Auth::user();
//            $store = $user->getShopifyStore;
//            $orders = $store->getOrders()->first();
//            $mollieOrders = MollieOrders::first();
//
////            foreach ($orders as $order) {
////                $order['tracking_number'] = ;
////            }
//
//        if ($orders['fulfillments'][0]['tracking_number'] == $mollieOrders['transaction_id']) {
//            $updateMollie = [
//                'payment_method' => 'Klarna'
//            ];
//            MollieOrders::where('table_id', $mollieOrders->id)->update($updateMollie);
//            return 'success';
//        }
//
//            return response()->json($orders['fulfillments'][0]['tracking_number']);
//        } catch(Exception $e) {
//            return($e->getMessage() . ' ' . $e->getLine());
//        }



        $mollie = new \Mollie\Api\MollieApiClient();
        $mollie->setApiKey('test_4PMxdakz6MUE2J9MaPfy2EamaGSyk2');

        $orders = $mollie->orders->page();
//        $previous_orders = $most_recent_orders->next();

        $pay_id = '';
        $transaction_id = '';

        foreach ($orders as $key => $order) {
//            if (!MollieOrders::where('order_id', $order->id)->exists()) {
            $order_pay = $mollie->orders->get('ord_9c5hih', ['embed' => 'payments,refunds']);
//            $order_pay = json_encode($order_pay, true);

            foreach ($order_pay->_embedded->payments as $pay) {
                $pay_id = $pay->id;
                $transaction_id = $pay->description;
            }

            $mollie->shipments->update('ord_9c5hih', 'shp_9dy2g1', [
                "tracking" => [
                    "carrier" => "DEVDEV",
                    "code" => "1111111",
                    "url" => "http://postnl.nl/tracktrace/?B=3SKABA000000000&P=1015CW&D=NL&T=C",
                ],
            ]);

//            return json_encode($shipment);

            $mollieData = [
                'order_id' => $order->id,
                'payment_method' => $order->method,
                'payment_id' => $pay_id,
                'transaction_id' => $transaction_id,
                'createdAt' => $order->createdAt,
                'givenName' => $order->billingAddress->givenName,
                'email' => $order->billingAddress->email,
            ];

            MollieOrders::insert($mollieData);
//            } else {
//                break;
//            }
        }

//        return $orders;

    }

}
