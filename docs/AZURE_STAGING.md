# Azure Staging — architecture sketch + open questions

> **Status: PARTIALLY BUILT.** The resource group, storage account, and blob containers used for artefact storage already exist in the Panwar Health tenant. The staging compute/database/deploy pipeline described below is NOT built yet.
>
> **Already created (2026-04-14):**
> - RG `careconnect` in `australiasoutheast`
> - Storage account `stcareconnect` (Standard_LRS, Hot, HTTPS-only, TLS1_2+, no public blob access)
> - Containers: `backups/` (holds the initial provider handover zip), `seed-sql/` (holds the DB dump used by local dev).

## Tenant & ownership

- **Tenant:** Panwar Health (single tenant, client-owned).
- **Billing:** Client-billed. All resources go under the Panwar Health Azure subscription, never a developer's personal account.
- **Resource group (planned):** `rg-careconnect-staging` (pending creation).
- **Staging lifecycle:** ephemeral. Spun up per change, torn down when the change ships. **Claude owns the spin-up / tear-down workflow**, not the human user.

## Target architecture

```
┌───────────────────────────────────────────────────────────────────────┐
│ Resource group: rg-careconnect-staging                                │
│                                                                       │
│   ┌─────────────────────────┐   ┌───────────────────────────────┐   │
│   │ App Service (Linux)     │   │ Azure DB for MySQL Flexible   │   │
│   │ PHP 8.3 runtime         │──▶│ 8.0, Burstable B1ms           │   │
│   │ Plan: B1 (~$13/mo)      │   │ ~$15/mo                       │   │
│   └──────────┬──────────────┘   └───────────────────────────────┘   │
│              │                                                        │
│              ▼                                                        │
│   ┌─────────────────────────┐                                        │
│   │ Storage Account (Blob)  │   shared with dev:                     │
│   │   - seed-dumps/         │     - seed SQL (sanitized)             │
│   │   - uploads/            │     - wp-content/uploads/ media        │
│   └─────────────────────────┘                                        │
└───────────────────────────────────────────────────────────────────────┘
```

### Why App Service (not Container Apps or a VM)

- Matches the production runtime model (plain PHP on Apache). No container drift to debug.
- Built-in PHP 8.3 image aligns with prod's PHP 8.3.30.
- Deployment slots enable zero-downtime swaps when promoting.
- Cheapest viable tier for a staging-class environment.

### Why MySQL Flexible (not Single)

- Flexible Server supports MySQL 8.0.x (matches prod 8.0.41). Single Server tops out at 5.7/8.0 deprecated.
- Start/stop API — lets staging scale to near-zero compute between change batches.

### Deploy flow (planned)

1. Push to the `staging` branch.
2. GitHub Actions workflow:
   - Builds nothing (WP is PHP, no build step).
   - FTP or ZipDeploy-uploads `site/` to the App Service.
   - Triggers a DB refresh if `db/init/01-seed.sql` has changed (rare).
3. App Service swaps the deployment slot → staging goes live at `https://hcp-staging-*.azurewebsites.net`.

## Spin-up / tear-down (my job)

Because staging is ephemeral, click-ops in the portal is the wrong primitive. Resources will be defined in Bicep (or Terraform) so the full environment can be created in ~10 minutes and destroyed cleanly.

```
infra/
├── staging.bicep                # App Service + MySQL + Blob + config
├── staging.parameters.dev.json
└── deploy.sh                    # az deployment group create ...
```

**Not yet written.** Will be built in the session where staging is first needed.

## Open questions

| # | Question | Current answer | Needs revisit when… |
|---|----------|---------------|---------------------|
| 1 | Staging domain | `*.azurewebsites.net` free subdomain | DNS records for `carepharma.com.au` become accessible |
| 2 | Salesforce integration | Skip on staging — plugin deactivated | A feature touches Salesforce sync |
| 3 | SMTP outbound | Skip on staging — WP Mail SMTP Pro deactivated | A feature needs real email testing; then use Azure Communication Services or a SendGrid sandbox |
| 4 | SSO / IP allowlist on staging | No — public `*.azurewebsites.net` is fine | Client asks for access controls |
| 5 | Seed data for staging | Anonymized prod dump (scrub emails, reset passwords, blank API keys) | **Before first staging boot** — this is a hard requirement, not negotiable. LMS = student PII |
| 6 | Storage account naming | `stcareconnectstaging` (24-char limit, lowercase) | Resource group is created |

## Mandatory staging validation queue

The following changes already live in the repo and **must be validated on staging before prod deploy**. See `docs/DEPLOY.md` → "🚩 High-risk pending deploys" for full context on each.

- [ ] **AHPRA PIEWS credential refactor** (`functions.php`) — first change that forced us to build staging. Failure mode = LMS signup silently breaks. Requires new AHPRA creds from the regulator before prod deploy.

This list will grow. Any time you refactor hardcoded creds, DB queries, or external-API calls out of inherited code, add the entry here at commit time — not at deploy time.

## When to actually build this

Trigger: **the first item in the mandatory queue above becomes deploy-ready**, OR the user says "time to test X on staging" OR a change is risky enough that local testing isn't sufficient. Until then, local Docker dev covers the day-to-day.
