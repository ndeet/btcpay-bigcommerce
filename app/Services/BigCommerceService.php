<?php

namespace App\Services;

use App\Enums\OrderStates;
use App\Models\Setting;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BigCommerceService
{
    const BTCPAY_SCRIPT_NAME = 'btcpay-checkout';
    const BTCPAY_SCRIPT_VERSION = '1.0.0';

    protected string $store_hash;
    protected Setting $setting;

    public function __construct(string $store_hash = null)
    {
        if ($store_hash) {
            $this->store_hash = $store_hash;
            $this->setting = Setting::where('store_hash', $this->store_hash)->first();
        }
    }

    public function getAppClientId()
    {
        if (env('APP_ENV') === 'local') {
            return env('BC_LOCAL_CLIENT_ID');
        } else {
            return env('BC_APP_CLIENT_ID');
        }
    }

    public function getAppSecret()
    {
        if (env('APP_ENV') === 'local') {
            return env('BC_LOCAL_SECRET');
        } else {
            return env('BC_APP_SECRET');
        }
    }

    public function getAccessToken()
    {
        //mymod
        #return env('BC_LOCAL_ACCESS_TOKEN');
        if (env('APP_ENV') === 'local') {
            return env('BC_LOCAL_ACCESS_TOKEN');
        } else if (!empty($this->setting->store_access_token)) {
            return $this->setting->store_access_token;
        } else {
            return session('access_token');
        }
    }

    public function getStoreHash()
    {
        if (env('APP_ENV') === 'local') {
            return env('BC_LOCAL_STORE_HASH');
        } else if (isset($this->store_hash)) {
            return $this->store_hash;
        } else {
            return session('store_hash');
        }
    }

    public function verifySignedRequest($signedRequest, $appRequest)
    {
        list($encodedData, $encodedSignature) = explode('.', $signedRequest, 2);

        // decode the data
        $signature = base64_decode($encodedSignature);
        $jsonStr = base64_decode($encodedData);
        $data = json_decode($jsonStr, true);
        // confirm the signature
        $expectedSignature = hash_hmac('sha256', $jsonStr, $this->getAppSecret($appRequest), $raw = false);

        if (!hash_equals($expectedSignature, $signature)) {
            error_log('Bad signed request from BigCommerce!');
            dd($signature, $jsonStr, $data, $expectedSignature, $signedRequest);
            return null;
        }
        return $data;
    }

    public function makeBigCommerceAPIRequest(Request $request, $endpoint)
    {
        $requestConfig = [
            'headers' => [
                'X-Auth-Client' => $this->getAppClientId(),
                'X-Auth-Token' => $this->getAccessToken($request),
                'Content-Type' => 'application/json',
            ]
        ];

        if ($request->method() === 'PUT') {
            $requestConfig['body'] = $request->getContent();
        }
        #error_log("checkpoint");
        try {
            $client = new Client();
            $result = $client->request($request->method(), 'https://api.bigcommerce.com/' . $this->getStoreHash($request) . '/' . $endpoint, $requestConfig);
        } catch (\Throwable $e) {
            error_log('req exception: ' . $e->getMessage());
            error_log('req status: ' . $e->getCode());
        }
        return $result;
    }

    public function proxyBigCommerceAPIRequest(Request $request, $endpoint)
    {
        if (strrpos($endpoint, 'v2') !== false) {
            // For v2 endpoints, add a .json to the end of each endpoint, to normalize against the v3 API standards
            $endpoint .= '.json';
        }

        $result = $this->makeBigCommerceAPIRequest($request, $endpoint);

        return response($result->getBody(), $result->getStatusCode())->header('Content-Type', 'application/json');
    }

    public function makeBigCommerceAPICall(string $method, array $data, string $endpoint)
    {
        $requestConfig = [
            'headers' => [
                'X-Auth-Client' => $this->getAppClientId(),
                'X-Auth-Token' => $this->getAccessToken(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ];

        if (in_array($method, ['POST', 'PUT'])) {
            $requestConfig['body'] = json_encode($data);
        }

        try {
            $client = new Client();
            $result = $client->request($method, 'https://api.bigcommerce.com/' . $this->getStoreHash() . '/' . $endpoint, $requestConfig);
        } catch (\Throwable $e) {
            dd($e->getMessage());
            error_log('req exception: ' . $e->getMessage());
            error_log('req status: ' . $e->getCode());
        }

        return $result;
    }

    public function ensureCheckoutScript(): void
    {
        // Check if the script is already installed.
        $setting = Setting::where('store_hash', $this->getStoreHash())->first();
        if (!empty($setting->js_file_uuid)) {
            if (!$this->getCheckoutScript($setting->js_file_uuid)) {
                $script = $this->setCheckoutScript();
            }
        } else {
            $script = $this->setCheckoutScript();
        }

        if (!empty($script['data']['uuid'])) {
            $setting->update(['js_file_uuid' => $script['data']['uuid']]);
        }
    }

    public function setCheckoutScript(): ?array
    {
        try {
           $payload = [
                'name' => self::BTCPAY_SCRIPT_NAME,
                'description' => 'Adds BTCPay Javascript to the checkout page.',
                'src' => url('/') . '/js/btcpay-bc.js?bcid=' . preg_replace('/^stores\//', '', $this->getStoreHash()),
                'auto_uninstall' => true,
                'load_method' => 'default',
                'location' => 'footer',
                'visibility' => 'checkout',
                'kind' => 'src',
                'api_client_id' => null,
                'consent_category' => 'essential',
                'enabled' => true,
           ];

            $result = $this->makeBigCommerceAPICall('POST', $payload, 'v3/content/scripts');

            return json_decode($result->getBody(), true);
        } catch (Illuminate\Contracts\Filesystem\FileNotFoundException $exception) {
            dd("Error setting file via BC API.");
        }

        return null;
    }

    public function getCheckoutScript(string $scriptUuid): ?array
    {
        $result = $this->makeBigCommerceAPICall(
            'GET',
            [],
            'v3/content/scripts/' . $scriptUuid
        );

        if ($result->getStatusCode() !== 200) {
            return null;
        }

        return json_decode($result->getBody(), true);
    }

    public function deleteCheckoutScript(string $scriptUuid): ?array
    {
        $result = $this->makeBigCommerceAPICall(
            'DELETE',
            [],
            'v3/content/scripts/' . $scriptUuid
        );

        if ($result->getStatusCode() !== 200) {
            return null;
        }

        return json_decode($result->getBody(), true);
    }

    public function createOrderFromCart(string $cartId): ?string
    {
        $result = $this->makeBigCommerceAPICall(
            'POST',
            [],
            'v3/checkouts/' . $cartId . '/orders'
        );

        if ($result->getStatusCode() !== 200) {
            return null;
        }

        $data = json_decode($result->getBody(), true);

        return $data['data']['id'];
    }

    public function updateOrderStatus(int $orderId, OrderStates $status)
    {
        $result = $this->makeBigCommerceAPICall(
            'PUT',
            ['status_id' => $status],
            'v2/orders/' . $orderId
        );

        if ($result->getStatusCode() !== 200) {
            return null;
        }

        $data = json_decode($result->getBody(), true);

        return $data['id'];
    }

    public function ensureStoreDomain()
    {
        $setting = Setting::where('store_hash', $this->getStoreHash())->first();

        if (empty($setting->store_domain)) {
            $storeInfo = $this->getStoreInformation();
            $setting->update(['store_domain' => $storeInfo['secure_url']]);
        }
    }

    public function getStoreInformation()
    {
        $result = $this->makeBigCommerceAPICall(
            'GET',
            [],
            'v2/store'
        );

        if ($result->getStatusCode() !== 200) {
            return null;
        }

        $data = json_decode($result->getBody(), true);

        return $data;
    }
}
