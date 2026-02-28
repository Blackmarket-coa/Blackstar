<?php

namespace App\Services;

use App\Models\ShipmentBoardListing;
use App\Models\ShipmentLeg;
use App\Services\FreeBlackMarket\OutboundEventPublisher;
use Illuminate\Validation\ValidationException;

class ShipmentLegProgressionService
{
    public function __construct(protected OutboundEventPublisher $publisher)
    {
    }

    public function updateLegStatus(ShipmentLeg $leg, string $status, ?string $proofHash = null, ?string $settlementRef = null, ?string $correlationId = null): ShipmentLeg
    {
        $listing = $leg->listing;

        $this->assertSequenceConstraints($leg, $status);

        if ($proofHash !== null) {
            $leg->proof_of_handoff_hash = $proofHash;
        }

        if ($settlementRef !== null) {
            $leg->settlement_ref = $settlementRef;
        }

        $leg->transitionTo($status);

        if (in_array($status, [ShipmentLeg::STATUS_IN_TRANSIT, ShipmentLeg::STATUS_HANDED_OFF, ShipmentLeg::STATUS_COMPLETED], true)) {
            $this->publisher->queueAndDispatch('shipment.leg.updated', [
                'shipment_listing_id' => $listing->id,
                'shipment_leg_id' => $leg->id,
                'sequence' => $leg->sequence,
                'status' => $leg->status,
                'from_node_id' => $leg->from_node_id,
                'to_node_id' => $leg->to_node_id,
            ], $correlationId);
        }

        if (!empty($leg->proof_of_handoff_hash)) {
            $this->publisher->queueAndDispatch('shipment.leg.handoff_proof', [
                'shipment_listing_id' => $listing->id,
                'shipment_leg_id' => $leg->id,
                'sequence' => $leg->sequence,
                'proof_of_handoff_hash' => $leg->proof_of_handoff_hash,
            ], $correlationId);
        }

        $this->evaluateListingCompletion($listing, $leg, $correlationId);

        return $leg->refresh();
    }

    protected function assertSequenceConstraints(ShipmentLeg $leg, string $nextStatus): void
    {
        if ($leg->sequence === 1) {
            return;
        }

        $prev = ShipmentLeg::query()
            ->where('shipment_board_listing_id', $leg->shipment_board_listing_id)
            ->where('sequence', $leg->sequence - 1)
            ->first();

        if (!$prev) {
            throw ValidationException::withMessages(['sequence' => ['Previous leg is missing.']]);
        }

        if ($nextStatus === ShipmentLeg::STATUS_IN_TRANSIT && $prev->status !== ShipmentLeg::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['sequence' => ['Cannot start this leg before previous leg is completed.']]);
        }
    }

    protected function evaluateListingCompletion(ShipmentBoardListing $listing, ShipmentLeg $updatedLeg, ?string $correlationId = null): void
    {
        $legs = $listing->legs()->get();

        if ($legs->contains(fn (ShipmentLeg $leg) => in_array($leg->status, [ShipmentLeg::STATUS_FAILED, ShipmentLeg::STATUS_DISPUTED], true))) {
            if ($listing->status !== ShipmentBoardListing::STATUS_DISPUTED) {
                if (in_array($listing->status, [ShipmentBoardListing::STATUS_CLAIMED, ShipmentBoardListing::STATUS_IN_TRANSIT], true)) {
                    if ($listing->status === ShipmentBoardListing::STATUS_CLAIMED) {
                        $listing->transitionTo(ShipmentBoardListing::STATUS_IN_TRANSIT);
                    }

                    $listing->transitionTo(ShipmentBoardListing::STATUS_DISPUTED);
                    $this->publisher->queueAndDispatch('shipment.disputed', [
                        'shipment_listing_id' => $listing->id,
                        'source_order_ref' => $listing->source_order_ref,
                        'status' => $listing->status,
                        'failed_leg_id' => $updatedLeg->id,
                    ], $correlationId);
                }
            }

            return;
        }

        if ($legs->isNotEmpty() && $legs->every(fn (ShipmentLeg $leg) => $leg->status === ShipmentLeg::STATUS_COMPLETED)) {
            if (in_array($listing->status, [ShipmentBoardListing::STATUS_CLAIMED, ShipmentBoardListing::STATUS_IN_TRANSIT], true)) {
                if ($listing->status === ShipmentBoardListing::STATUS_CLAIMED) {
                    $listing->transitionTo(ShipmentBoardListing::STATUS_IN_TRANSIT);
                }

                $listing->transitionTo(ShipmentBoardListing::STATUS_DELIVERED);
                $this->publisher->queueAndDispatch('shipment.delivered', [
                    'shipment_listing_id' => $listing->id,
                    'source_order_ref' => $listing->source_order_ref,
                    'status' => $listing->status,
                ], $correlationId);
            }

            return;
        }

        if ($updatedLeg->status === ShipmentLeg::STATUS_IN_TRANSIT && $listing->status === ShipmentBoardListing::STATUS_CLAIMED) {
            $listing->transitionTo(ShipmentBoardListing::STATUS_IN_TRANSIT);
            $this->publisher->queueAndDispatch('shipment.in_transit', [
                'shipment_listing_id' => $listing->id,
                'source_order_ref' => $listing->source_order_ref,
                'status' => $listing->status,
            ], $correlationId);
        }
    }
}
