<?php

namespace App\Services;

use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\TransportClass;

class ShipmentEligibilityService
{
    public function isNodeEligibleForListing(Node $node, ShipmentBoardListing $listing): bool
    {
        if ($listing->status !== ShipmentBoardListing::STATUS_OPEN) {
            return false;
        }

        if (!$node->is_active || !$node->hasCompletedAttestation()) {
            return false;
        }

        if (!empty($listing->jurisdiction) && $node->jurisdiction !== $listing->jurisdiction) {
            return false;
        }

        $transportClasses = $node->transportClasses()->get();

        if ($transportClasses->isEmpty()) {
            return false;
        }

        foreach ($transportClasses as $transportClass) {
            if ($this->matchesTransportConstraints($transportClass, $listing)) {
                return true;
            }
        }

        return false;
    }

    protected function matchesTransportConstraints(TransportClass $transportClass, ShipmentBoardListing $listing): bool
    {
        if (!empty($listing->required_category) && $transportClass->category !== $listing->required_category) {
            return false;
        }

        if (!empty($listing->required_subtype) && $transportClass->subtype !== $listing->required_subtype) {
            return false;
        }

        if (!is_null($listing->required_weight_limit)) {
            if (is_null($transportClass->weight_limit) || $transportClass->weight_limit < $listing->required_weight_limit) {
                return false;
            }
        }

        if (!is_null($listing->required_range_limit)) {
            if (is_null($transportClass->range_limit) || $transportClass->range_limit < $listing->required_range_limit) {
                return false;
            }
        }

        if ($listing->requires_hazard_capability && !$transportClass->hazard_capability) {
            return false;
        }

        if (!empty($listing->required_regulatory_class) && $transportClass->regulatory_class !== $listing->required_regulatory_class) {
            return false;
        }

        if ($listing->insurance_required_flag && !$transportClass->insurance_required_flag) {
            return false;
        }

        return true;
    }
}
