<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Handle OAuth2 authentication and Order creation against the PayPal Sandbox API.
 */
class PayPalService
{
    /**
     * Create a PayPal Order for the given amount/currency and return the raw
     * PayPal response. The caller is responsible for converting to a currency
     * PayPal actually supports (PayPal does not accept VND, for example).
     *
     * @return array<string, mixed>
     */
    public function createOrder(float $amount, string $currency): array
    {
        return Http::withToken($this->token())
            ->post($this->baseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ]],
                // Required for the hosted "checkoutnow" redirect flow: without a return_url
                // PayPal has nowhere to send the browser back to and the "Continue" step on
                // the approval page fails with a raw METHOD_NOT_SUPPORTED API error instead
                // of completing. PayPal appends its own ?token=&PayerID= query params to
                // whichever URL we give it, so the frontend return/cancel pages read those
                // to look the payment back up via GET /api/payments?provider_order_id=.
                'application_context' => [
                    'return_url' => config('app.url').'/payments/return',
                    'cancel_url' => config('app.url').'/payments/cancel',
                    'user_action' => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                    // Default to the PayPal login screen rather than the guest/card
                    // page, so buyers with a PayPal balance see it as a pay option
                    // instead of only their saved cards.
                    'landing_page' => 'LOGIN',
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
     * Generate a browser-safe client token (a JWT) used to initialize the PayPal Web
     * SDK (Card Fields) on the frontend. Unlike client_id, this token is short-lived
     * and scoped for client-side use, so client_secret never leaves the server.
     *
     * This is a distinct grant from token(): passing response_type=client_token makes
     * PayPal issue a JWT instead of the plain Bearer token used for REST API calls —
     * the Web SDK v6 createInstance() rejects anything that isn't a JWT. The older
     * /v1/identity/generate-token endpoint returns a different (Braintree-format,
     * non-JWT) token meant for the legacy Advanced Checkout/Hosted Fields SDK, not
     * this one.
     */
    public function generateClientToken(): string
    {
        return $this->requestAccessToken(['response_type' => 'client_token']);
    }

    /**
     * Obtain an OAuth2 access token using the client credentials grant.
     */
    private function token(): string
    {
        return $this->requestAccessToken();
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function requestAccessToken(array $extra = []): string
    {
        return Http::asForm()
            ->withBasicAuth(config('paypal.client_id'), config('paypal.client_secret'))
            ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials', ...$extra])
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
