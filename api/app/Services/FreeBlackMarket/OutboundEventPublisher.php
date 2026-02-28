<?php

namespace App\Services\FreeBlackMarket;

use App\Models\FbmOutboundEvent;
use Illuminate\Support\Facades\Http;

class OutboundEventPublisher
{
    public function queueAndDispatch(string $eventType, array $payload, ?string $correlationId = null): FbmOutboundEvent
    {
        $signature = $this->signPayload($payload);

        $event = FbmOutboundEvent::create([
            'event_type' => $eventType,
            'correlation_id' => $correlationId,
            'payload' => $payload,
            'signature' => $signature,
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

        $response = Http::withHeaders([
            'X-FBM-Signature' => $event->signature,
            'X-Correlation-ID' => $event->correlation_id ?? '',
            'Content-Type' => 'application/json',
        ])->post($url, [
            'event_type' => $event->event_type,
            'payload' => $event->payload,
            'correlation_id' => $event->correlation_id,
        ]);

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

    protected function signPayload(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), config('freeblackmarket.outbound_secret'));
    }
}
