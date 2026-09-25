<?php

namespace Tests\Feature;

use App\Models\FbmInboundEventReceipt;
use App\Models\FbmOutboundEvent;
use App\Models\Node;
use App\Services\FreeBlackMarket\InboundEventProcessor;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FreeBlackMarketRetryCommandTest extends TestCase
{
    use RefreshDatabase;

    private function failedReceipt(string $eventId, $nextAttemptAt): FbmInboundEventReceipt
    {
        return FbmInboundEventReceipt::create([
            'event_id' => $eventId,
            'event_type' => 'order.created',
            'correlation_id' => 'corr-' . $eventId,
            'payload' => ['event_id' => $eventId, 'event_type' => 'order.created', 'payload' => []],
            'status' => 'failed',
            'attempts' => 1,
            'last_error' => 'transient',
            'next_attempt_at' => $nextAttemptAt,
        ]);
    }

    private function failedOutbound(string $ref): FbmOutboundEvent
    {
        return FbmOutboundEvent::create([
            'event_type' => 'shipment.claimed',
            'correlation_id' => 'corr-' . $ref,
            'payload' => ['source_order_ref' => $ref],
            'status' => 'failed',
            'attempts' => 1,
            'last_error' => 'HTTP 503',
            'next_attempt_at' => now()->subMinute(),
        ]);
    }

    public function test_command_retries_due_inbound_receipts_and_outbound_events(): void
    {
        config()->set('freeblackmarket.outbound_secret', 'test-outbound-secret');
        config()->set('freeblackmarket.outbound_url', 'https://fbm.example/events');
        Http::fake(['fbm.example/*' => Http::response(['ok' => true], 200)]);

        $due = $this->failedReceipt('evt-due', now()->subMinute());
        $notYet = $this->failedReceipt('evt-later', now()->addHour());
        $outbound = $this->failedOutbound('ref-1');

        $this->artisan('fbm:retry')
            ->expectsOutput('Retried 1 inbound receipt(s) and 1 outbound event(s).')
            ->assertExitCode(0);

        $this->assertSame('processed', $due->fresh()->status);
        $this->assertSame(2, $due->fresh()->attempts);
        $this->assertSame('failed', $notYet->fresh()->status, 'backoff not yet elapsed');
        $this->assertSame(1, $notYet->fresh()->attempts);
        $this->assertSame('dispatched', $outbound->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_command_is_bounded_by_limit_option(): void
    {
        config()->set('freeblackmarket.outbound_secret', 'test-outbound-secret');
        config()->set('freeblackmarket.outbound_url', 'https://fbm.example/events');
        Http::fake(['fbm.example/*' => Http::response(['ok' => true], 200)]);

        foreach (range(1, 3) as $i) {
            $this->failedReceipt('evt-' . $i, now()->subMinutes(10 - $i));
            $this->failedOutbound('ref-' . $i);
        }

        $this->artisan('fbm:retry', ['--limit' => 2])
            ->expectsOutput('Retried 2 inbound receipt(s) and 2 outbound event(s).')
            ->assertExitCode(0);

        $this->assertSame(2, FbmInboundEventReceipt::where('status', 'processed')->count());
        // Oldest-due first: the latest-due receipt is the one left for next run.
        $this->assertSame('failed', FbmInboundEventReceipt::where('event_id', 'evt-3')->first()->status);
        $this->assertSame(2, FbmOutboundEvent::where('status', 'dispatched')->count());
        Http::assertSentCount(2);
    }

    public function test_command_defaults_to_configured_batch_size(): void
    {
        config()->set('freeblackmarket.retry_batch_size', 1);

        $this->failedReceipt('evt-a', now()->subMinutes(2));
        $this->failedReceipt('evt-b', now()->subMinute());

        $this->artisan('fbm:retry')
            ->expectsOutput('Retried 1 inbound receipt(s) and 0 outbound event(s).')
            ->assertExitCode(0);
    }

    public function test_command_rejects_non_positive_limit(): void
    {
        $this->artisan('fbm:retry', ['--limit' => 0])->assertExitCode(1);
    }

    public function test_command_is_scheduled_every_five_minutes_without_overlapping(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'fbm:retry'));

        $this->assertNotNull($event, 'fbm:retry is scheduled');
        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_default_jurisdiction_config_key_is_defined_and_used(): void
    {
        $this->assertArrayHasKey('default_jurisdiction', config('freeblackmarket'));

        config()->set('freeblackmarket.default_jurisdiction', 'CA');

        app(InboundEventProcessor::class)->process([
            'event_id' => 'evt-prov-jur',
            'event_type' => 'node.operator.approved',
            'payload' => [
                'external_ref' => 'sel_jur',
                'seller_name' => 'North Couriers',
                'member_email' => 'ops@north.test',
                'credential' => ['key_id' => 'bsk_jur', 'secret' => str_repeat('b', 64)],
            ],
        ], 'corr-jur');

        $this->assertSame('CA', Node::where('node_id', 'fbm:sel_jur')->firstOrFail()->jurisdiction);
    }
}
