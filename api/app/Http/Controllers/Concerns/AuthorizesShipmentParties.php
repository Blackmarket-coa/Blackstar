<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ShipmentBoardListing;

/**
 * Who may write the money and movement records attached to a shipment.
 *
 * Settlement references and leg progressions are bookkeeping about real money
 * moving between a buyer, a vendor, a node and the platform. They were writable
 * by any authenticated user: `ShipmentPaymentReferenceController::update` and
 * every action on `ShipmentLegController` ran no authorization at all, so one
 * account could overwrite `platform_fee_ref`, `vendor_node_settlement_ref` and
 * `settlement_ref` on any listing in the network. That was contained only by
 * the accident that no user could obtain an account; automatic FBM-driven
 * provisioning removes that containment, so the rule has to be explicit.
 *
 * A party is the member who posted the listing, or a member of the node
 * carrying it. Per-leg nodes are deliberately NOT parties: the relay model has
 * the claiming node drive every leg of its own shipment, including handoffs to
 * and from other nodes (see tests/Feature/ShipmentLegRelayTest.php).
 */
trait AuthorizesShipmentParties
{
    protected function isShipmentParty(ShipmentBoardListing $listing): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        if ($listing->created_by_user_id === $user->id) {
            return true;
        }
        $nodeId = $user->node_id;
        if (empty($nodeId)) {
            return false;
        }
        return $nodeId === $listing->claimed_by_node_id || $nodeId === $listing->current_node_id;
    }

    /** 404, not 403: whether a shipment exists is itself not public. */
    protected function authorizeShipmentParty(ShipmentBoardListing $listing): void
    {
        abort_unless($this->isShipmentParty($listing), 404);
    }
}
