<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record which bid won a listing.
 *
 * `claim_policy` has had a `bid` value since the table was created, and
 * `submitBid` has accepted bids for just as long, but nothing could ever turn
 * one into a claim: there was no award path. Worse, `claim()` never consulted
 * `claim_policy` at all, so any eligible node could take a bid-policy listing
 * outright and the bids became decorative — the policy was both unenforced and
 * unfinishable.
 *
 * The award endpoint closes that, and the winning bid has to be recorded rather
 * than merely implied by `claimed_by_node_id`: the bid carries the amount and
 * currency the work was agreed at, which is what a payout later has to
 * reconcile against. A node's most recent bid is not necessarily the one that
 * was accepted.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipment_board_listings', function (Blueprint $table) {
            $table->foreignUuid('awarded_shipment_bid_id')
                ->nullable()
                ->after('claimed_by_node_id')
                ->constrained('shipment_bids')
                ->nullOnDelete();
            $table->timestamp('awarded_at')->nullable()->after('claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_board_listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('awarded_shipment_bid_id');
            $table->dropColumn('awarded_at');
        });
    }
};
