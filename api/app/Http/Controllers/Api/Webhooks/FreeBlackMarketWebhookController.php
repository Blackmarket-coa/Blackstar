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
        $incoming = $request->header('X-FBM-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), config('freeblackmarket.webhook_secret'));

        abort_unless(hash_equals($expected, $incoming), 401, 'Invalid signature.');
    }
}
