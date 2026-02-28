<?php

namespace Tests\Unit;

use App\Services\Payments\NonCustodialPaymentGuard;
use LogicException;
use Tests\TestCase;

class NonCustodialPaymentGuardTest extends TestCase
{
    public function test_platform_cannot_hold_shipment_principal(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Platform custody is prohibited');

        app(NonCustodialPaymentGuard::class)->holdShipmentPrincipal('listing-1', 100.00);
    }

    public function test_platform_cannot_disburse_shipment_principal(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Platform disbursement is prohibited');

        app(NonCustodialPaymentGuard::class)->disburseShipmentPrincipal('listing-1', 100.00);
    }
}
