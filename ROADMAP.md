# Blackstar roadmap — dependencies recorded from the BMC launch plan

Recorded 2026-10-04 from the operator's Black Mask launch spec (2026-10-03).
The whole plan is in the free-black-market repo at
`docs/BLACK_MASK_LAUNCH_PLAN.md`; the Blackout steps are in that repo's
`docs/black-mask-chat-panel-and-account-link.md`. This file holds what touches
Blackstar, which in this plan is **deferred**.

## Position in the launch order

Blackout, Black Mask and FBM launch first. Vending units come later, funded by
income from the existing structure. Blackstar is deferred until vending reaches
its third stage.

## Steps

| # | Step | Depends on | Notes |
|---|---|---|---|
| S1 | **Vending stage 3 — vendor-stocked network.** FBM vendors stock vending units on consignment and **Blackstar micro-depots serve as restock points.** Units need their own revenue line (host fees, leasing, or a per-unit software fee) because a 3% commission cannot pay for hardware. | Vending stage 1 (a "unit OS" sold to operators who already own machines) and stage 2 (three to five BMC-owned units with BMC's own products) | Stage 2 must stand on its own and not wait on marketplace volume: FBM had 2 vendors and 3 products live and Blackstar was not deployed when the plan was written. |
| S2 | **Enable GitHub Actions** before any vending work relies on CI here. The Actions API reported **0 runs ever** for this repository on 2026-10-04; the nine workflow files have never executed. | Operator setting | A green checkmark here has never meant anything (see `CLAUDE.md`). Expect the first run to surface a backlog that is not any one change's fault. |
| S3 | **Deploy.** Blackstar is not deployed. The micro-depot role in S1 assumes a running API and console. | Phase 0 infrastructure capacity on the DL360, after the vault and Synapse load are understood | Not scheduled. |

## Not in scope for Blackstar in this plan

Billing (FBM), the hosted vault and its standby (Black Mask fork, DL360 and
backup server), the chat panel and account link (Blackout), and sovereign
identity beyond the optional account link. The securities-law review that gates
community capital circles is not a Blackstar concern.
