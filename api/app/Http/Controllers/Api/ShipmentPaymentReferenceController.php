<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShipmentPaymentReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentPaymentReferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ShipmentPaymentReference::query()->with('shipmentBoardListing:id,source_order_ref,status');

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
        return response()->json($shipmentPaymentReference->load('shipmentBoardListing:id,source_order_ref,status'));
    }

    public function store(Request $request): JsonResponse
    {
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
