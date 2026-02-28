<?php

namespace App\Services;

use App\Models\Node;
use App\Models\ShipmentBoardListing;

class ShipmentEligibilityService
{
    public function isNodeEligibleForListing(Node $node, ShipmentBoardListing $listing): bool
    {
        if ($listing->status !== ShipmentBoardListing::STATUS_OPEN) {
            return false;
        }

        if (!empty($listing->jurisdiction) && $node->jurisdiction !== $listing->jurisdiction) {
            return false;
        }

        $required = $listing->required_transport_capabilities ?? [];
        $nodeCapabilities = $node->transport_capabilities ?? [];

        foreach ($required as $capability) {
            if (!in_array($capability, $nodeCapabilities, true)) {
                return false;
            }
        }

        return true;
    }
}
