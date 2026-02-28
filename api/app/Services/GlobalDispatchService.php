<?php

namespace App\Services;

use App\Models\ShipmentBoardListing;

class GlobalDispatchService
{
    public function autoAssign(ShipmentBoardListing $listing): ?string
    {
        if (config('dispatch.automatic_global_assignment') === true) {
            // Guardrail: shipment board model does not support force assignment.
            return null;
        }

        return null;
    }
}
