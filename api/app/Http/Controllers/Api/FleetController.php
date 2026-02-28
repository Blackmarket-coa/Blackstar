<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fleet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Fleet::class);

        return response()->json(Fleet::query()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Fleet::class);

        $fleet = Fleet::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'node_id' => ['nullable', 'uuid', 'exists:nodes,id'],
        ]));

        return response()->json($fleet, 201);
    }

    public function show(Fleet $fleet): JsonResponse
    {
        $this->authorize('view', $fleet);

        return response()->json($fleet);
    }

    public function update(Request $request, Fleet $fleet): JsonResponse
    {
        $this->authorize('update', $fleet);

        $fleet->update($request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:50'],
        ]));

        return response()->json($fleet->refresh());
    }

    public function destroy(Fleet $fleet): JsonResponse
    {
        $this->authorize('delete', $fleet);
        $fleet->delete();

        return response()->json(status: 204);
    }
}
