# Non-Custodial Payment References

This platform stores only external payment references for shipment reconciliation/reporting.

## Stored references

- `buyer_vendor_payment_ref`
- `vendor_node_settlement_ref`
- `platform_fee_ref`

## Explicitly not stored/implemented

- pooled wallets
- custody balances
- shipment principal holding
- shipment principal disbursement

## APIs

Authenticated endpoints:

- `POST /api/shipment-payment-references`
- `PATCH /api/shipment-payment-references/{id}`
- `GET /api/shipment-payment-references`
- `GET /api/shipment-payment-references/{id}`

### Reporting filters

`GET /api/shipment-payment-references` supports:

- `source_order_ref`
- `status` (from linked shipment listing)
