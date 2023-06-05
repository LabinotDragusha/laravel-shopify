<?php

namespace App\Http\Controllers;

use App\Jobs\Shopify\Sync\MollieOrder;
use App\Models\MollieOrders;
use App\Traits\RequestTrait;
use Illuminate\Http\Request;
use Exception;
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

    public function profile(Request $request)
    {
        $user = Auth::user();
        $store = $user->getShopifyStore;
        $api_mollie = $store->mollie_api;

        $mollie = new \Mollie\Api\MollieApiClient();
        $mollie->setApiKey($api_mollie);

        $profileMe = $mollie->profiles->getCurrent();

        return json_encode($profileMe);
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
            MollieOrder::dispatch($user, $store);

            return back()->with('success', 'Mollie sync successful');
        } catch (Exception $e) {
            return ($e->getMessage() . ' ' . $e->getLine());
        }
    }
}
