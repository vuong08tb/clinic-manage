<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Handle OAuth2 authentication and Order creation against the PayPal Sandbox API.
 */
class PayPalService
{
    /**
     * Create a PayPal Order for the given amount and return the raw PayPal response.
     *
     * @return array<string, mixed>
     */
    public function createOrder(float $amount): array
    {
        return Http::withToken($this->token())
            ->post($this->baseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'amount' => [
                        'currency_code' => config('paypal.currency'),
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ]],
                // Required for the hosted "checkoutnow" redirect flow: without a return_url
                // PayPal has nowhere to send the browser back to and the "Continue" step on
                // the approval page fails with a raw METHOD_NOT_SUPPORTED API error instead
                // of completing.
                'application_context' => [
                    'return_url' => config('app.url'),
                    'cancel_url' => config('app.url'),
                    'user_action' => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                ],
            ])
            ->throw()
            ->json();
    }

   
    public function captureOrder(string $orderId): array
    {
        return Http::withToken($this->token())
            ->post($this->baseUrl()."/v2/checkout/orders/{$orderId}/capture", (object) [])
            ->json() ?? [];
    }

    /**
     * Obtain an OAuth2 access token using the client credentials grant.
     */
    private function token(): string
    {
        return Http::asForm()
            ->withBasicAuth(config('paypal.client_id'), config('paypal.client_secret'))
            ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()
            ->json('access_token');
    }

    /**
     * Resolve the PayPal API base URL for the configured mode.
     */
    private function baseUrl(): string
    {
        return config('paypal.mode') === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }
}