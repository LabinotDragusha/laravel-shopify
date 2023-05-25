<?php

namespace App\Jobs\Shopify\Sync;

use App\Models\MollieOrders;
use App\Traits\RequestTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MollieOrder implements ShouldQueue
{
    private $user, $store, $indexes_to_insert;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RequestTrait;

    public function __construct($user, $store)
    {
        $this->user = $user;
        $this->store = $store;
    }

    public function handle()
    {
        try {
            $mollie = new \Mollie\Api\MollieApiClient();
            $mollie->setApiKey($this->store->mollie_api);

            $orders_mollie = $mollie->orders->next();

            $pay_id = '';
            $transaction_id = '';

            foreach ($orders_mollie as $key => $order) {
                $order_pay = $mollie->orders->get($order->id, ['embed' => 'payments,refunds,shipments']);
                $shopify_payment_id = isset($order->metadata->shopify_payment_id) ? $order->metadata->shopify_payment_id : null;
                $orders_shopify = $this->store->getOrders()
                    ->where('payment_id', $shopify_payment_id)
                    ->get();

                if (!empty($order_pay->_embedded->payments)) {
                    foreach ($order_pay->_embedded->payments as $pay) {
                        $pay_id = $pay->id;
                        $transaction_id = $pay->description;
                    }
                }

                $tracking_company = 'none';
                $tracking_number = '0';
                $tracking_url = 'https://www.dhl.de/en/privatkunden/pakete-empfangen/verfolgen.html';

                if (isset($orders_shopify[0]) && isset($orders_shopify[0]->fulfillments) && count($orders_shopify[0]->fulfillments) > 0) {
                    $lastFulfillment = $orders_shopify[0]->fulfillments[count($orders_shopify[0]->fulfillments) - 1];

                    if (isset($lastFulfillment['tracking_company'])) {
                        $tracking_company = $lastFulfillment['tracking_company'];
                    }

                    if (isset($lastFulfillment['tracking_number'])) {
                        $tracking_number = $lastFulfillment['tracking_number'];
                    }

                    if (isset($lastFulfillment['tracking_url'])) {
                        $tracking_url = $lastFulfillment['tracking_url'];
                    }
                }


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
        } catch (Exception $e) {
            Log::critical(['code' => $e->getCode(), 'message' => $e->getMessage(), 'trace' => json_encode($e->getTrace())]);
            throw $e;
        }

    }


}
