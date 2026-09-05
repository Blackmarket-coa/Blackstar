# Consolidation Status — Blackstar

Part of the 2026-08-28 seven-repo BMC consolidation review. The canonical review — audit
verdicts, decisions, and the ordered roadmap — is `docs/REPO_CONSOLIDATION_REVIEW.md` in
`Blackmarket-coa/free-black-market`.

## This repo's verdict: ~~frozen and~~ absorbed (operator decision, 2026-08-28) — unfrozen 2026-09-03

> **Unfrozen by operator decision, 2026-09-03**, recorded in the canonical
> review (`docs/REPO_CONSOLIDATION_REVIEW.md` §3, "Blackstar"). The freeze and
> the plan to archive this repo are withdrawn; the absorption stands — FBM's
> fulfillment modules remain the live implementation. The FBM↔Blackstar bridge
> stays dark by default (`FBM_BLACKSTAR_INTEGRATION=0`) until two deployments
> are paired; per-shipment sequence numbers (contract §9.3) are the open
> bilateral change on this side. The first concrete use case queued for the
> relay protocol here — micro-depot relay points for farmers-market vendors —
> is sequenced in `docs/CDFI_COOP_ROADMAP.md` §3.9 of the FBM repo: an FBM
> depot listing first, then a depot node kind a `ShipmentLeg` can hand off
> to, then pooling. The paragraph below is kept as the record of the August
> decision.

The `AGGRESSIVE_OPERATIONS_GUIDE.md` absorption is confirmed: FBM's `blackstar-fulfillment` /
`blackstar-fulfillment-provider` modules plus the per-partner HMAC bridge are the live logistics
implementation. This repo is frozen — archive it on GitHub after this branch merges. Revive
standalone node software only when a real external logistics node (an independent LLC/co-op
running its own instance) actually exists.

Context from the audit: the custom work here is 3,066 lines of Laravel (board/claim/bid, shipment
legs, node attestation/trust, the FBM event bridge — all tested); the 13 Fleetbase submodules are
empty, so the surrounding fork cannot build the Fleetbase product as checked out; the headline
"Network Advantage Engine" features (mesh routing, batch aggregation, micro-depots, reverse-auction
mechanics) exist as design docs only. The `workflows/*.md` workplans describing the old
`apps/blackstar-console` as complete are historical — that duplicate app was folded into
`console/` on this branch.

## Salvage inventory (worth carrying into FBM, per the canonical review)

- **Shipment board / claim / bid / leg data model** — `api/app/Models/ShipmentBoardListing.php`,
  `ShipmentBid.php`, `ShipmentLeg.php` and their eligibility/progression services; reference
  design for FBM's fulfillment modules.
- **Per-partner HMAC credential rotation** — `api/app/Models/NodeCredential.php` +
  `FbmCredentialCommand.php`: key-id + encrypted secret, overlap-based rotation ("issue new,
  switch sender, revoke old — no flag day"), replay-protected webhooks.
- **Non-custodial posture guard** — `api/app/Services/Payments/NonCustodialPaymentGuard.php`
  (custody unconditionally throws; payment references only). Complements FBM's
  `posture-a-guard.ts` for any future partner-node flow.
- **Node attestation + trust scoring shape** — `NodeAttestationAcceptance`, `NodeTrustScore`;
  under the reputation decision (karma_event as the single write path) these become derived
  projections, not a parallel score.
