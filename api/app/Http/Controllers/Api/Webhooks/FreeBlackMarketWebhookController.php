<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\NodeCredential;
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
        [$secret, $credential] = $this->resolveVerificationSecret($request);

        $timestamp = (string) $request->header('X-FBM-Timestamp', '');
        abort_unless(ctype_digit($timestamp), 401, 'Missing or malformed timestamp.');

        $tolerance = (int) config('freeblackmarket.signature_tolerance_seconds', 300);
        abort_if(abs(now()->getTimestamp() - (int) $timestamp) > $tolerance, 401, 'Stale signature.');

        $incoming = (string) $request->header('X-FBM-Signature', '');
        $expected = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secret);

        abort_unless(hash_equals($expected, $incoming), 401, 'Invalid signature.');

        $credential?->forceFill(['last_used_at' => now()])->save();
    }

    /**
     * Per-partner machine credentials: when the request names a key id, the
     * matching active NodeCredential's secret verifies it — the pre-pilot
     * hard gate's replacement for the deployment-global secret. The global
     * secret remains only as a migration path while FBM_REQUIRE_KEY_ID is
     * off; flipping it retires the global secret without a code change.
     *
     * An unknown or revoked key id answers exactly like a bad signature so
     * the endpoint does not confirm which key ids exist.
     *
     * @return array{0: string, 1: ?NodeCredential}
     */
    protected function resolveVerificationSecret(Request $request): array
    {
        $keyId = (string) $request->header('X-FBM-Key-ID', '');

        if ($keyId !== '') {
            $credential = NodeCredential::query()->active()->where('key_id', $keyId)->first();
            abort_if($credential === null, 401, 'Invalid signature.');

            return [(string) $credential->secret, $credential];
        }

        abort_if((bool) config('freeblackmarket.require_key_id'), 401, 'Key ID required.');

        $secret = (string) config('freeblackmarket.webhook_secret');
        abort_if($secret === '', 503, 'FreeBlackMarket integration is not configured.');

        return [$secret, null];
    }
}
