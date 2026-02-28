# FreeBlackMarket Event Contract

> Note: In this repository layout, `docs/` is a git submodule in some environments. If unavailable, mirror this file under `api/docs/events/`.

## Inbound events (FreeBlackMarket -> Logistics Protocol)

Envelope:

```json
{
  "event_id": "string (globally unique)",
  "event_type": "order.created | delivery.option.selected | order.cancelled",
  "correlation_id": "string",
  "payload": { "...": "event specific" }
}
```

Headers:

- `X-FBM-Signature`: `HMAC_SHA256(raw_request_body, FBM_WEBHOOK_SECRET)`
- `X-Correlation-ID` (optional): used for tracing and propagated downstream

### `order.created`
- idempotent pre-validation hook (no listing creation by itself).

### `delivery.option.selected`
- when `payload.delivery_option === federated_delivery_network`, creates shipment board listing idempotently keyed by `source_order_ref`.

### `order.cancelled`
- cancels matching shipment listing when current status is `open|claimed|in_transit`.

## Outbound events (Logistics Protocol -> FreeBlackMarket)

- `shipment.claimed`
- `shipment.in_transit`
- `shipment.delivered`
- `shipment.disputed`
- `shipment.cancelled`

Envelope:

```json
{
  "event_type": "shipment.claimed",
  "correlation_id": "string",
  "payload": {
    "shipment_listing_id": "uuid",
    "source_order_ref": "string",
    "claimed_by_node_id": "uuid|null",
    "status": "string"
  }
}
```

Headers:

- `X-FBM-Signature`: `HMAC_SHA256(json_encode(payload), FBM_OUTBOUND_SECRET)`
- `X-Correlation-ID`: correlation ID from inbound/request context

## Idempotency

- Inbound receipts are persisted in `fbm_inbound_event_receipts` keyed by unique `event_id`.
- Replays with already-processed `event_id` return `202` without side effects.

## Retry & dead-letter strategy

### Inbound
- status progression: `processing` -> `processed` OR `failed` -> `dead_letter`.
- `attempts` increments on each processing attempt.
- failed events schedule `next_attempt_at` with linear backoff (`FBM_RETRY_BACKOFF_SECONDS * attempts`).
- events exceeding `FBM_MAX_RETRIES` transition to `dead_letter`.

### Outbound
- status progression: `pending` -> `dispatched` OR `failed` -> `dead_letter`.
- failed deliveries are retried by `retryPending()` using same backoff policy.

## Correlation & tracing

- Correlation ID source order:
  1. `X-Correlation-ID` header,
  2. `correlation_id` field in body,
  3. generated UUID fallback.
- Correlation ID is stored on inbound/outbound event records and emitted in outbound request headers.
