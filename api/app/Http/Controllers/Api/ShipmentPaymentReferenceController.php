<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesShipmentParties;
use App\Http\Controllers\Controller;
use App\Models\ShipmentBoardListing;
use App\Models\ShipmentPaymentReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentPaymentReferenceController extends Controller
{
    use AuthorizesShipmentParties;

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        // Only references for shipments the caller posted or their node carries.
        // This listed every settlement reference in the network before.
        $query = ShipmentPaymentReference::query()
            ->with('shipmentBoardListing:id,source_order_ref,status')
            ->whereHas('shipmentBoardListing', function ($q) use ($user) {
                $q->where('created_by_user_id', $user?->id);
                if (!empty($user?->node_id)) {
                    $q->orWhere('claimed_by_node_id', $user->node_id)
                        ->orWhere('current_node_id', $user->node_id);
                }
            });

        if ($request->filled('source_order_ref')) {
            $ref = $request->input('source_order_ref');
            $query->whereHas('shipmentBoardListing', fn ($q) => $q->where('source_order_ref', $ref));
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            $query->whereHas('shipmentBoardListing', fn ($q) => $q->where('status', $status));
        }

        return response()->json($query->paginate(25));
    }

    public function show(ShipmentPaymentReference $shipmentPaymentReference): JsonResponse
    {
        $this->authorizeShipmentParty($shipmentPaymentReference->shipmentBoardListing);

        return response()->json($shipmentPaymentReference->load('shipmentBoardListing:id,source_order_ref,status'));
    }

    public function store(Request $request): JsonResponse
    {
        // Authorize before validating. Validation first would answer a stranger
        // with 422 "a reference already exists for this listing", which tells
        // them something about a shipment they have no part in.
        $listing = ShipmentBoardListing::find($request->input('shipment_board_listing_id'));
        abort_unless($listing && $this->isShipmentParty($listing), 404);

        $validated = $request->validate([
            'shipment_board_listing_id' => ['required', 'uuid', 'exists:shipment_board_listings,id', 'unique:shipment_payment_references,shipment_board_listing_id'],
            'buyer_vendor_payment_ref' => ['nullable', 'string', 'max:255'],
            'vendor_node_settlement_ref' => ['nullable', 'string', 'max:255'],
            'platform_fee_ref' => ['nullable', 'string', 'max:255'],
            'correlation_id' => ['nullable', 'string', 'max:255'],
        ]);

        $paymentRef = ShipmentPaymentReference::create($validated + [
            'recorded_by_user_id' => auth()->id(),
            'correlation_id' => $validated['correlation_id'] ?? $request->header('X-Correlation-ID'),
        ]);

        return response()->json($paymentRef, 201);
    }

    public function update(Request $request, ShipmentPaymentReference $shipmentPaymentReference): JsonResponse
    {
        $this->authorizeShipmentParty($shipmentPaymentReference->shipmentBoardListing);

        $validated = $request->validate([
            'buyer_vendor_payment_ref' => ['nullable', 'string', 'max:255'],
            'vendor_node_settlement_ref' => ['nullable', 'string', 'max:255'],
            'platform_fee_ref' => ['nullable', 'string', 'max:255'],
            'correlation_id' => ['nullable', 'string', 'max:255'],
            // Explicitly reject custody fields
            'shipment_principal_amount' => ['prohibited'],
            'custody_wallet_id' => ['prohibited'],
            'disbursement_reference' => ['prohibited'],
        ]);

        $shipmentPaymentReference->update($validated);

        return response()->json($shipmentPaymentReference->refresh());
    }
}
