<?php

return [
    'webhook_secret' => env('FBM_WEBHOOK_SECRET', 'fbm_webhook_secret'),
    'outbound_secret' => env('FBM_OUTBOUND_SECRET', 'fbm_outbound_secret'),
    'outbound_url' => env('FBM_OUTBOUND_URL'),
    'max_retries' => (int) env('FBM_MAX_RETRIES', 3),
    'retry_backoff_seconds' => (int) env('FBM_RETRY_BACKOFF_SECONDS', 30),
];
