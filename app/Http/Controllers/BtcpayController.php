<?php

namespace App\Http\Controllers;

use App\Enums\OrderStates;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\BigCommerceService;
use BTCPayServer\Client\Invoice;
use BTCPayServer\Client\Webhook;
use BTCPayServer\Util\PreciseNumber;
use Illuminate\Http\Request;

class BtcpayController extends Controller
{
    protected $storeHash;
    protected $settings;

    /*
        public function __construct()
        {
          $this->storeHash = session('store_hash');
          $this->settings = Setting::where('store_hash', 'stores/' . $this->storeHash)->first();
          ##$this->settings = Setting::where('store_hash', 'stores/tknzsgwjgx')->first();
        }
    */
    public function createInvoice(Request $request)
    {
#error_log(print_r($request->headers->all(), true));
#error_log(print_r($this->settings, true));
#error_log(print_r(session()->all(), false));

        // todo: add logic to reuse non-expired invoice id and order id.

        // Load settings from db.
        $this->settings = Setting::where('store_hash', 'stores/' . $request->input('storeId'))->first();

        if (!$this->settings) {
            throw new \Exception("Store not found, aborting.");
        }

        // todo: validate input data

        // Create order on BigCommerce
        $bcService = new BigCommerceService($this->settings->store_hash);
        $orderId = $bcService->createOrderFromCart($request->input('cartId'));
        if (!$orderId) {
            throw new \Exception("Error creating order on BigCommerce, aborting.");
        }

        $client = new Invoice($this->settings->url, $this->settings->api_key);
        try {
            $options = new \BTCPayServer\Client\InvoiceCheckoutOptions();
            $options->setRedirectURL($this->settings->store_domain . '/checkout/order-confirmation');

            $invoice = $client->createInvoice(
                $this->settings->store_id,
                $request->input('currency'),
                PreciseNumber::parseString($request->input('total')),
                $request->input('cartId'),
                null,
                null,
                $options
            );

            // Store transaction.
            $transaction = Transaction::create([
                'setting_id' => $this->settings->id,
                'cart_id' => $request->input('cartId'),
                'order_id' => $orderId,
                'invoice_id' => $invoice->getId(),
                'invoice_status' => $invoice->getStatus(),
            ]);

            // Update order status to awaiting payment?

            // todo: Update the order with the invoice id + maybe link to invoice? staff note or metadata field

            return $invoice->getData() + ['orderId' => $orderId];

        } catch (\Throwable $e) {
            error_log($e->getMessage());
        }
    }

    protected function createWebhook($storeHash, $force = false)
    {
        $this->settings = Setting::where('store_hash', 'stores/' . $storeHash)->first();

        $client = new \BTCPayServer\Client\Webhook($this->settings->url, $this->settings->api_key);
        try {
            $webhook = $client->createWebhook(
                $this->settings->store_id,
                env('APP_URL') . '/webhook/' . $this->settings->id,
            );

            $this->settings->webhook_secret = $webhook->getWebhookSecret();
            $this->settings->webhook_id = $webhook->getId();
            $this->settings->save();

            return $webhook->getId();
        } catch (\Throwable $e) {
            error_log($e->getMessage());
        }
    }

    public function processWebhook(Setting $setting, Request $request)
    {
        $rawPostData = file_get_contents("php://input");

        if (!$transaction = Transaction::where('invoice_id', $request->input('invoiceId'))->first()) {
            error_log('No transaction found for invoiceId: ' . $request->input('invoiceId'));
            return;
        }

##        // todo: validate webhook request
##        if (!$this->validateWebhookRequest($rawPostData, $setting->webhook_secret)) {
##            error_log('Failed to validate webhook request.');
##            return;
##        }


        $postData = json_decode($rawPostData, false, 512, JSON_THROW_ON_ERROR);

        if (!isset($postData->invoiceId)) {
            error_log('No BTCPay invoiceId provided, aborting.');
            return;
        }

        // Update invoice status on transaction.
        $transaction->update(['invoice_status' => $postData->type]);

        // Update order status on BC.
        $bcService = new BigCommerceService($setting->store_hash);

        switch ($postData->type) {
            case 'InvoiceReceivedPayment':
                if ($postData->afterExpiration) {
                    $bcService->updateOrderStatus($transaction->order_id, OrderStates::PENDING);
                    #$order->add_order_note(__('Invoice (partial) payment incoming (unconfirmed) after invoice was already expired.', 'btcpay-greenfield-for-woocommerce'));
                } else {
                    // No need to change order status here, only leave a note.
                    #$order->add_order_note(__('Invoice (partial) payment incoming (unconfirmed). Waiting for settlement.', 'btcpay-greenfield-for-woocommerce'));
                }

                // Store payment data (exchange rate, address).
                #$this->updateWCOrderPayments($orderId);

                break;
            case 'InvoicePaymentSettled':
                if ($postData->afterExpiration) {
                    // Check if also the invoice is now fully paid. Needed because expired invoices can be paid fully after expiration.
                    if (GreenfieldApiHelper::invoiceIsFullyPaid($postData->invoiceId)) {
                        $bcService->updateOrderStatus($transaction->order_id, OrderStates::COMPLETED);
                        #$order->add_order_note(__('Invoice fully settled after invoice was already expired. Needs manual checking.', 'btcpay-greenfield-for-woocommerce'));
                    } else {
                        $bcService->updateOrderStatus($transaction->order_id, OrderStates::MANUAL_VERIFICATION_REQUIRED);
                        #$order->add_order_note(__('(Partial) payment settled but invoice not settled yet (could be more transactions incoming). Needs manual checking.', 'btcpay-greenfield-for-woocommerce'));
                    }
                } else {
                    // No need to change order status here, only leave a note.
                    #$order->add_order_note(__('Invoice (partial) payment settled.', 'btcpay-greenfield-for-woocommerce'));
                }

                // Store payment data (exchange rate, address).
                #$this->updateWCOrderPayments($order);
                break;
            case 'InvoiceProcessing': // The invoice is paid in full.
                $bcService->updateOrderStatus($transaction->order_id, OrderStates::AWAITING_PAYMENT);
                if (isset($postData->overPaid) && $postData->overPaid) {
                    #$order->add_order_note(__('Invoice payment received fully with overpayment, waiting for settlement.', 'btcpay-greenfield-for-woocommerce'));
                } else {
                    #$order->add_order_note(__('Invoice payment received fully, waiting for settlement.', 'btcpay-greenfield-for-woocommerce'));
                }
                break;
            case 'InvoiceInvalid':
                $bcService->updateOrderStatus($transaction->order_id, OrderStates::CANCELLED);
                if ($postData->manuallyMarked) {
                    #$order->add_order_note(__('Invoice manually marked invalid.', 'btcpay-greenfield-for-woocommerce'));
                } else {
                    #$order->add_order_note(__('Invoice became invalid.', 'btcpay-greenfield-for-woocommerce'));
                }
                break;
            case 'InvoiceExpired':
                if ($postData->partiallyPaid) {
                    $bcService->updateOrderStatus($transaction->order_id, OrderStates::MANUAL_VERIFICATION_REQUIRED);
                    #$order->add_order_note(__('Invoice expired but was paid partially, please check.', 'btcpay-greenfield-for-woocommerce'));
                } else {
                    $bcService->updateOrderStatus($transaction->order_id, OrderStates::CANCELLED);
                    #$order->add_order_note(__('Invoice expired.', 'btcpay-greenfield-for-woocommerce'));
                }
                break;
            case 'InvoiceSettled':

                if (isset($postData->overPaid) && $postData->overPaid) {
                    $bcService->updateOrderStatus($transaction->order_id, OrderStates::MANUAL_VERIFICATION_REQUIRED);
                    #$order->add_order_note(__('Invoice payment settled but was overpaid.', 'btcpay-greenfield-for-woocommerce'));
                } else {
                    $bcService->updateOrderStatus($transaction->order_id, OrderStates::AWAITING_FULFILLMENT);
                    #$order->add_order_note(__('Invoice payment settled.', 'btcpay-greenfield-for-woocommerce'));
                }

                // Store payment data (exchange rate, address).
                #$this->updateWCOrderPayments($order);

                break;
        }

        return $setting->store_hash;
    }

    public function validateWebhookRequest(string $rawPostData, string $webhookSecret): bool
    {
        // Note: getallheaders() CamelCases all headers for PHP-FPM/Nginx but for others maybe not,
        // so "BTCPay-Sig" may become "Btcpay-Sig".
        $headers = getallheaders();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'btcpay-sig') {
                $signature = $value;
            }
        }

        if (isset($signature) && Webhook::isIncomingWebhookRequestValid($rawPostData, $signature, $webhookSecret)) {
            return true;
        }

        return false;
    }
}
