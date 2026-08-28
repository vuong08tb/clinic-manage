<?php

return [
    'mode' => env('PAYPAL_MODE', 'sandbox'),
    'client_id' => env('PAYPAL_CLIENT_ID'),
    'client_secret' => env('PAYPAL_CLIENT_SECRET'),
    'currency' => env('PAYPAL_CURRENCY', 'USD'),

    // Invoices are denominated in VND, but PayPal does not support VND as a
    // transaction currency (see docs/visafix.md §6) — orders must be created in
    // paypal.currency instead. This is how many VND equal 1 unit of that currency,
    // used to convert the invoice amount before sending it to PayPal. Update
    // periodically to track the real exchange rate; there is no live-rate lookup.
    'exchange_rate_vnd' => (float) env('PAYPAL_EXCHANGE_RATE_VND', 25400),
];
