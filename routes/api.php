<?php

use App\Http\Controllers\BtcpayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::any('create-invoice', [BtcpayController::class, 'createInvoice'])->middleware('disable_cors');
Route::options('create-invoice', function () {
})->middleware('disable_cors');

// Webhook endpoint updated by BTCPay Server instances.
Route::post('webhook/{setting}', [BtcpayController::class, 'processWebhook']);
