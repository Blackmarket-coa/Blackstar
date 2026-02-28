<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShipmentBid;
use App\Models\ShipmentBoardListing;
use App\Services\ShipmentEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentBoardListingController extends Controller
{
    public function __construct(protected ShipmentEligibilityService $eligibility)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $listing = ShipmentBoardListing::create($request->validate([
            'source_order_ref' => ['required', 'string', 'max:255'],
            'claim_policy' => ['nullable', 'string', 'in:first_claim,bid'],
            'jurisdiction' => ['nullable', 'string', 'max:255'],
            'required_transport_capabilities' => ['nullable', 'array'],
        ]) + [
            'status' => ShipmentBoardListing::STATUS_OPEN,
            'created_by_user_id' => auth()->id(),
        ]);

        return response()->json($listing, 201);
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
            ->values();

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

        $shipmentBoardListing->status = ShipmentBoardListing::STATUS_CLAIMED;
        $shipmentBoardListing->claimed_by_node_id = $node->id;
        $shipmentBoardListing->claimed_at = now();
        $shipmentBoardListing->save();

        return response()->json($shipmentBoardListing->refresh());
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

    public function updateStatus(Request $request, ShipmentBoardListing $shipmentBoardListing): JsonResponse
    {
        $status = $request->validate([
            'status' => ['required', 'in:in_transit,delivered,disputed,cancelled'],
        ])['status'];

        $node = auth()->user()->node;
        abort_if(!$node, 403, 'User is not assigned to a node.');
        abort_if($shipmentBoardListing->claimed_by_node_id !== $node->id, 403, 'Only claiming node can update status.');

        $shipmentBoardListing->transitionTo($status);

        return response()->json($shipmentBoardListing->refresh());
    }
}
