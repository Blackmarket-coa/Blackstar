<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        return response()->json(Vehicle::query()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Vehicle::class);

        $vehicle = Vehicle::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plate_number' => ['required', 'string', 'max:255', 'unique:vehicles,plate_number'],
            'fleet_id' => ['nullable', 'uuid', 'exists:fleets,id'],
            'node_id' => ['nullable', 'uuid', 'exists:nodes,id'],
        ]));

        return response()->json($vehicle, 201);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        return response()->json($vehicle);
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $vehicle->update($request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'plate_number' => ['sometimes', 'string', 'max:255', 'unique:vehicles,plate_number,' . $vehicle->id],
            'fleet_id' => ['nullable', 'uuid', 'exists:fleets,id'],
        ]));

        return response()->json($vehicle->refresh());
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('delete', $vehicle);
        $vehicle->delete();

        return response()->json(status: 204);
    }
}
