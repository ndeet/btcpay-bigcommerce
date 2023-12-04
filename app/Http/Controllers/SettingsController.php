<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\BigCommerceService;
use App\Services\BtcPayService;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

use GuzzleHttp\Exception\RequestException;

use GuzzleHttp\Client;

class SettingsController extends Controller
{

    public function index(Request $request)
    {
        return Setting::where('store_hash', $request->session()->get('store_hash'))->first();
    }

    public function store(Request $request): RedirectResponse
    {
        // get store has from session
        $store_hash = $request->session()->get('store_hash');

        $validated = $request->validate([
            'store_id' => 'required|string|max:255',
            'api_key' => 'required|string|max:255',
            'url' => 'required|url|max:255',
        ]);

        if ($store_hash && $validated) {
            // Create settings entry.
            $setting = Setting::create(['store_hash' => $store_hash] + $validated);
        } else {
            return redirect('/')->with('error', 'Error saving settings.');
        }

        $bcService = new BigCommerceService($store_hash);

        // Make sure script is available.
        $bcService->ensureCheckoutScript();

        // Make sure the store domain is set.
        $bcService->ensureStoreDomain();

        // Ensure Webhook is installed.
        try {
            $btcPayService = new BtcPayService($store_hash);
            $btcPayService->ensureWebhook();
        } catch (\Throwable $e) {
            return redirect('/')->with('error', 'Error creating webhook: ' . $e->getMessage());
        }

        return redirect('/')->with('success', 'Settings saved.');
    }

    public function update(Setting $setting, Request $request): RedirectResponse
    {
        $bcService = new BigCommerceService($setting->store_hash);

        $validated = $request->validate([
            'store_id' => 'required|string|max:255',
            'api_key' => 'required|string|max:255',
            'url' => 'required|url|max:255',
        ]);

        // Update settings.
        if ($validated) {
            $setting->update($validated);
        }

        // Make sure the script available.
        $bcService->ensureCheckoutScript();

        // Make sure the store domain is set.
        $bcService->ensureStoreDomain();

        // Ensure Webhook is installed.
        try {
            $btcPayService = new BtcPayService($setting->store_hash);
            $btcPayService->ensureWebhook();
        } catch (\Throwable $e) {
            #return redirect('/')->with('error', 'Error creating webhook: ' . $e->getMessage());
            dd($e->getMessage());
        }

        return redirect('/')->with('success', 'Settings updated.');
    }

    public function installScript(Request $request)
    {
        $bcService = new BigCommerceService();
        $bcService->setCheckoutScript();
    }
}
