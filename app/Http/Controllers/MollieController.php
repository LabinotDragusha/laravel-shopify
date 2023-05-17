<?php

namespace App\Http\Controllers;

use App\Models\MollieOrders;
use App\Traits\RequestTrait;

class MollieController extends Controller
{
    use RequestTrait;

    public function index()
    {
        return view('mollie.index');
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
            if (!MollieOrders::where('order_id', $order->id)->exists()) {
                $order_pay = $mollie->orders->get('ord_am71b5', ['embed' => 'payments,refunds,shipments']);
//            $order_pay = json_encode($order_pay, true);
//
//            return $order_pay;

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
            } else {
                break;
            }
        }

        return $orders;

    }
}
