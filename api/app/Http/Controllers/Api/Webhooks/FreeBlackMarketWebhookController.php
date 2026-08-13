<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\FreeBlackMarket\InboundEventProcessor;
use App\Services\FreeBlackMarket\OutboundEventPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FreeBlackMarketWebhookController extends Controller
{
    public function __construct(
        protected InboundEventProcessor $processor,
        protected OutboundEventPublisher $publisher
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $this->verifySignature($request);

        $correlationId = $request->header('X-Correlation-ID') ?: ($request->input('correlation_id') ?: (string) str()->uuid());
        $receipt = $this->processor->process($request->all(), $correlationId);

        return response()->json([
            'status' => $receipt->status,
            'event_id' => $receipt->event_id,
            'correlation_id' => $correlationId,
            'attempts' => $receipt->attempts,
        ], 202);
    }

    public function retry(): JsonResponse
    {
        $this->processor->retryFailed();
        $this->publisher->retryPending();

        return response()->json(['status' => 'ok']);
    }

    protected function verifySignature(Request $request): void
    {
        $secret = (string) config('freeblackmarket.webhook_secret');
        abort_if($secret === '', 503, 'FreeBlackMarket integration is not configured.');

        $timestamp = (string) $request->header('X-FBM-Timestamp', '');
        abort_unless(ctype_digit($timestamp), 401, 'Missing or malformed timestamp.');

        $tolerance = (int) config('freeblackmarket.signature_tolerance_seconds', 300);
        abort_if(abs(now()->getTimestamp() - (int) $timestamp) > $tolerance, 401, 'Stale signature.');

        $incoming = (string) $request->header('X-FBM-Signature', '');
        $expected = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secret);

        abort_unless(hash_equals($expected, $incoming), 401, 'Invalid signature.');
    }
}
