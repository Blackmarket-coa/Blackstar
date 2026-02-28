# Rollback Verification Checklist

- [ ] API authentication and routing healthy.
- [ ] `POST /api/webhooks/freeblackmarket` signed request accepted (`202`).
- [ ] No sustained `401 Invalid signature` anomalies.
- [ ] Shipment listing claim flow functional.
- [ ] Shipment status updates (`in_transit`, `delivered`, `disputed`) functional.
- [ ] Outbound dispatch retries recover failed events.
- [ ] Dead-letter growth stabilized.
- [ ] Vendor payload contract remains allowlisted, denylist-free.
- [ ] Incident channel updated with rollback result and timestamps.
