<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContributionCreditsDeferredTest extends TestCase
{
    use RefreshDatabase;

    public function test_contribution_credits_module_is_explicitly_deferred_in_core_runtime(): void
    {
        $this->assertFalse(Schema::hasTable('contribution_credits'));

        $this->getJson('/api/contribution-credits')->assertNotFound();
    }
}
