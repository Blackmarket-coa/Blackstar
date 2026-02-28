<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Driver::class);

        return response()->json(Driver::query()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Driver::class);

        $driver = Driver::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:drivers,email'],
            'fleet_id' => ['nullable', 'uuid', 'exists:fleets,id'],
            'node_id' => ['nullable', 'uuid', 'exists:nodes,id'],
        ]));

        return response()->json($driver, 201);
    }

    public function show(Driver $driver): JsonResponse
    {
        $this->authorize('view', $driver);

        return response()->json($driver);
    }

    public function update(Request $request, Driver $driver): JsonResponse
    {
        $this->authorize('update', $driver);

        $driver->update($request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:drivers,email,' . $driver->id],
            'fleet_id' => ['nullable', 'uuid', 'exists:fleets,id'],
        ]));

        return response()->json($driver->refresh());
    }

    public function destroy(Driver $driver): JsonResponse
    {
        $this->authorize('delete', $driver);
        $driver->delete();

        return response()->json(status: 204);
    }
}
