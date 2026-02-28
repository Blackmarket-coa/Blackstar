<?php

namespace App\Services\Payments;

use LogicException;

class NonCustodialPaymentGuard
{
    public function holdShipmentPrincipal(string $shipmentListingId, float $amount): void
    {
        throw new LogicException('Platform custody is prohibited: shipment principal cannot be held.');
    }

    public function disburseShipmentPrincipal(string $shipmentListingId, float $amount): void
    {
        throw new LogicException('Platform disbursement is prohibited: shipment principal cannot be disbursed.');
    }
}
