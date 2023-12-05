<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\BigCommerceService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

use GuzzleHttp\Exception\RequestException;

use GuzzleHttp\Client;

class MainController extends Controller
{
    protected $baseURL;

    protected BigCommerceService $bcService;

    public function __construct()
    {
        $this->baseURL = env('APP_URL');
        $this->bcService = new BigCommerceService();
    }

    public function install(Request $request): RedirectResponse
    {
        // Make sure all required query params have been passed
        if (!$request->has('code') || !$request->has('scope') || !$request->has('context')) {
            return redirect()->action([MainController::class, 'error'], ['error_message' => 'Not enough information was passed to install this app.']);
        }

        try {
            $client = new Client();
            $result = $client->request('POST', 'https://login.bigcommerce.com/oauth2/token', [
                'json' => [
                    'client_id' => $this->bcService->getAppClientId(),
                    'client_secret' => $this->bcService->getAppSecret($request),
                    'redirect_uri' => $this->baseURL . '/auth/install',
                    'grant_type' => 'authorization_code',
                    'code' => $request->input('code'),
                    'scope' => $request->input('scope'),
                    'context' => $request->input('context'), // store_hash
                ]
            ]);

            $statusCode = $result->getStatusCode();
            $data = json_decode($result->getBody(), true);

            if ($statusCode == 200) {
                // Store data in session.
                $request->session()->put('store_hash', $data['context']);
                $request->session()->put('access_token', $data['access_token']);
                $request->session()->put('user_id', $data['user']['id']);
                $request->session()->put('user_email', $data['user']['email']);

                // Persist the data.
                if ($setting = Setting::where('store_hash', $data['context'])->first()) {
                    $setting->update([
                        'store_access_token' => $data['access_token'],
                        'store_user_id' => $data['user']['id'],
                        //'store_user_email' => $data['user']['email'],
                    ]);
                } else {
                    $setting = Setting::create([
                        'store_hash' => $data['context'],
                        'store_access_token' => $data['access_token'],
                        'store_user_id' => $data['user']['id'],
                        //'store_user_email' => $data['user']['email'],
                    ]);
                }

                // If the merchant installed the app via an external link, redirect back to the
                // BC installation success page for this app
                if ($request->has('external_install')) {
                    return Redirect::to('https://login.bigcommerce.com/app/' . $this->bcService->getAppClientId() . '/install/succeeded');
                }
            }

            return Redirect::to('/');
        } catch (RequestException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $errorMessage = "An error occurred.";

            if ($e->hasResponse()) {
                //if ($statusCode != 500) {
                    $errorMessage = $e->getMessage();
                //}
            }

            // If the merchant installed the app via an external link, redirect back to the
            // BC installation failure page for this app
            if ($request->has('external_install')) {
                return Redirect::to('https://login.bigcommerce.com/app/' . $this->bcService->getAppClientId() . '/install/failed');
            } else {
                $request->session()->put('error_message', $errorMessage);
                return redirect()->action([MainController::class, 'error'], ['error_message' => $errorMessage]);
            }
        }
    }

    public function load(Request $request): RedirectResponse
    {
        $signedPayload = $request->input('signed_payload');
        if (!empty($signedPayload)) {
            $verifiedSignedRequestData = $this->bcService->verifySignedRequest($signedPayload, $request);
            if ($verifiedSignedRequestData !== null) {
                $request->session()->put('user_id', $verifiedSignedRequestData['user']['id']);
                $request->session()->put('user_email', $verifiedSignedRequestData['user']['email']);
                $request->session()->put('owner_id', $verifiedSignedRequestData['owner']['id']);
                $request->session()->put('owner_email', $verifiedSignedRequestData['owner']['email']);
                $request->session()->put('store_hash', $verifiedSignedRequestData['context']);

            } else {
                return redirect()->action([MainController::class, 'error'], ['error_message' => 'The signed request from BigCommerce could not be validated.']);
            }
        } else {
            return redirect()->action([MainController::class, 'error'], ['error_message' => 'The signed request from BigCommerce was empty.']);
        }

        $request->session()->regenerate();

        return Redirect::to('/');
    }

    public function uninstall(Request $request)
    {
        $storeHash = $request->session()->get('store_hash');
        $setting = Setting::where('store_hash', $storeHash)->first();

        // Delete the installed script.
        $bcService = new BigCommerceService($storeHash);
        $bcService->deleteCheckoutScript($setting->js_file_uuid);

        $request->session()->flush();

        return Redirect::to('/');
    }

    public function error(Request $request)
    {
        $errorMessage = "Internal Application Error";

        if ($request->session()->has('error_message')) {
            $errorMessage = $request->session()->get('error_message');
        }

        echo '<h4>An issue has occurred:</h4> <p>' . $errorMessage . '</p> <a href="' . $this->baseURL . '">Go back to home</a>';
    }


  public function bcApiCall(Request $request, $endpoint)
  {
    return $this->bcService->proxyBigCommerceAPIRequest($request, $endpoint);
  }
}
