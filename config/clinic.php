<?php

return [
    'examination_fee' => (float) env('EXAMINATION_FEE', 0),

    // Stock at or below this level counts as running low. Zero-stock medicines are included.
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),
];
