<?php

return [
    // No insecure fallbacks: an unset secret must disable the integration,
    // not silently authenticate against a value published in this repository.
    'webhook_secret' => env('FBM_WEBHOOK_SECRET'),
    'outbound_secret' => env('FBM_OUTBOUND_SECRET'),
    'outbound_url' => env('FBM_OUTBOUND_URL'),
    // Maximum accepted skew between X-FBM-Timestamp and this server's clock,
    // in seconds. Signatures outside the window are treated as replays.
    'signature_tolerance_seconds' => (int) env('FBM_SIGNATURE_TOLERANCE_SECONDS', 300),
    'max_retries' => (int) env('FBM_MAX_RETRIES', 3),
    'retry_backoff_seconds' => (int) env('FBM_RETRY_BACKOFF_SECONDS', 30),
];
