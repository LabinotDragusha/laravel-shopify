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

            $user = Auth::user();
            $store = $user->getShopifyStore;

            $mollie = new \Mollie\Api\MollieApiClient();
            $mollie->setApiKey($store->mollie_api);

            $orders_mollie = $mollie->orders->page();

            $pay_id = '';
            $transaction_id = '';

            foreach ($orders_mollie as $key => $order) {
                $order_pay = $mollie->orders->get($order->id, ['embed' => 'payments,refunds,shipments']);
                $shopify_payment_id = isset($order->metadata->shopify_payment_id) ? $order->metadata->shopify_payment_id : null;
                $orders_shopify = $store->getOrders()
                    ->where('payment_id', $shopify_payment_id)
                    ->get();

                if (!empty($order_pay->_embedded->payments)) {
                    foreach ($order_pay->_embedded->payments as $pay) {
                        $pay_id = $pay->id;
                        $transaction_id = $pay->description;
                    }
                }

                $tracking_company = isset($orders_shopify[0]->fulfillments[0]['tracking_company']) ? $orders_shopify[0]->fulfillments[0]['tracking_company'] : 'none';
                $tracking_number = isset($orders_shopify[0]->fulfillments[0]['tracking_number']) ? $orders_shopify[0]->fulfillments[0]['tracking_number'] : '0';
                $tracking_url = isset($orders_shopify[0]->fulfillments[0]['tracking_url']) ? $orders_shopify[0]->fulfillments[0]['tracking_url'] : 'https://www.dhl.de/en/privatkunden/pakete-empfangen/verfolgen.html';

                if (!empty($order_pay->_embedded->shipments)) {
                    $shipment = $mollie->shipments->update($order->id, $order_pay->_embedded->shipments[0]->id, [
                        "tracking" => [
                            "carrier" => $tracking_company,
                            "code" => $tracking_number,
                            "url" => $tracking_url,
                        ],
                    ]);

                    $shipment = json_encode($shipment, true);
                }

                $mollieData = [
                    'order_id' => $order_pay->id,
                    'payment_method' => $order_pay->method,
                    'payment_id' => $pay_id,
                    'transaction_id' => $transaction_id,
                    'createdAt' => $order_pay->createdAt,
                    'givenName' => $order_pay->billingAddress->givenName,
                    'email' => $order_pay->billingAddress->email,
                ];
                MollieOrders::insert($mollieData);
            }

            return back()->with('success', 'Mollie sync successful');
        } catch (Exception $e) {
            return ($e->getMessage() . ' ' . $e->getLine());
        }
    }


}
