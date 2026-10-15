# Apache Superset integration and deployment plan

**Status:** Investigation and proposal; no integration or deployment has been performed.

## Recommendation

Use Apache Superset as a separate, read-only analytics service for GovStore and Snipe-IT reporting. Keep the Laravel application as the system of record for authentication, workflow, authorization and inventory mutations. Superset should query a curated reporting layer, preferably on a read replica or reporting database, rather than the application’s unrestricted operational schema.

Serve every organizational tier, from office staff through national leadership, through dashboards embedded in GovStore. Use GovStore as the identity and authorization authority. Give the Prime Minister and other national viewers national aggregate dashboards by default; office-level operational detail remains limited to the user's authorized office/company scope. Superset’s roles and row-level security are useful controls, but they do not inherit GovStore’s `TenantContext` or replace database access controls.

## Where Superset could help

The repository already has reporting-worthy, structured data in Snipe-IT and GovStore. Likely first dashboards are:

| Dashboard | Candidate measures | Intended audience |
| --- | --- | --- |
| Stock and ledger | Inbound/outbound quantities, balances derived from immutable movements, stock by office and product category, movement trends | Storekeepers and office managers for their office; company and national aggregates for their authorized scope |
| Receipt and issue activity | Posted receipts/issues over time, document counts, products and quantities, workflow status and processing time | Office operations, company management and national aggregates |
| Request workflow | Submitted, approved, fulfilled and closed requests; backlog age; fulfillment time; approved versus issued quantities | Office approvers and management; national aggregates for leadership |
| Asset inventory | Asset counts and lifecycle/status summaries by office, company, category and time | Office IT, company administrators and national aggregates for leadership |
| Data quality and reconciliation | Missing office/company links, ledger versus projected stock differences, stale drafts and incomplete references | Office owners for their own records; restricted ICT and audit roles for cross-office comparisons |

These are proposed use cases, not claims that every measure is currently complete or reconciled. In particular, balances and stock-value measures need agreed definitions and reconciliation against the posted ledger before they are treated as authoritative.

## Integration and access design

1. **Keep analytics read-only.** Use a dedicated database account with `SELECT` only on curated views or reporting tables. Do not grant write, schema, admin or application credential privileges. Superset itself is an analytics layer, not a database firewall.
2. **Use production records through a reporting path.** Production data is approved for analytics. Prefer a read replica for a near-real-time first implementation; validate replication lag against an agreed freshness target. If the reporting workload or data residency needs call for a separate reporting store, synchronize through MariaDB replication or a reviewed binlog/CDC pipeline and publish only approved reporting views. Direct queries against the primary transactional database should be limited to an initial low-volume proof after measuring load.
3. **Build safe datasets.** Publish reporting views with explicit company/office keys and only required columns. Exclude document evidence paths, attachment metadata, secrets, unnecessary user-level fields and raw request justification. Aggregate sensitive data where individual records are not needed. Keep joins and status semantics reviewed by the GovStore domain owners.
4. **Enforce scope at the embed layer and restrict the source.** GovStore’s Laravel global scopes apply to application queries and do not protect independent Superset SQL. Publish curated views with explicit office/company keys and no unnecessary personal data. The Superset reporting identity may still be able to read every row in those views; Superset is therefore a trusted service boundary. Guest-token row-level security must enforce each embedded viewer's scope, while least-privilege source grants, locked-down Superset APIs and disabled SQL Lab reduce the impact of mistakes. If the threat model requires office isolation to survive a Superset compromise, the reporting data must be physically or credential-separated by scope; ordinary Superset RLS alone cannot provide that guarantee.
5. **Embed through GovStore only.** Add an authenticated GovStore endpoint that checks the appropriate declared `gov.can:<ability>` through `GovAccess`, then derives the effective office/company scope from the current `TenantContext` and verified memberships. It must not trust a dashboard filter or submitted office ID. The endpoint should allowlist dashboard/dataset IDs, issue short-lived guest tokens and bind row filters to the server-derived scope. Restrict the dashboard’s allowed embed origin. Test direct token/API access and attempts to alter filters, IDs, cached queries and dashboard parameters. Office/company abilities should grant only corresponding scopes; national dashboards require a specific national analytics ability.
6. **Keep administrator surfaces separate.** Only a small trusted group should administer Superset databases, datasets, SQL Lab, roles and row-level security. Analysts should receive dashboard access only, with SQL Lab and dataset editing disabled unless a specific role requires them.
7. **Protect authentication and transport.** Use the existing organizational identity provider (SSO/OAuth/LDAP if available), HTTPS, secure secrets management, a unique production `SUPERSET_SECRET_KEY`, least-privilege database credentials, and secure proxy/CSP settings. Disable example data and default admin credentials in real environments.

There is no separate Superset sign-in for ordinary viewers in this design: GovStore authenticates the user and serves the embedded dashboard using a short-lived guest token. Keycloak can later become the common identity provider for both products, while GovStore remains responsible for GovStore abilities and office/company scope. National leadership dashboards should default to aggregate measures; person-level or transaction-level drill-down requires a separately approved ability and data-minimization review.

## Deployment approach

The current repository includes a Snipe-IT Docker Compose stack with the app and MariaDB. It has no Superset service or Kubernetes deployment configuration. The target is an organization-operated Linux VM, with Superset independently deployable so its upgrades, resource use, credentials and failure modes do not affect the inventory application.

### Development and evaluation

- Use a pinned official Superset release in a separate Docker Compose project on a Linux host or Linux VM for evaluation. Superset documents Compose as the fastest evaluation/development setup and explicitly says its Compose constructs are not production-ready. Superset does not officially support Windows; the developer environment here is Windows, so use Linux/WSL2 or a Linux development host if a hands-on pilot is needed.
- Use a separate PostgreSQL metadata database for Superset, and Redis/Celery only if enabling caching, alerts or reports requires them. Disable sample data outside disposable demos.
- Build an immutable image from the official lean image with the needed MySQL/MariaDB driver; pin image and dependency versions. Do not use the `-dev` image as a production image.
- Connect only to sanitized sample or isolated development reporting data initially. Avoid exposing the developer workstation or production DB to the Superset container through a broad host-network rule.

### Production on the Linux VM

- Because the selected target is a Linux VM and no Kubernetes or managed container platform is identified, plan for a single-node Linux deployment. This creates a clear availability and capacity limit; record the VM as a single point of failure and define recovery expectations before launch.
- Use a pinned official Superset image and a maintained production configuration, not the upstream quickstart/development Compose setup. A hardened Compose project or individually managed containers may be used on the VM after operations review. Superset's documentation cautions that Compose is not production-ready out of the box, so the deployment must add the missing operational controls.
- Provide a production PostgreSQL metadata database with backups and restore exercises, Redis for server-side sessions/cache/broker needs, Superset web processes, and Celery workers/beat only if alerts, reports, or async queries are enabled. Prefer placing PostgreSQL/Redis and the reporting replica outside the Superset VM if infrastructure permits; otherwise isolate their containers, storage, credentials and backup schedule on the VM.
- Put Superset behind the organization's HTTPS reverse proxy; restrict inbound access so only GovStore and authorized administrators can reach it. Keep metadata and cache private. Configure secure cookies for embedding, short session/token lifetimes, CSP/allowed origins, rate/query limits and monitoring. Set worker count and query timeouts based on measured demand.
- Store signing keys and database credentials in a secret manager. Use distinct keys and credentials per environment. Pin an exact Superset image tag and driver versions; deploy upgrades through staging and back up the metadata database before migrations.
- Restrict network paths: browser users reach Superset through the HTTPS endpoint; Superset reaches only the reporting database and required internal services; the reporting database accepts only the Superset read-only identity and approved replication/refresh identities.
- Establish monitoring, audit-log review, backup retention, patch/upgrade ownership, alert/report email settings if used, and a documented disable/rollback path before enabling access to production data.

## Suggested delivery phases

| Phase | Work | Exit condition |
| --- | --- | --- |
| 0. Define the service | Confirm national versus office dashboard boundaries, Linux VM owner, freshness target, data residency, identity and metric ownership | ICT, GovStore owner and security/data owners approve scope and operational limits |
| 1. Proof of value | Run a pinned Superset release in isolated Linux Compose; connect to sanitized or masked data; prototype inventory and workflow dashboards | Measures reconcile and representative office/national users accept the dashboards |
| 2. Reporting foundation | Define metric semantics; create curated views and read-only identity; configure read replica or CDC if needed; benchmark data freshness and query load | Each office signs off its own metric meanings and source data; shared national metric definitions are approved and versioned centrally |
| 3. GovStore embedding and access | Map GovStore abilities to dashboard/dataset allowlists; implement server-side guest-token flow, row-level security and allowed origin; perform negative access tests | Users cannot access another office/company scope by altering filters, URLs, requests, tokens, SQL/API paths or cached queries; national users see approved national data |
| 4. Linux VM production deployment | Deploy the reviewed single-node topology with backups, secrets, HTTPS, monitoring, recovery procedure and pinned release; import versioned dashboard metadata | Restore, upgrade/rollback, access, performance and operational ownership checks are accepted |
| 5. Tiered rollout | Release to office, company and national leadership audiences in controlled cohorts; monitor freshness, access logs, query load and office feedback | Office owners sign off their measures; national data owner accepts cross-office comparability before full rollout |

## Decisions needed before implementation

- Which Linux VM team owns patching, Docker/container operations, backups, monitoring and recovery, and what availability target is expected for the single-node design?
- What freshness target is acceptable for dashboards (for example, replication lag in minutes), and should the reporting replica run on the same host or a separate database server?
- Which national body approves shared metric definitions and resolves differences between offices? Each office owns validation of its own source data and office-level measure meanings.
- Which GovStore ability names and dashboard audience groups should map to office, company and national views, including the national-leadership audience?
- When Keycloak is introduced, will it federate users into GovStore, Superset, or both? Until then, GovStore remains the sole viewer identity and authorization source for embedded dashboards.

## Principal risks and controls

| Risk | Control |
| --- | --- |
| Cross-office/company disclosure because Superset bypasses Laravel scopes | Curated source-side views/permissions, server-derived embed RLS, no ordinary-user standalone access, and negative tests across roles and scopes |
| Superset service compromise exposes all rows reachable by its reporting credential | Minimize reporting columns, restrict network and admin access, keep Superset patched, monitor access, and assess scope-partitioned reporting data if the risk is unacceptable |
| Analytics queries degrade transactional operations | Read replica/reporting store, query limits/timeouts, load monitoring, and measured refresh/query plans |
| Charts disagree with workflow or ledger semantics | Signed-off definitions, traceable SQL, reconciliation against source records and a visible “as of” timestamp |
| Exposing personal or sensitive request information | Data minimization, aggregation, column exclusion and narrow role assignment |
| Lost dashboards or credentials during upgrade/restore | External PostgreSQL metadata store, backups, restore exercises, secret rotation plan and pinned releases |
| Confusing a Superset chart with an authoritative operational action | Keep all writes and approvals in GovStore; make Superset datasets and connections read-only |

## Evidence checked

- Repository deployment: root `docker-compose.yml` defines the existing app and MariaDB stack; root `.env.example` selects MySQL by default.
- GovStore data model: Store Operations migrations define `gov_documents`, `gov_document_items`, `gov_inventory_movements`, receipt/issue tables and document references/attachments; Custom Requests defines service requests, items and events. These include tenant identifiers on key inventory documents and movements, but the request schema has evolving office-scope rules that must be rechecked before exposing it.
- Superset documentation: [installation methods](https://superset.apache.org/docs/installation/installation-methods/), [Docker builds](https://superset.apache.org/admin-docs/installation/docker-builds/), [architecture](https://superset.apache.org/admin-docs/6.1.0/installation/architecture/), [production security](https://superset.apache.org/admin-docs/security/securing_superset/), [embedding](https://superset.apache.org/user-docs/using-superset/embedding/), [database connections](https://superset.apache.org/docs/databases/) and [MySQL/MariaDB connectivity](https://superset.apache.org/user-docs/databases/supported/mysql/).

