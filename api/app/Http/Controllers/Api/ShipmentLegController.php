<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShipmentBoardListing;
use App\Models\ShipmentLeg;
use App\Services\ShipmentLegProgressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentLegController extends Controller
{
    public function __construct(protected ShipmentLegProgressionService $progression)
    {
    }

    public function index(ShipmentBoardListing $shipmentBoardListing): JsonResponse
    {
        return response()->json($shipmentBoardListing->legs()->get());
    }

    public function store(Request $request, ShipmentBoardListing $shipmentBoardListing): JsonResponse
    {
        $validated = $request->validate([
            'sequence' => ['required', 'integer', 'min:1'],
            'from_node_id' => ['nullable', 'uuid', 'exists:nodes,id'],
            'to_node_id' => ['nullable', 'uuid', 'exists:nodes,id'],
            'status' => ['nullable', 'in:pending,in_transit,handed_off,completed,failed,disputed'],
            'proof_of_handoff_hash' => ['nullable', 'string', 'max:255'],
            'settlement_ref' => ['nullable', 'string', 'max:255'],
        ]);

        $leg = ShipmentLeg::create($validated + [
            'shipment_board_listing_id' => $shipmentBoardListing->id,
            'status' => $validated['status'] ?? ShipmentLeg::STATUS_PENDING,
        ]);

        return response()->json($leg, 201);
    }

    public function update(Request $request, ShipmentBoardListing $shipmentBoardListing, ShipmentLeg $shipmentLeg): JsonResponse
    {
        abort_if($shipmentLeg->shipment_board_listing_id !== $shipmentBoardListing->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'in:in_transit,handed_off,completed,failed,disputed'],
            'proof_of_handoff_hash' => ['nullable', 'string', 'max:255'],
            'settlement_ref' => ['nullable', 'string', 'max:255'],
        ]);

        $correlationId = $request->header('X-Correlation-ID') ?: (string) str()->uuid();
        $leg = $this->progression->updateLegStatus(
            $shipmentLeg,
            $validated['status'],
            $validated['proof_of_handoff_hash'] ?? null,
            $validated['settlement_ref'] ?? null,
            $correlationId
        );

        return response()->json($leg);
    }
}
