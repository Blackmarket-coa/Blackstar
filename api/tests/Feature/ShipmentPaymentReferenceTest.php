<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\ShipmentPaymentReference;
use App\Models\TransportClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentPaymentReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAuthedUser(): User
    {
        $node = Node::factory()->create(['jurisdiction' => 'US']);
        $tc = TransportClass::factory()->create(['category' => 'ground', 'subtype' => 'van']);
        $node->transportClasses()->attach($tc->id);

        return User::factory()->create(['node_id' => $node->id]);
    }

    public function test_stores_only_reference_fields_for_reconciliation(): void
    {
        $user = $this->makeAuthedUser();
        $creator = User::factory()->create();
        // Settlement references are writable by a party to the shipment: the
        // member who posted it, or the node carrying it. This listing is
        // claimed by the caller's node.
        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'claimed_by_node_id' => $user->node_id,
            'current_node_id' => $user->node_id,
        ]);

        $this->actingAs($user)
            ->postJson('/api/shipment-payment-references', [
                'shipment_board_listing_id' => $listing->id,
                'buyer_vendor_payment_ref' => 'pay_123',
                'vendor_node_settlement_ref' => 'set_456',
                'platform_fee_ref' => 'fee_789',
            ], ['X-Correlation-ID' => 'corr-pay-001'])
            ->assertCreated()
            ->assertJsonPath('buyer_vendor_payment_ref', 'pay_123')
            ->assertJsonPath('vendor_node_settlement_ref', 'set_456')
            ->assertJsonPath('platform_fee_ref', 'fee_789')
            ->assertJsonPath('correlation_id', 'corr-pay-001');
    }

    public function test_reconciliation_read_api_supports_filters(): void
    {
        $user = $this->makeAuthedUser();
        $creator = User::factory()->create();

        $openListing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'claimed_by_node_id' => $user->node_id,
            'current_node_id' => $user->node_id,
            'source_order_ref' => 'ORD-RECON-1',
            'status' => ShipmentBoardListing::STATUS_OPEN,
        ]);
        $deliveredListing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'claimed_by_node_id' => $user->node_id,
            'current_node_id' => $user->node_id,
            'source_order_ref' => 'ORD-RECON-2',
            'status' => ShipmentBoardListing::STATUS_DELIVERED,
        ]);

        ShipmentPaymentReference::factory()->create(['shipment_board_listing_id' => $openListing->id]);
        ShipmentPaymentReference::factory()->create(['shipment_board_listing_id' => $deliveredListing->id]);

        $this->actingAs($user)
            ->getJson('/api/shipment-payment-references?source_order_ref=ORD-RECON-2')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)
            ->getJson('/api/shipment-payment-references?status=delivered')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_update_rejects_custody_fields(): void
    {
        $user = $this->makeAuthedUser();
        $listing = ShipmentBoardListing::factory()->create([
            'claimed_by_node_id' => $user->node_id,
            'current_node_id' => $user->node_id,
        ]);
        $paymentRef = ShipmentPaymentReference::factory()->create([
            'shipment_board_listing_id' => $listing->id,
        ]);

        $this->actingAs($user)
            ->patchJson('/api/shipment-payment-references/' . $paymentRef->id, [
                'shipment_principal_amount' => 1000,
                'custody_wallet_id' => 'wallet-1',
            ])
            ->assertStatus(422);
    }

    public function test_a_stranger_can_neither_read_nor_rewrite_settlement_references(): void
    {
        $stranger = $this->makeAuthedUser();
        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => User::factory()->create()->id,
            'source_order_ref' => 'ORD-NOT-YOURS',
        ]);
        $paymentRef = ShipmentPaymentReference::factory()->create([
            'shipment_board_listing_id' => $listing->id,
            'platform_fee_ref' => 'fee_original',
        ]);

        // These reference real money moving between a buyer, a vendor, a node
        // and the platform. Every authenticated account could rewrite any of
        // them, on any shipment in the network.
        $this->actingAs($stranger)
            ->patchJson('/api/shipment-payment-references/' . $paymentRef->id, [
                'platform_fee_ref' => 'fee_hijacked',
            ])
            ->assertNotFound();
        $this->assertSame('fee_original', $paymentRef->refresh()->platform_fee_ref);

        $this->actingAs($stranger)
            ->getJson('/api/shipment-payment-references/' . $paymentRef->id)
            ->assertNotFound();

        // ...and the list endpoint does not hand them the whole network either.
        $this->actingAs($stranger)
            ->getJson('/api/shipment-payment-references')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($stranger)
            ->postJson('/api/shipment-payment-references', [
                'shipment_board_listing_id' => $listing->id,
                'platform_fee_ref' => 'fee_squatted',
            ])
            ->assertNotFound();
    }
}
