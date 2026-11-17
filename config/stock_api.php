<?php

return [
    'enabled' => (bool) env('STOCK_API_ENABLED', true),
    'warehouse_code' => env('STOCK_API_WAREHOUSE_CODE', 'WSEHA29'),
    'token' => env('STOCK_API_TOKEN'),
    'outbound_manual_enabled' => (bool) env('STOCK_API_OUTBOUND_MANUAL_ENABLED', true),
    'rate_limit_per_minute' => (int) env('STOCK_API_RATE_LIMIT_PER_MINUTE', 60),
];
