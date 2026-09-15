<?php

namespace App\Services;

use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\TransportClass;
use App\Support\Geo;

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

        if (!$this->servesListingCoalition($node, $listing)) {
            return false;
        }

        if (!$this->isWithinServiceRadius($node, $listing)) {
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

    /**
     * Is the listing's origin inside the node's service radius?
     *
     * `nodes.service_radius` existed from the first migration and was read by
     * nothing, because neither a node nor a listing carried a coordinate. The
     * only geography was `jurisdiction` compared as an exact string, so a node
     * in California was eligible for a listing in New York whenever both said
     * "US", and the board offered it to them. Operators were asked for a
     * service radius on every node and the answer was discarded.
     *
     * **Applies only when the question can be answered.** Three ways it cannot,
     * each returning true so the jurisdiction and transport checks still decide
     * as they did before:
     *
     *   - the node has no coordinates — pre-existing rows, until backfilled;
     *   - the listing has no origin coordinates — the same, and FBM only began
     *     sending them alongside this change;
     *   - the radius is zero, which is the column's default and therefore means
     *     "unset" far more often than "serves nowhere". Treating the default as
     *     a refusal would make every node on the platform ineligible for
     *     everything the moment this shipped.
     *
     * So this narrows eligibility only for a node that has said where it is and
     * how far it travels, against a listing that has said where it starts.
     * Everything else behaves exactly as before.
     */
    /**
     * A coalition's freight is offered to that coalition's own nodes.
     *
     * The point of handing a coalition's physical drive to the board is that
     * the coalition's members haul it — the reverse auction is meant to run
     * *among coalition members*, not to open their drive to the whole network.
     * So a listing carrying a `coalition_ref` narrows to nodes holding an
     * active membership of it.
     *
     * A listing with no `coalition_ref` — every ordinary delivery — is
     * unaffected, which is why this reads as "no coalition, no restriction"
     * rather than gating the board on membership generally.
     */
    protected function servesListingCoalition(Node $node, ShipmentBoardListing $listing): bool
    {
        $coalitionRef = (string) ($listing->coalition_ref ?? '');

        if ($coalitionRef === '') {
            return true;
        }

        return $node->belongsToCoalition($coalitionRef);
    }

    protected function isWithinServiceRadius(Node $node, ShipmentBoardListing $listing): bool
    {
        $radius = (float) ($node->service_radius ?? 0);
        if ($radius <= 0) {
            return true;
        }

        $distance = Geo::distanceMiles(
            $node->latitude,
            $node->longitude,
            $listing->origin_latitude,
            $listing->origin_longitude
        );

        if ($distance === null) {
            return true;
        }

        return $distance <= $radius;
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

        if (!is_null($listing->required_volume_limit)) {
            if (is_null($transportClass->volume_limit) || $transportClass->volume_limit < $listing->required_volume_limit) {
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
