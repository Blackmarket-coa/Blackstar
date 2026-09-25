<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransportClass;
use Illuminate\Http\JsonResponse;

class TransportClassController extends Controller
{
    /**
     * The catalogue an operator picks from when declaring what their node
     * can haul (PUT /api/nodes/{node}/transport-classes). Read-only: the
     * canonical rows come from TransportClassSeeder.
     */
    public function index(): JsonResponse
    {
        return response()->json(
            TransportClass::query()->orderBy('category')->orderBy('subtype')->get()
        );
    }
}
