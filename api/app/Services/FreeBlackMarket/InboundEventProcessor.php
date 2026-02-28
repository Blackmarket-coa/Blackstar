<?php

namespace App\Services\FreeBlackMarket;

use App\Models\FbmInboundEventReceipt;
use App\Models\ShipmentBoardListing;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class InboundEventProcessor
{
    public function process(array $body, ?string $correlationId = null): FbmInboundEventReceipt
    {
        $eventId = Arr::get($body, 'event_id');
        $eventType = Arr::get($body, 'event_type');

        return DB::transaction(function () use ($body, $eventId, $eventType, $correlationId) {
            $receipt = FbmInboundEventReceipt::firstOrCreate(
                ['event_id' => $eventId],
                [
                    'event_type' => $eventType,
                    'correlation_id' => $correlationId,
                    'payload' => $body,
                    'status' => 'processing',
                    'attempts' => 0,
                ]
            );

            if ($receipt->wasRecentlyCreated === false && $receipt->status === 'processed') {
                return $receipt;
            }

            $receipt->attempts += 1;

            try {
                $this->applyEvent($eventType, Arr::get($body, 'payload', []), $correlationId);
                $receipt->status = 'processed';
                $receipt->last_error = null;
                $receipt->processed_at = now();
                $receipt->next_attempt_at = null;
            } catch (\Throwable $e) {
                $maxRetries = (int) config('freeblackmarket.max_retries', 3);
                $backoff = (int) config('freeblackmarket.retry_backoff_seconds', 30);

                $receipt->last_error = $e->getMessage();
                if ($receipt->attempts >= $maxRetries) {
                    $receipt->status = 'dead_letter';
                    $receipt->next_attempt_at = null;
                } else {
                    $receipt->status = 'failed';
                    $receipt->next_attempt_at = now()->addSeconds($backoff * $receipt->attempts);
                }
            }

            $receipt->save();

            return $receipt;
        });
    }

    public function retryFailed(): void
    {
        FbmInboundEventReceipt::query()
            ->where('status', 'failed')
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '<=', now())
            ->each(fn (FbmInboundEventReceipt $receipt) => $this->process($receipt->payload, $receipt->correlation_id));
    }

    protected function applyEvent(string $eventType, array $payload, ?string $correlationId = null): void
    {
        if ($eventType === 'order.created') {
            // idempotent no-op placeholder for pre-validation pipeline.
            return;
        }

        if ($eventType === 'delivery.option.selected') {
            if (($payload['delivery_option'] ?? null) !== 'federated_delivery_network') {
                return;
            }

            ShipmentBoardListing::firstOrCreate(
                ['source_order_ref' => $payload['source_order_ref']],
                [
                    'status' => ShipmentBoardListing::STATUS_OPEN,
                    'claim_policy' => $payload['claim_policy'] ?? 'first_claim',
                    'jurisdiction' => $payload['jurisdiction'] ?? null,
                    'required_category' => $payload['required_category'] ?? null,
                    'required_subtype' => $payload['required_subtype'] ?? null,
                    'required_weight_limit' => $payload['required_weight_limit'] ?? null,
                    'required_range_limit' => $payload['required_range_limit'] ?? null,
                    'requires_hazard_capability' => (bool) ($payload['requires_hazard_capability'] ?? false),
                    'required_regulatory_class' => $payload['required_regulatory_class'] ?? null,
                    'insurance_required_flag' => (bool) ($payload['insurance_required_flag'] ?? false),
                    'required_transport_capabilities' => $payload['required_transport_capabilities'] ?? [],
                    'created_by_user_id' => $payload['created_by_user_id'],
                ]
            );

            return;
        }

        if ($eventType === 'order.cancelled') {
            $listing = ShipmentBoardListing::query()
                ->where('source_order_ref', $payload['source_order_ref'] ?? '')
                ->first();

            if ($listing && in_array($listing->status, [ShipmentBoardListing::STATUS_OPEN, ShipmentBoardListing::STATUS_CLAIMED, ShipmentBoardListing::STATUS_IN_TRANSIT], true)) {
                $listing->transitionTo(ShipmentBoardListing::STATUS_CANCELLED);
            }

            return;
        }

        throw new \RuntimeException('Unsupported event_type: ' . $eventType);
    }
}
