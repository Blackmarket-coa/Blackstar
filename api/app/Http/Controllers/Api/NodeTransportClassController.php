<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\TransportClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The write path for a node's transport classes.
 *
 * ShipmentEligibilityService refuses any node with no transport class, and
 * before this nothing outside factories attached one, so every real node was
 * ineligible for every listing.
 */
class NodeTransportClassController extends Controller
{
    /**
     * Replace the node's transport classes (sync, not append). Accepts
     * `transport_class_ids` (uuids), `codes` (`category.subtype`, as listed by
     * GET /api/transport-classes), or both; the union is applied. An empty
     * list clears the node's classes.
     */
    public function update(Request $request, Node $node): JsonResponse
    {
        $this->authorize('update', $node);

        $data = $request->validate([
            'transport_class_ids' => ['sometimes', 'array'],
            'transport_class_ids.*' => ['string', 'distinct', 'exists:transport_classes,id'],
            'codes' => ['sometimes', 'array'],
            'codes.*' => ['string', 'distinct', 'max:255'],
        ]);

        if (!array_key_exists('transport_class_ids', $data) && !array_key_exists('codes', $data)) {
            throw ValidationException::withMessages([
                'transport_class_ids' => ['Provide transport_class_ids or codes.'],
            ]);
        }

        $ids = $data['transport_class_ids'] ?? [];

        $codes = $data['codes'] ?? [];
        if ($codes !== []) {
            $byCode = TransportClass::query()->get()->keyBy('code');
            $unknown = array_values(array_diff($codes, $byCode->keys()->all()));
            if ($unknown !== []) {
                throw ValidationException::withMessages([
                    'codes' => ['Unknown transport class code(s): ' . implode(', ', $unknown) . '.'],
                ]);
            }

            foreach ($codes as $code) {
                $ids[] = $byCode[$code]->id;
            }
        }

        $node->transportClasses()->sync(array_values(array_unique($ids)));

        return response()->json(
            $node->transportClasses()->orderBy('category')->orderBy('subtype')->get()
                ->each(fn (TransportClass $class) => $class->makeHidden('pivot'))
        );
    }
}
