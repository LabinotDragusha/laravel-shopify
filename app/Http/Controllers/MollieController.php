<?php

namespace App\Http\Controllers;

use App\Models\MollieOrders;
use App\Traits\RequestTrait;
use Illuminate\Http\Request;
use Exception;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;

class MollieController extends Controller
{
    use RequestTrait;

    public function index()
    {
        try {
            $user = Auth::user();
            $store = $user->getShopifyStore;
            $mollie_key = $store->mollie_api ?? '';

            return view('mollie.index', compact('mollie_key'));

        } catch (Exception $e) {
            return ($e->getMessage() . ' ' . $e->getLine());
        }

    }

    public function save(Request $request)
    {
        $key = $request->input('mollie_api');

        // Save the key to the "mollie_api" column in your model
        $user = Auth::user();
        $store = $user->getShopifyStore;
        $store->mollie_api = $key;
        $store->save();

        // Redirect or return a response
        return redirect()->back()->with('success', 'Key saved successfully.');
    }

    public function syncOrdersMollie()
    {
        try {


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

            $user = Auth::user();
            $store = $user->getShopifyStore;

            $mollie = new \Mollie\Api\MollieApiClient();
            $mollie->setApiKey($store->mollie_api);

            $orders = $mollie->orders->page();
//        $previous_orders = $most_recent_orders->next();

            $pay_id = '';
            $transaction_id = '';

            foreach ($orders as $key => $order) {
//                if (!MollieOrders::where('order_id', $order->id)->exists()) {
                    $order_pay = $mollie->orders->get('ord_am71b5', ['embed' => 'payments,refunds,shipments']);
//            $order_pay = json_encode($order_pay, true);
//
//            return $order_pay;


                    $orders = $store->getOrders()->where('payment_details->0->receipt->payment_id', $order_pay->payment_id);  //Select columns

                    return $orders;

//                    if ($order_pay->payment_id == $store->getOrders)

                        foreach ($order_pay->_embedded->payments as $pay) {
                            $pay_id = $pay->id;
                            $transaction_id = $pay->description;
                        }

                    $mollieData = [
                        'order_id' => $order->id,
                        'payment_method' => $order->method,
                        'payment_id' => $pay_id,
                        'transaction_id' => $transaction_id,
                        'createdAt' => $order->createdAt,
                        'givenName' => $order->billingAddress->givenName,
                        'email' => $order->billingAddress->email,
                    ];

                    $shipment = $mollie->shipments->update($order_pay->id, 'shp_6srmnv', [
                        "tracking" => [
                            "carrier" => "PostNL",
                            "code" => $transaction_id,
                            "url" => "http://postnl.nl/tracktrace/?B=3SKABA000000000&P=1015CW&D=NL&T=C",
                        ],
                    ]);

                    $shipment = json_encode($shipment, true);

                    MollieOrders::insert($mollieData);
//                } else {
//                    break;
//                }
            }

            return $orders;
        } catch (Exception $e) {
            return ($e->getMessage() . ' ' . $e->getLine());
        }
    }

}
