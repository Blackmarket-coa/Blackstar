# Centralized Dispatch Audit (Fleetbase fork)

> Requested destination was `docs/federation-audit.md`, but `docs/` is a git submodule and could not be initialized in this environment (SSH to GitHub blocked). This report is committed at repository root as `federation-audit.md`.

## Scope and method

- Constraint followed: **no behavior changes** were implemented.
- Audit method: static code search (`rg`), submodule topology inspection, and review of in-repo federation blueprint.

### Command evidence

```bash
# dispatch/assignment capability strings in currently checked-out code
rg -n "dispatch_order_to|assign_driver_to|assign_order_to|optimize" console/app/utils/get-permission-action.js -S

# centralization assumptions documented in migration blueprint
rg -n "automatic global dispatch assignment|global route optimization|global pricing engine|platform does not dispatch routes" FEDERATED_LOGISTICS_COMPATIBILITY.md -S

# determine whether FleetOps/Core API code is present in this checkout
git submodule status
find packages/fleetops packages/core-api packages/fleetops-data -maxdepth 2 -type f | head -n 20

# attempt to initialize docs submodule for requested output path
# (fails due SSH network restriction to github.com:22)
git submodule update --init --depth 1 docs
```

Observed output highlights:
- `console/app/utils/get-permission-action.js` contains `assign_order_to`, `assign_driver_to`, `dispatch_order_to`, and `optimize` action names.
- `FEDERATED_LOGISTICS_COMPATIBILITY.md` explicitly identifies centralized behaviors to disable/refactor.
- `packages/fleetops`, `packages/core-api`, and `packages/fleetops-data` are uninitialized submodules (`git submodule status` lines prefixed with `-`), so implementation code in those modules is not available for deep call-graph auditing in this environment.

---

## 1) Files/classes/functions that perform auto-dispatch

### A. Confirmed in currently available code

1. **Permission/action mapping for dispatch-assignment verbs**
   - File: `console/app/utils/get-permission-action.js`
   - Function: `getPermissionAction(permissionName)`
   - Signals: action tokens include `assign_order_to`, `assign_driver_to`, `dispatch_order_to`, `dispatch`, and `optimize`.
   - Interpretation: this function does not execute dispatch directly, but it proves dispatch/assignment operations exist in the permission model/UI layer.

### B. Expected implementation locations, not auditable in this checkout

The likely auto-dispatch execution logic is expected in:
- `packages/fleetops`
- `packages/core-api`
- `packages/fleetops-data`

These were not available locally because they are git submodules not initialized in this environment.

### Audit status table

| Component | Type | Auto-dispatch role | Status |
|---|---|---|---|
| `console/app/utils/get-permission-action.js#getPermissionAction` | UI permission parsing | Indirect signal only | **Observed** |
| `packages/fleetops` | extension submodule | likely dispatch orchestration | **Not present (submodule unavailable)** |
| `packages/core-api` | API submodule | likely assignment endpoints/services | **Not present (submodule unavailable)** |
| `packages/fleetops-data` | data/model submodule | likely order/driver assignment models | **Not present (submodule unavailable)** |

---

## 2) Routing and pricing components that assume centralized control

### A. Policy-level centralized assumptions (explicit)

From `FEDERATED_LOGISTICS_COMPATIBILITY.md`:
- `automatic global dispatch assignment`
- `global route optimization that decides operational routes for nodes`
- `global pricing engine that sets mandatory shipping rates`

These are explicitly identified as centralized patterns to remove.

### B. UI/permission signals (implicit)

From `console/app/utils/get-permission-action.js`:
- `optimize` action token suggests route optimization operations are exposed through permissions.
- dispatch/assignment tokens suggest centralized command verbs may exist in connected backend services.

### C. Missing code required for full inventory

Concrete routing and pricing service classes/functions could not be enumerated from source implementation because relevant submodules are absent in this checkout.

---

## 3) Migration notes: what to disable vs. refactor

> Classification uses `remove`, `replace`, `defer` language from the compatibility plan.

### Disable immediately (feature-flag/off by config)

1. **Automatic global dispatch assignment**
   - Action: disable scheduler/jobs/hooks that auto-assign orders across nodes.
   - Disposition: `remove` (or hard-disable until board/claim is live).

2. **Global route optimization that dictates node operations**
   - Action: disable centralized optimizer calls that produce mandatory execution routes.
   - Disposition: `replace` with node-local/private planning.

3. **Global mandatory pricing engine**
   - Action: disable forced global rate-setting used as authoritative final quote.
   - Disposition: `replace` with federated listing/claim and node-decided rates.

### Refactor next (protocol-preserving alternatives)

1. **Dispatch model → shipment board model**
   - Refactor from `assign/dispatch` semantics to `list/claim/bid` semantics.
   - Platform stores eligibility and status events; node decides operations.

2. **Routing model**
   - Keep transport capability checks centrally (eligibility only), but move route selection to node internals.

3. **Pricing model**
   - Platform can host reference/range data and transparency fields, but final operational pricing should be node-side and non-custodial.

### Defer (until submodules are available)

- File-by-file call-path ownership map for backend dispatch/routing/pricing code in `packages/fleetops`, `packages/core-api`, `packages/fleetops-data`.
- Endpoint/service-level deprecation matrix with exact class/function signatures.

---

## 4) Gaps and next action to complete audit

1. Initialize/fetch submodules (or provide mirrored tarballs) for:
   - `packages/fleetops`
   - `packages/core-api`
   - `packages/fleetops-data`
   - optionally `docs`
2. Re-run this audit and expand with:
   - exact file/class/function list of auto-dispatch execution paths,
   - routing/pricing service inventory,
   - ownership and disposition per path (`remove`/`replace`/`defer`).
