

## What is Blackstar Federated Logistics Protocol (FLP)?

Blackstar FLP is a federated, multi-tenant logistics coordination protocol derived from Fleetbase and tailored for FreeBlackMarket + Blackout interoperability.

Unlike traditional dispatch software, Blackstar FLP centers on **independent node autonomy**:

- Vendors publish shipment demand.
- Eligible nodes discover and claim work voluntarily.
- Nodes execute transport independently (single-leg or relay).
- Lifecycle and compliance artifacts are synchronized through auditable APIs/events.

Blackstar FLP is a **software coordination layer**, not a carrier, dispatcher, or custody wallet.

### Core platform posture

- **Federated architecture**: independent node operators coordinate without centralized force-assignment.
- **Transport-agnostic model**: capabilities are declared through transport classes.
- **Non-custodial settlement boundary**: payment references are tracked, principal custody is out-of-platform.
- **Governance-reference integration**: Blackout rooms/decision refs are logged for auditability.
- **Vendor-safe visibility contract**: payloads are allowlisted and tested to prevent leakage of private coordination data.

## 🎯 Who Is Blackstar FLP For?

Blackstar FLP is designed for ecosystems that need interoperable fulfillment without centralized operational control:

- **Marketplace operators** needing checkout-to-shipment lifecycle sync via event contracts.
- **Independent logistics nodes** (LLCs, co-ops, owner-operators) that need claim-based workload access.
- **Federated delivery networks** requiring relay handoffs, trust scoring, and auditable governance references.
- **Compliance-conscious operators** that require attestation workflows and explicit responsibility boundaries.
- **Protocol integrators/developers** extending APIs, webhooks, and policy-safe visibility layers.

## Visual Feature Showcase

| Feature | Screenshot | Description |
|---------|------------|-------------|
| **Order Board** | <img src="https://flb-assets.s3.ap-southeast-1.amazonaws.com/static/order-board-kanban.png" alt="Fleetbase Order Board" width="600" /> | Visualize and manage your orders with a dynamic Kanban board. |
| **Order Config** | <img src="https://flb-assets.s3.ap-southeast-1.amazonaws.com/static/order-workflow-config.png" alt="Fleetbase Order Configuration" width="600" /> | Create custom order configurations with logic, rules, automation, activity flows, and custom fields. |
| **Order Tracking** | <img src="https://flb-assets.s3.ap-southeast-1.amazonaws.com/static/order-map-view.png" alt="Fleetbase Order Map View" width="600" /> | Track individual orders in real-time on an interactive map. |
| **Live Fleet Map** | <img src="https://flb-assets.s3.ap-southeast-1.amazonaws.com/static/live-map-tracking.png" alt="Fleetbase Live Map Tracking" width="600" /> | Get a complete overview of your fleet and active orders on a live map. |
| **Service Zones** | <img src="https://flb-assets.s3.ap-southeast-1.amazonaws.com/static/fleet-map-zones.png" alt="Fleetbase Fleet Map with Zones" width="600" /> | Define and manage service areas and zones for your fleet. |

**Quickstart**

```bash
npm install -g @fleetbase/cli
flb install-fleetbase
```

## 📖 Table of contents

  - [Features](#-features)
  - [Install](#-install)
  - [Extensions](#-extensions)
  - [Apps](#-apps)
  - [Roadmap](#-roadmap)
  - [Deployment Options](#-deployment-options)
  - [Bugs and Feature Requests](#-bugs-and--feature-requests)
  - [Documentation](#-documentation)
  - [Contributing](#-contributing)
  - [Community](#-community)
  - [Creators](#creators)
  - [License & Copyright](#license--copyright)

## 📦 Features (Detailed)

### 1) Federated Shipment Board
- Claim-based listing lifecycle (`open -> claimed -> in_transit -> delivered/disputed/cancelled`).
- No centralized dispatcher force-assigning work.
- Eligibility filtering by node activation + attestation + transport capability constraints.

### 2) FreeBlackMarket Interoperability
- Inbound webhook contract supports `order.created`, `delivery.option.selected`, `order.cancelled`.
- Outbound lifecycle events include `shipment.claimed`, `shipment.in_transit`, `shipment.delivered`, `shipment.disputed`, `shipment.cancelled`.
- Idempotent inbound receipt handling with retry/dead-letter transitions.

### 3) Correlation and Traceability
- Correlation ID propagation from inbound requests to outbound events and API responses.
- Persisted correlation references for cross-system incident and audit tracing.

### 4) Node Tenancy and Capability Model
- Node-scoped operational ownership boundaries.
- Transport class matching (`category`, `subtype`, limits, hazard/regulatory/insurance flags).
- Eligibility checks that block non-attested or incompatible nodes from claiming restricted listings.

### 5) Inter-Node Relay Workflow
- Shipment leg sequencing with handoff proof and settlement references.
- Multi-leg completion/dispute semantics with auditable leg history.
- Relay-oriented event emission for leg updates and proofs.

### 6) Governance Audit References (Blackout)
- Stores governance room IDs and decision references for auditability.
- Explicitly keeps vote/proposal workflow logic out of logistics core.
- Append-only governance outcome logging APIs.

### 7) Non-Custodial Payment Reference Layer
- Tracks buyer->vendor, vendor->node, and platform fee references.
- Designed to avoid pooled custody of shipment principal.
- Reconciliation-oriented APIs and data references.

### 8) Vendor Visibility Safety Controls
- Allowlist-based response payload shaping for vendor-facing listing APIs.
- Denylist coverage for route internals, private coordination data, and non-required telemetry.
- Contract tests to guard payload regressions.

### 9) Release and Test Reliability Tooling
- Deterministic API preflight/setup scripts for local and CI.
- CI critical-suite job with log artifact publishing.
- Staging E2E and incident-readiness reporting artifacts under `reports/`.

### 10) Incident Readiness Assets
- Runbooks for signature mismatch/outage, replay storms, dead-letter growth, and rollback scenarios.
- Simulation scripts/logs with responder timeline and command evidence.
- Readiness scorecards and prioritized action items.

## 💾 Install

The easiest way to get started with Blackstar FLP is using the Fleetbase CLI bootstrap path in this repository, which automates Docker-based installation for protocol services. For non-Docker installation patterns, use the upstream install guide as a Laravel/Fleetbase baseline and apply this repository's federated configuration overlays.

### Prerequisites
- Node.js (v14 or higher)
- Docker and Docker Compose
- Git

### Quick Install with CLI

```bash
# Install the Fleetbase CLI globally
npm install -g @fleetbase/cli

# Run the interactive installer
flb install-fleetbase
```

### Alternative Install Script

You can also use the install script directly:

```bash
git clone git@github.com:fleetbase/fleetbase.git  
cd fleetbase && ./scripts/docker-install.sh
```

### Accessing Blackstar FLP
Once successfully installed and running you can access the Blackstar operations console on port 4200 and the API on port 8000.  
  
Blackstar Console: http://localhost:4200
Blackstar API: http://localhost:8000


### Railway deployment

Railway-specific config templates are available in:

- `railway.json` (API service)
- `railway.worker.json` (queue worker)
- `railway.scheduler.json` (scheduler)
- `api/docs/railway-deployment.md` (setup and env var mapping)

### Additional Configurations

**CORS:** If you're installing directly on a server you will need to configure the environment variables to the application container:
```
CONSOLE_HOST=http://{yourhost}:4200
```
If you have additional applications or frontends you can use the environment variable `FRONTEND_HOSTS` to add a comma delimited list of additional frontend hosts.

**Application Key** If you get an issue about a missing application key just run:
```bash
docker compose exec application bash -c "php artisan key:generate --show"
```
Next copy this value to the `APP_KEY` environment variable in the application container and restart.
  
**Routing:** Fleetbase ships with a default OSRM server hosted by [router.project-osrm.org](https://router.project-osrm.org) but you're able to use your own or any other OSRM compatible server. You can modify this in the `console/environments` directory by modifying the .env file of the environment you're deploying and setting the `OSRM_HOST` to the OSRM server for Fleetbase to use.  
  
**Services:** There are a few environment variables which need to be set for Fleetbase to function with full features. If you're deploying with docker then it's easiest to just create a `docker-compose.override.yml` and supply the environment variables in this file.

```yaml
version: "3.8"
services:  
  application:  
    environment:  
      CONSOLE_HOST: http://localhost:4200
      MAIL_MAILER: (ses, smtp, mailgun, postmark, sendgrid)
      OSRM_HOST: https://router.project-osrm.org
      IPINFO_API_KEY:
      GOOGLE_MAPS_API_KEY:  
      GOOGLE_MAPS_LOCALE: us
      TWILIO_SID:  
      TWILIO_TOKEN:
      TWILIO_FROM:

  socket:
    environment:
      # IMPORTANT: Configure WebSocket origins for security
      # Development (localhost only - include WebSocket protocols):
      SOCKETCLUSTER_OPTIONS: '{"origins":"http://localhost:*,https://localhost:*,ws://localhost:*,wss://localhost:*"}'
      # Production (replace with your actual domain):
      # SOCKETCLUSTER_OPTIONS: '{"origins":"https://yourdomain.com:*,wss://yourdomain.com:*"}'
```

**WebSocket Security:** The `SOCKETCLUSTER_OPTIONS` environment variable controls which domains can connect to the WebSocket server. Always restrict origins to your specific domains in production to prevent security vulnerabilities.

You can learn more about full installation, and configuration in the [official documentation](https://docs.fleetbase.io/getting-started/install).

## ⌨️ Fleetbase CLI 

The Fleetbase CLI remains the primary bootstrap tool for managing a Blackstar deployment derived from Fleetbase. It simplifies installation, extension management, authentication, and development workflows.

Install the CLI globally with npm:

```bash
npm install -g @fleetbase/cli
```

### Available Commands

| Command | Description |
|---------|-------------|
| `flb install-fleetbase` | Install Fleetbase using Docker with interactive setup |
| `flb set-auth <token>` | Set your registry authentication token for installing extensions |
| `flb search [query]` | Search and browse available extensions |
| `flb install <extension>` | Install an extension to your Fleetbase instance |
| `flb uninstall <extension>` | Uninstall an extension from your instance |
| `flb register` | Register a Registry Developer Account |
| `flb verify` | Verify your developer account email |
| `flb login` | Authenticate with the registry (for publishing extensions) |
| `flb scaffold` | Scaffold a new extension for development |
| `flb publish` | Publish an extension to the registry |
| `flb generate-token` | Generate or regenerate your registry authentication token |

# 🧩 Extensions 

Extensions are modular components that enhance the functionality of your Blackstar instance. They allow you to add new features, customize existing behavior, or integrate with external systems.

### Browsing Extensions

```bash
flb search              # list all extensions
flb search fleet        # search by keyword
flb search --category logistics
flb search --free
flb search --json       # machine-readable output
```

### Installing Extensions

To install extensions on a self-hosted instance:

```bash
# 1. Register an account (one-time)
flb register

# 2. Verify your email (one-time)
flb verify -e your-email@example.com -c verification-code

# 3. Generate your registry token
flb generate-token -e your-email@example.com

# 4. Set your authentication token
flb set-auth your-registry-token-here

# 5. Install an extension
flb install <extension>
```

**Example:**
```bash
flb install fleetbase/pallet
flb install fleetbase/fleetops
```

### Developing Extensions

You can develop and publish your own extensions to extend Fleetbase's functionality or monetize through the marketplace. Learn more in the [extension building guide](https://docs.fleetbase.io/developers/building-an-extension).

```bash
# 1. Register a developer account (one-time)
flb register

# 2. Verify your email
flb verify -e your-email@example.com -c verification-code

# 3. Scaffold a new extension
flb scaffold

# 4. Authenticate for publishing
flb login -u your-username -p your-password -e your-email@example.com

# 5. Publish to the registry
flb publish
```

# 📱 Apps

Fleetbase offers open-source mobile apps that can be customized and deployed:

| App | Description | Platform | Repository |
|-----|-------------|----------|------------|
| **Storefront App** | E-commerce/on-demand app for launching your own shop or marketplace | iOS & Android | [GitHub](https://github.com/fleetbase/storefront-app) |
| **Navigator App** | Driver app for managing orders with real-time location tracking | iOS & Android | [GitHub](https://github.com/fleetbase/navigator-app) |

## 🛣️ Roadmap

| Feature | Status | Expected Release | Description |
|---------|--------|------------------|-------------|
| **Pallet (WMS)** | 🚧 In Development | Late Q1 / Early Q2 2026 | Inventory and Warehouse Management extension |
| **Ledger** | 🚧 In Development | Late Q1 / Early Q2 2026 | Accounting and Invoicing extension |
| **AI Agent** | 🔬 Research | Q4 2026 | AI integration for system and workflow automation |
| **Dynamic Rules** | 📋 Planned | 2027 | Rule builder to trigger events, tasks, and jobs |

Want to influence our roadmap? [Join the discussion](https://github.com/orgs/fleetbase/discussions)

## 🚀 Deployment Options

| Option | Best For | Setup Time | Maintenance |
|--------|----------|------------|-------------|
| **Docker (Local)** | Development & Testing | 5 minutes | Self-managed |
| **On-Premise** | Production on your own infrastructure | 30-60 minutes | Self-managed |
| **Cloud Self-Hosted** | Production (AWS, GCP, Azure) | 30-60 minutes | Self-managed |
| **Fleetbase Cloud** | Quick start, no DevOps | Instant | Fully managed |

[View detailed deployment guides →](https://docs.fleetbase.io/category/deploying)

## 🐛 Bugs and 💡 Feature Requests

Have a bug or a feature request? Please check the <a href="https://github.com/fleetbase/fleetbase/issues">issue tracker</a> and search for existing and closed issues. If your problem or idea is not addressed yet, please <a href="https://github.com/fleetbase/fleetbase/issues/new">open a new issue</a>.

## 📄 Documentation

Fleetbase has comprehensive documentation to help you get started and make the most of the platform:

- **Getting Started**: [Installation Guide](https://docs.fleetbase.io/getting-started/install)
- **API Reference**: [API Documentation](https://docs.fleetbase.io/api-reference)
- **Extension Development**: [Building Extensions](https://docs.fleetbase.io/developers/building-an-extension)
- **Deployment**: [Deployment Guides](https://docs.fleetbase.io/deployment)
- **Federated Logistics Compatibility**: [FreeBlackMarket + Blackout blueprint](FEDERATED_LOGISTICS_COMPATIBILITY.md)
- **API Test Quickstart (Local + CI)**: [Deterministic test bootstrap](api/docs/testing-quickstart.md)

## 🤝 Contributing

We welcome contributions from the community! Here's how you can help:

- **Report Bugs**: [Open an issue](https://github.com/fleetbase/fleetbase/issues/new)
- **Suggest Features**: [Start a discussion](https://github.com/orgs/fleetbase/discussions)
- **Submit PRs**: Read our [Contributing Guide](https://github.com/fleetbase/fleetbase/blob/main/CONTRIBUTING.md)
- **Write Documentation**: Help improve our [docs](https://docs.fleetbase.io)
- **Build Extensions**: Create and share [extensions](https://docs.fleetbase.io/developers/building-an-extension)

**Development Setup**: See our [Development Installation Guide](https://docs.fleetbase.io/getting-started/install/for-development) for detailed instructions on setting up your local development environment.

## 👥 Community

Get updates on Fleetbase's development and chat with the project maintainers and community members by joining our <a href="https://discord.gg/V7RVWRQ2Wm">Discord</a>.

<ul>
  <li>Follow <a href="https://x.com/fleetbase_io">@fleetbase_io on X</a>.</li>
  <li>Read and subscribe to <a href="https://www.fleetbase.io/blog-2">The Official Fleetbase Blog</a>.</li>
  <li>Ask and explore <a href="https://github.com/orgs/fleetbase/discussions">our GitHub Discussions</a>.</li>
</ul>
<p dir="auto">See the <a href="https://github.com/fleetbase/fleetbase/releases">Releases</a> section of our GitHub project for changelogs for each release version of Fleetbase.</p>
<p>Release announcement posts on <a href="https://www.fleetbase.io/blog-2" rel="nofollow">the official Fleetbase blog</a> contain summaries of the most noteworthy changes made in each release.</p>



# License & Copyright

Fleetbase is available under a **dual-licensing model** to accommodate both open-source community users and commercial enterprises:

## Open Source License (AGPL-3.0)

By default, Fleetbase is licensed under the [GNU Affero General Public License v3.0 (AGPL-3.0)](https://www.gnu.org/licenses/agpl-3.0.html). This license allows you to use, modify, and distribute Fleetbase freely, provided that:

- Any modifications or derivative works are also made available under AGPL-3.0
- If you run a modified version as a network service, you must make the source code available to users

The AGPL-3.0 is ideal for open-source projects, academic research, and organizations committed to sharing their improvements with the community.

## Commercial License (FCL)

For organizations that require more flexibility, Fleetbase offers a **Fleetbase Commercial License (FCL)** that provides:

- **Freedom from AGPL obligations** – Deploy and modify Fleetbase without source code disclosure requirements
- **Proprietary integrations** – Build closed-source extensions and integrations
- **Commercial protections** – Warranties, indemnities, and legal assurances not provided under AGPL
- **Derivative work ownership** – Retain full ownership of your modifications and customizations
- **Flexible licensing options** – Choose from annual, monthly, or perpetual license models

### Commercial License Options

| License Type | Price | Support & Updates | Best For |
|--------------|-------|-------------------|----------|
| **Annual License** | $25,000/year | ✅ All upgrades & Business Support included | Organizations requiring continuous updates and support |
| **Monthly License** | $2,500/month | ✅ All upgrades & Business Support included | Pilot projects and short-term deployments |
| **Major Version License** | $25,000 (one-time) | ❌ No ongoing support | Stable deployments on a single major version |
| **Minor Version License** | $15,000 (one-time) | ❌ No ongoing support | Locked version deployments |

### When You Need a Commercial License

You should consider a commercial license if you:

- Want to build proprietary extensions or integrations without open-sourcing them
- Need to embed Fleetbase in a commercial product without AGPL obligations
- Require enterprise-grade support, SLAs, and legal protections
- Plan to modify Fleetbase without sharing your changes publicly

### Get a Commercial License

For more information about commercial licensing options, please contact us:

- **Email:** [hello@fleetbase.io](mailto:hello@fleetbase.io)
- **Website:** [fleetbase.io](https://fleetbase.io)

---

**Copyright © 2026 Fleetbase Pte. Ltd.** All rights reserved.

