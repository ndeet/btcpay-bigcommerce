<?php

namespace App\Services;

use App\Models\Setting;
use BTCPayServer\Client\Webhook;
use BTCPayServer\Result\Webhook as WebhookResult;
use BTCPayServer\Result\WebhookCreated;

class BtcPayService
{
    protected Setting $setting;

    public function __construct(string $store_hash)
    {
        $this->setting = Setting::where('store_hash', $store_hash)->first();

        if (!$this->setting) {
            throw new \Exception('No settings found for store hash: ' . $store_hash);
        }
    }

    // create webhook on BTCPay Server
    public function ensureWebhook(): void
    {
        if (empty($this->setting->webhook_id)) {
            $this->createWebhook();
        } else {
            try {
                // check if stored webhook id exists on BTCPay Server, if not create it
                $this->getWebhook($this->setting->webhook_id);
            } catch (\Throwable $e) {
                $this->createWebhook();
            }
        }
    }

    public function createWebhook(): WebhookCreated
    {
        $client = new Webhook($this->setting->url, $this->setting->api_key);

        try {
            $webhook = $client->createWebhook(
                $this->setting->store_id,
                env('APP_URL') . '/api/webhook/' . $this->setting->id,
                null,
                null
            );

            $this->setting->update([
                'webhook_id' => $webhook->getId(),
                'webhook_secret' => $webhook->getSecret()
            ]);

            return $webhook;
        } catch (\Throwable $e) {
            throw new \Exception('Error creating webhook: ' . $e->getMessage());
        }
    }

    public function getWebhook(string $webhookId): WebhookResult
    {
        $client = new Webhook($this->setting->url, $this->setting->api_key);

        try {
            $webhook = $client->getWebhook($this->setting->store_id, $webhookId);
            return $webhook;
        } catch (\Throwable $e) {
            throw new \Exception('Error getting webhook: ' . $e->getMessage());
        }
    }
}
