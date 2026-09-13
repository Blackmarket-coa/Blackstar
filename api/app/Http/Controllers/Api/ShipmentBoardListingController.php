<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShipmentBid;
use App\Models\ShipmentBoardListing;
use App\Services\ShipmentEligibilityService;
use App\Services\FreeBlackMarket\OutboundEventPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentBoardListingController extends Controller
{
    public function __construct(protected ShipmentEligibilityService $eligibility, protected OutboundEventPublisher $publisher)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $listing = ShipmentBoardListing::create($request->validate([
            'source_order_ref' => ['required', 'string', 'max:255'],
            'claim_policy' => ['nullable', 'string', 'in:first_claim,bid'],
            'job_type' => ['nullable', 'string', 'in:delivery,virtual,physical'],
            'bounty_amount' => ['nullable', 'numeric', 'min:0'],
            'bounty_currency' => ['nullable', 'string', 'max:8'],
            'origin' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'work_order' => ['nullable', 'string'],
            'creator_qa_checklist' => ['nullable', 'array'],
            'creator_qa_checklist.*' => ['string', 'max:500'],
            'jurisdiction' => ['nullable', 'string', 'max:255'],
            'required_category' => ['nullable', 'string', 'max:255'],
            'required_subtype' => ['nullable', 'string', 'max:255'],
            'required_weight_limit' => ['nullable', 'numeric', 'min:0'],
            'required_volume_limit' => ['nullable', 'numeric', 'min:0'],
            'required_range_limit' => ['nullable', 'numeric', 'min:0'],
            'requires_hazard_capability' => ['nullable', 'boolean'],
            'required_regulatory_class' => ['nullable', 'string', 'max:255'],
            'insurance_required_flag' => ['nullable', 'boolean'],
            'required_transport_capabilities' => ['nullable', 'array'],
        ]) + [
            'status' => ShipmentBoardListing::STATUS_OPEN,
            'created_by_user_id' => auth()->id(),
        ]);

        return response()->json($this->vendorListingPayload($listing), 201);
    }

    public function eligibleListings(): JsonResponse
    {
        $node = auth()->user()->node;

        if (!$node) {
            return response()->json([]);
        }

        $listings = ShipmentBoardListing::query()
            ->where('status', ShipmentBoardListing::STATUS_OPEN)
            ->get()
            ->filter(fn (ShipmentBoardListing $listing) => $this->eligibility->isNodeEligibleForListing($node, $listing))
            ->values()
            ->map(fn (ShipmentBoardListing $listing) => $this->vendorListingPayload($listing));

        return response()->json($listings);
    }

    public function claim(ShipmentBoardListing $shipmentBoardListing): JsonResponse
    {
        $node = auth()->user()->node;

        abort_if(!$node, 403, 'User is not assigned to a node.');

        if (!$this->eligibility->isNodeEligibleForListing($node, $shipmentBoardListing)) {
            abort(403, 'Node is not eligible to claim this listing.');
        }

        abort_if($shipmentBoardListing->status !== ShipmentBoardListing::STATUS_OPEN, 422, 'Listing can only be claimed from open status.');

        // Honour claim_policy. A `bid` listing is awarded by whoever posted it,
        // not taken by whoever asks first — without this check the column was
        // decorative: any eligible node could claim a bid listing outright and
        // every bid submitted against it was moot.
        abort_if(
            $shipmentBoardListing->claim_policy === 'bid',
            422,
            'This listing is awarded from bids. Submit a bid and wait for the poster to award it.'
        );

        // FLP non-dispatch invariant: only explicit node claims can move a listing to claimed.
        $shipmentBoardListing->status = ShipmentBoardListing::STATUS_CLAIMED;
        $shipmentBoardListing->claimed_by_node_id = $node->id;
        $shipmentBoardListing->current_node_id = $node->id;
        $shipmentBoardListing->claimed_at = now();
        $shipmentBoardListing->save();

        $correlationId = request()->header('X-Correlation-ID') ?: (string) str()->uuid();
        $this->publisher->queueAndDispatch('shipment.claimed', [
            'shipment_listing_id' => $shipmentBoardListing->id,
            'source_order_ref' => $shipmentBoardListing->source_order_ref,
            'claimed_by_node_id' => $shipmentBoardListing->claimed_by_node_id,
            'status' => $shipmentBoardListing->status,
        ], $correlationId);

        return response()->json($this->vendorListingPayload($shipmentBoardListing->refresh(), $correlationId));
    }

    public function submitBid(Request $request, ShipmentBoardListing $shipmentBoardListing): JsonResponse
    {
        $node = auth()->user()->node;

        abort_if(!$node, 403, 'User is not assigned to a node.');
        abort_if($shipmentBoardListing->claim_policy !== 'bid', 422, 'Bidding is not enabled for this listing.');

        if (!$this->eligibility->isNodeEligibleForListing($node, $shipmentBoardListing)) {
            abort(403, 'Node is not eligible to bid on this listing.');
        }

        $bid = ShipmentBid::updateOrCreate(
            [
                'shipment_board_listing_id' => $shipmentBoardListing->id,
                'node_id' => $node->id,
            ],
            $request->validate([
                'amount' => ['required', 'numeric', 'min:0'],
                'currency' => ['nullable', 'string', 'max:8'],
                'note' => ['nullable', 'string'],
            ])
        );

        return response()->json($bid, 201);
    }

    /**
     * Award a bid-policy listing to one of its bids.
     *
     * The counterpart `claim()` refuses to provide, and the reason `claim()`
     * can now enforce `claim_policy` at all: refusing a direct claim on a bid
     * listing without an award path would have left those listings permanently
     * unclaimable, so the policy could only be honoured once there was another
     * way through.
     *
     * Authority is the listing's poster (`created_by_user_id`), not the
     * claiming node — awarding is the act of choosing between bids, which is
     * the poster's to make. Every listing has one: the FBM ingest path fills it
     * from the payload or falls back to the federated listing creator.
     *
     * Eligibility is re-checked at award time rather than trusted from when the
     * bid was placed. A node's capabilities, jurisdiction or trust standing can
     * change between bidding and awarding, and awarding assigns real work; the
     * bid is a price, not a warrant.
     *
     * Emits `shipment.claimed` with exactly the fields a direct claim emits, so
     * the relay contract does not fork on how the listing came to be claimed.
     * The awarded amount is deliberately NOT added to it: that payload is an
     * exact-keys contract shared with FBM and pinned by
     * `VendorVisibilityContractTest`, and widening it is a cross-repo change to
     * make on purpose rather than a side effect of adding this endpoint. The
     * amount is on the bid, reachable from `awarded_shipment_bid_id`, whenever
     * a payout path needs it.
     *
     * The vendor-facing response is likewise the standard listing payload,
     * unchanged. A node learns the outcome from `status` and
     * `claimed_by_node_id`; which bid won, and for how much, is the poster's
     * and the winner's business rather than every other bidder's.
     */
    public function award(Request $request, ShipmentBoardListing $shipmentBoardListing): JsonResponse
    {
        $user = auth()->user();

        abort_if(
            $shipmentBoardListing->created_by_user_id !== $user->id,
            403,
            'Only the node that posted this listing can award it.'
        );

        abort_if(
            $shipmentBoardListing->claim_policy !== 'bid',
            422,
            'Awarding applies to bid listings. This one is claimed directly.'
        );

        abort_if(
            $shipmentBoardListing->status !== ShipmentBoardListing::STATUS_OPEN,
            422,
            'Listing can only be awarded from open status.'
        );

        $bidId = $request->validate([
            'bid_id' => ['required', 'string'],
        ])['bid_id'];

        // Scoped to this listing, so a bid id from another listing reads as
        // "no such bid" rather than awarding work across listings.
        $bid = ShipmentBid::query()
            ->where('shipment_board_listing_id', $shipmentBoardListing->id)
            ->where('id', $bidId)
            ->first();

        abort_if(!$bid, 404, 'No such bid on this listing.');

        $node = $bid->node;
        abort_if(!$node, 422, 'That bid belongs to a node that no longer exists.');

        if (!$this->eligibility->isNodeEligibleForListing($node, $shipmentBoardListing)) {
            abort(422, 'That node is no longer eligible for this listing.');
        }

        $shipmentBoardListing->status = ShipmentBoardListing::STATUS_CLAIMED;
        $shipmentBoardListing->claimed_by_node_id = $node->id;
        $shipmentBoardListing->current_node_id = $node->id;
        $shipmentBoardListing->claimed_at = now();
        $shipmentBoardListing->awarded_shipment_bid_id = $bid->id;
        $shipmentBoardListing->awarded_at = now();
        $shipmentBoardListing->save();

        $correlationId = request()->header('X-Correlation-ID') ?: (string) str()->uuid();
        $this->publisher->queueAndDispatch('shipment.claimed', [
            'shipment_listing_id' => $shipmentBoardListing->id,
            'source_order_ref' => $shipmentBoardListing->source_order_ref,
            'claimed_by_node_id' => $shipmentBoardListing->claimed_by_node_id,
            'status' => $shipmentBoardListing->status,
        ], $correlationId);

        return response()->json($this->vendorListingPayload($shipmentBoardListing->refresh(), $correlationId));
    }

    public function updateStatus(Request $request, ShipmentBoardListing $shipmentBoardListing): JsonResponse
    {
        $status = $request->validate([
            'status' => ['required', 'in:in_transit,delivered,disputed,cancelled'],
        ])['status'];

        $node = auth()->user()->node;
        abort_if(!$node, 403, 'User is not assigned to a node.');
        abort_if($shipmentBoardListing->claimed_by_node_id !== $node->id, 403, 'Only claiming node can update status.');

        // Keep mutable ownership state explicit to avoid hidden auto-assignment paths.
        $shipmentBoardListing->current_node_id = $node->id;
        $shipmentBoardListing->save();
        $shipmentBoardListing->transitionTo($status);

        $eventType = 'shipment.' . $shipmentBoardListing->status;
        $correlationId = request()->header('X-Correlation-ID') ?: (string) str()->uuid();
        $this->publisher->queueAndDispatch($eventType, [
            'shipment_listing_id' => $shipmentBoardListing->id,
            'source_order_ref' => $shipmentBoardListing->source_order_ref,
            'claimed_by_node_id' => $shipmentBoardListing->claimed_by_node_id,
            'status' => $shipmentBoardListing->status,
        ], $correlationId);

        return response()->json($this->vendorListingPayload($shipmentBoardListing->refresh(), $correlationId));
    }

    protected function vendorListingPayload(ShipmentBoardListing $listing, ?string $correlationId = null): array
    {
        $payload = [
            'id' => $listing->id,
            'source_order_ref' => $listing->source_order_ref,
            'status' => $listing->status,
            'origin' => $listing->origin,
            'destination' => $listing->destination,
            'work_order' => $listing->work_order,
            'creator_qa_checklist' => $listing->creator_qa_checklist,
            'claim_policy' => $listing->claim_policy,
            'job_type' => $listing->job_type,
            'bounty_amount' => $listing->bounty_amount,
            'bounty_currency' => $listing->bounty_currency,
            'jurisdiction' => $listing->jurisdiction,
            'required_category' => $listing->required_category,
            'required_subtype' => $listing->required_subtype,
            'required_weight_limit' => $listing->required_weight_limit,
            'required_volume_limit' => $listing->required_volume_limit,
            'required_range_limit' => $listing->required_range_limit,
            'requires_hazard_capability' => $listing->requires_hazard_capability,
            'required_regulatory_class' => $listing->required_regulatory_class,
            'insurance_required_flag' => $listing->insurance_required_flag,
            'required_transport_capabilities' => $listing->required_transport_capabilities,
            'claimed_by_node_id' => $listing->claimed_by_node_id,
            'current_node_id' => $listing->current_node_id,
            'claimed_at' => $listing->claimed_at,
            'in_transit_at' => $listing->in_transit_at,
            'delivered_at' => $listing->delivered_at,
            'disputed_at' => $listing->disputed_at,
            'cancelled_at' => $listing->cancelled_at,
            'created_at' => $listing->created_at,
            'updated_at' => $listing->updated_at,
        ];

        if ($correlationId !== null) {
            $payload['correlation_id'] = $correlationId;
        }

        return $payload;
    }
}
