<?php

return [
    'enabled' => (bool) env('RPP_SYNC_ENABLED', false),
    'source' => rtrim((string) env('RPP_SOURCE_URL', ''), '/'),
    'token' => env('RPP_SOURCE_API_TOKEN'),
    'webhook_secret' => env('RPP_SYNC_WEBHOOK_SECRET'),
    'timeout' => (int) env('RPP_SYNC_TIMEOUT', 15),
];
