# FreeBlackMarket Event Contract

> Note: In this repository layout, `docs/` is a git submodule in some environments. If unavailable, mirror this file under `api/docs/events/`.

This file is the wire-format source of truth. The FBM-side operational view
(secret pairing matrix, emit sites, delivery guarantees, open items) lives in
the `free-black-market` repo at `docs/integrations/federated-logistics.md`.

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

- `X-FBM-Timestamp`: unix seconds at signing time. Requests older or newer
  than `FBM_SIGNATURE_TOLERANCE_SECONDS` (default 300) are rejected with 401 —
  this is the replay defense the QA plan requires.
- `X-FBM-Signature`: `HMAC_SHA256("{X-FBM-Timestamp}.{raw_request_body}", FBM_WEBHOOK_SECRET)`
- `X-Correlation-ID` (optional): used for tracing and propagated downstream

If `FBM_WEBHOOK_SECRET` is unset the endpoint returns 503: an unconfigured
secret disables the integration instead of authenticating against a default.

### `order.created`
- idempotent pre-validation hook (no listing creation by itself).

### `delivery.option.selected`
- when `payload.delivery_option === federated_delivery_network`, creates shipment board listing idempotently keyed by `source_order_ref`.
- `payload.created_by_user_id` is optional: FBM has no Blackstar user identity
  and omits it, so the listing creator defaults to the `FBM_SYSTEM_USER_ID`
  service account. If neither is available the event fails (and dead-letters)
  with an actionable error — no ownerless listings, no invented owners.

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
  "event_id": "uuid (outbound event id, stable across retries)",
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

`event_id` is the outbound event record's uuid. Retries of the same event
re-sign with a fresh timestamp but keep the same `event_id`, so receivers can
deduplicate at the receipt level exactly as this side does for inbound events.

Headers:

- `X-FBM-Timestamp`: unix seconds, computed fresh per delivery attempt.
- `X-FBM-Signature`: `HMAC_SHA256("{X-FBM-Timestamp}.{raw_request_body}", FBM_OUTBOUND_SECRET)` —
  the signature covers the exact serialized envelope (`event_type`, `payload`,
  `correlation_id`), not just the payload member, so no envelope field is
  tamperable.
- `X-Correlation-ID`: correlation ID from inbound/request context

Receivers should verify with a constant-time compare and enforce the same
timestamp tolerance as the inbound direction. If `FBM_OUTBOUND_SECRET` is
unset, dispatch fails closed (`failed` → `dead_letter`), never unsigned.

## Credentials & key rotation

Both directions support per-partner machine credentials in place of the
deployment-global secrets:

- `X-FBM-Key-ID` (optional in contract v1): names the credential that signed
  the request. The receiver verifies with that credential's secret. Inbound
  credentials live in `node_credentials` (issue / rotate / revoke / list via
  `php artisan fbm:credential`); secrets are encrypted at rest and printed
  exactly once at issue time. An unknown or revoked key id answers exactly
  like a bad signature.
- Rotation is overlap-based: `rotate` issues a new credential while the old
  one keeps verifying until explicitly revoked — no flag day.
- Migration path: requests without a key id fall back to the global secret
  until `FBM_REQUIRE_KEY_ID=true`, which retires the global inbound secret
  without a code change. Outbound, `FBM_OUTBOUND_KEY_ID` announces this
  deployment's credential to FBM.

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
