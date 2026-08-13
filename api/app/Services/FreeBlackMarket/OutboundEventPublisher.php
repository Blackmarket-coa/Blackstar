<?php

namespace App\Services\FreeBlackMarket;

use App\Models\FbmOutboundEvent;
use Illuminate\Support\Facades\Http;

class OutboundEventPublisher
{
    public function queueAndDispatch(string $eventType, array $payload, ?string $correlationId = null): FbmOutboundEvent
    {
        $event = FbmOutboundEvent::create([
            'event_type' => $eventType,
            'correlation_id' => $correlationId,
            'payload' => $payload,
            // Signed per dispatch attempt: a timestamped signature computed at
            // queue time would already be stale by the time a retry fires.
            'signature' => null,
            'status' => 'pending',
            'attempts' => 0,
        ]);

        $this->dispatch($event);

        return $event->refresh();
    }

    public function dispatch(FbmOutboundEvent $event): void
    {
        $event->attempts += 1;

        $url = config('freeblackmarket.outbound_url');
        if (empty($url)) {
            $this->markFailed($event, 'Missing FBM outbound URL.');

            return;
        }

        $secret = (string) config('freeblackmarket.outbound_secret');
        if ($secret === '') {
            $this->markFailed($event, 'Missing FBM outbound secret.');

            return;
        }

        // Sign the exact bytes that go on the wire, not just the payload
        // member: an unsigned event_type or correlation_id is tamperable.
        // event_id is the outbound row's uuid — stable across retries of the
        // same event, so receivers can deduplicate at the receipt level.
        $body = (string) json_encode([
            'event_id' => $event->id,
            'event_type' => $event->event_type,
            'payload' => $event->payload,
            'correlation_id' => $event->correlation_id,
        ]);
        $timestamp = (string) now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        $event->signature = $signature;

        // A transport-level failure (DNS, refused connection, dead proxy) must
        // land in the same retry/dead-letter lane as an HTTP error — never
        // escape to the caller, where it would fail the user-facing action
        // (e.g. a claim) that triggered the event.
        try {
            $response = Http::withHeaders([
                'X-FBM-Signature' => $signature,
                'X-FBM-Timestamp' => $timestamp,
                'X-Correlation-ID' => $event->correlation_id ?? '',
            ])->withBody($body, 'application/json')->post($url);
        } catch (\Throwable $e) {
            $this->markFailed($event, 'Connection error: ' . $e->getMessage());

            return;
        }

        if ($response->successful()) {
            $event->status = 'dispatched';
            $event->dispatched_at = now();
            $event->last_error = null;
            $event->save();

            return;
        }

        $this->markFailed($event, 'HTTP ' . $response->status() . ': ' . $response->body());
    }

    public function retryPending(): void
    {
        FbmOutboundEvent::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->each(fn (FbmOutboundEvent $event) => $this->dispatch($event));
    }

    protected function markFailed(FbmOutboundEvent $event, string $error): void
    {
        $maxRetries = (int) config('freeblackmarket.max_retries', 3);
        $backoff = (int) config('freeblackmarket.retry_backoff_seconds', 30);

        $event->last_error = $error;

        if ($event->attempts >= $maxRetries) {
            $event->status = 'dead_letter';
            $event->next_attempt_at = null;
        } else {
            $event->status = 'failed';
            $event->next_attempt_at = now()->addSeconds($backoff * $event->attempts);
        }

        $event->save();
    }
}
