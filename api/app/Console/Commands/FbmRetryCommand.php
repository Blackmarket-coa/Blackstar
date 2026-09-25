<?php

namespace App\Console\Commands;

use App\Services\FreeBlackMarket\InboundEventProcessor;
use App\Services\FreeBlackMarket\OutboundEventPublisher;
use Illuminate\Console\Command;

/**
 * Scheduled counterpart of POST /api/webhooks/freeblackmarket/retry: the same
 * service calls, so failed inbound receipts and pending outbound events are
 * retried without anyone having to hit the endpoint. Each lane is bounded by
 * --limit (default freeblackmarket.retry_batch_size); the backoff and
 * dead-letter rules are the services' own, unchanged.
 */
class FbmRetryCommand extends Command
{
    protected $signature = 'fbm:retry
        {--limit= : Max inbound receipts and max outbound events to retry this run}';

    protected $description = 'Retry failed inbound and pending outbound FreeBlackMarket events whose backoff has elapsed';

    public function handle(InboundEventProcessor $processor, OutboundEventPublisher $publisher): int
    {
        $limit = $this->option('limit') ?? config('freeblackmarket.retry_batch_size', 100);
        if (! is_numeric($limit) || (int) $limit < 1) {
            $this->error('--limit must be a positive integer.');

            return self::FAILURE;
        }
        $limit = (int) $limit;

        $inbound = $processor->retryFailed($limit);
        $outbound = $publisher->retryPending($limit);

        $this->info("Retried {$inbound} inbound receipt(s) and {$outbound} outbound event(s).");

        return self::SUCCESS;
    }
}
