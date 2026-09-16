<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\NodeCredential;
use App\Models\User;
use App\Services\FreeBlackMarket\InboundEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Node operators are provisioned by FBM approving a logistics seller.
 *
 * Before this, nothing in the application ever wrote `users.node_id`: the only
 * way to create an operator was a direct database write, which is why
 * NodePolicy's "you must already belong to a node to create one" had no
 * bootstrap at all.
 */
class NodeOperatorProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $overrides = []): array
    {
        return [
            'event_id' => $overrides['event_id'] ?? 'evt-' . bin2hex(random_bytes(6)),
            'event_type' => 'node.operator.approved',
            'payload' => array_merge([
                'external_ref' => 'sel_01ABC',
                'seller_id' => 'sel_01ABC',
                'seller_name' => 'Cross Town Couriers',
                'member_email' => 'dispatch@crosstown.test',
                'member_name' => 'Sam Dispatch',
                'credential' => ['key_id' => 'bsk_abc123', 'secret' => str_repeat('a', 64)],
            ], $overrides['payload'] ?? []),
        ];
    }

    private function process(array $body): void
    {
        app(InboundEventProcessor::class)->process($body, 'corr-prov-1');
    }

    public function test_an_approved_logistics_seller_becomes_a_node_operator(): void
    {
        $this->process($this->event());

        $node = Node::where('node_id', 'fbm:sel_01ABC')->firstOrFail();
        $this->assertSame('Cross Town Couriers', $node->legal_entity_name);

        $user = User::where('email', 'dispatch@crosstown.test')->firstOrFail();
        $this->assertSame($node->id, $user->node_id, 'the member is bound to their node');

        $credential = NodeCredential::where('key_id', 'bsk_abc123')->firstOrFail();
        $this->assertSame($node->id, $credential->node_id);
        $this->assertSame('active', $credential->status);
        // The cast is `encrypted`, so reading it back proves round-trip, and the
        // column itself never holds the plaintext.
        $this->assertSame(str_repeat('a', 64), $credential->secret);
    }

    public function test_the_node_starts_inactive_until_the_operator_attests(): void
    {
        $this->process($this->event());

        $node = Node::where('node_id', 'fbm:sel_01ABC')->firstOrFail();
        // FBM approving a storefront is not a carrier accepting transport
        // liability. ShipmentEligibilityService requires an active node, so an
        // operator provisioned this way cannot claim work until they attest.
        $this->assertFalse((bool) $node->is_active);
    }

    public function test_a_redelivery_does_not_mint_a_second_operator(): void
    {
        // The bridge is at-least-once by contract. Two operator accounts for one
        // seller, each holding live credentials, is the failure this guards.
        $this->process($this->event(['event_id' => 'evt-one']));
        $this->process($this->event(['event_id' => 'evt-two']));

        $this->assertSame(1, Node::where('node_id', 'fbm:sel_01ABC')->count());
        $this->assertSame(1, User::where('email', 'dispatch@crosstown.test')->count());
        $this->assertSame(1, NodeCredential::where('key_id', 'bsk_abc123')->count());
    }

    public function test_a_second_seller_gets_its_own_node(): void
    {
        $this->process($this->event());
        $this->process($this->event([
            'event_id' => 'evt-other',
            'payload' => [
                'external_ref' => 'sel_02XYZ',
                'seller_id' => 'sel_02XYZ',
                'seller_name' => 'Northside Haulage',
                'member_email' => 'ops@northside.test',
                'credential' => ['key_id' => 'bsk_def456', 'secret' => str_repeat('b', 64)],
            ],
        ]));

        $this->assertSame(2, Node::whereIn('node_id', ['fbm:sel_01ABC', 'fbm:sel_02XYZ'])->count());
        $this->assertNotSame(
            User::where('email', 'dispatch@crosstown.test')->value('node_id'),
            User::where('email', 'ops@northside.test')->value('node_id'),
            'operators must not share a node'
        );
    }

    public function test_an_event_missing_its_credential_or_email_is_refused(): void
    {
        foreach ([['credential' => []], ['member_email' => null]] as $broken) {
            $receipt = app(InboundEventProcessor::class)
                ->process($this->event(['payload' => $broken]), 'corr-bad');

            // A half-provisioned operator - a node with no credential, or a
            // credential bound to nobody - is worse than a dead-letter.
            $this->assertNotSame('processed', $receipt->status);
        }

        $this->assertSame(0, Node::where('node_id', 'like', 'fbm:%')->count());
    }
}
