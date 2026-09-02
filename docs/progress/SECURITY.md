# Security Ledger

Security is cross-cutting; M22 is the comprehensive hardening audit, not the first security milestone.

## Threat boundaries to test throughout

- Anonymous visitor -> public REST/chat.
- Authenticated visitor/customer -> conversation/order/action APIs.
- Administrator -> admin REST/configuration.
- External URL/sitemap -> crawler/extractor.
- Uploaded document -> parser/indexer.
- Retrieved content -> model context.
- LLM tool request -> action execution.
- WordPress -> OpenAI/OpenRouter/WP AI Client.
- WordPress -> external vector store.
- External website -> cross-site embed.
- MCP client -> WordPress Ability/action.

## Mandatory controls

- Capability and ownership checks.
- REST permission callbacks.
- Nonces where appropriate for cookie-authenticated mutations.
- Strict validation, sanitization, output escaping.
- Prepared SQL.
- SSRF-safe URL fetching and redirect policy.
- File MIME/size/resource controls.
- Secret redaction and no credential exposure to clients/logs.
- Rate/cost/spam controls.
- Prompt/retrieval/tool-injection defenses.
- IDOR prevention for conversations/leads/orders.
- WooCommerce order/customer authorization.
- Explicit CORS/origin policy for external embeds.
- Action schema validation, risk classification, authorization, timeout, audit.

## M01 foundation security evidence

- Runtime surface intentionally introduced no REST endpoints, external HTTP calls, credentials, uploads, or user-input processing.
- Permanent GitHub Actions workflow uses read-only repository contents permission.
- Composer audit reported no known advisories on the verified foundation candidate.
- npm development tooling has tracked non-critical transitive advisories; Node dependencies are development/build only and are excluded from the production ZIP.
- Production packaging is allow-list based and rejects development/private paths.

## M02 database security evidence

Verified runtime candidate: `4db24d95db0d572f28273734714c74a47ac8bb2e`, CI run `33603435032`.

### SQL/value boundaries
- Source/document value-bearing reads use prepared `%s`/`%d` placeholders; plugin-owned table identifiers use `%i` from trusted `TableNames` only.
- Inserts, updates, and deletes use the narrow `$wpdb` adapter write APIs with explicit formats.
- Real WordPress tests store and retrieve SQL-injection-shaped literals including `source-' OR 1=1 --` and `" OR 1=1 --`; row counts and source scoping remain unchanged.
- Apostrophes, `<script>literal test data</script>`, and Unicode `مرحبا` are treated as stored data, not executable SQL or output policy.
- Repository pagination is bounded to at most 100 records per page, limiting accidental/untrusted unbounded list requests at the persistence layer.

### Migration boundary
- Migrations run under a MySQL named advisory lock.
- Lock contention does not execute DDL.
- The schema version is refreshed after successful lock acquisition, closing a race where a stale process could replay migrations completed by another process.
- Version persistence occurs after successful migration application; failed migrations retain the last successful version and release the lock in `finally`.
- The current-schema `plugins_loaded` path exits after the lightweight version read rather than acquiring a lock or executing DDL.

### Uninstall/destructive boundary
- Data retention is the default; destructive uninstall requires the explicit persisted true/one setting.
- `uninstall.php` is guarded by `WP_UNINSTALL_PLUGIN` and loads only the packaged Composer runtime.
- Plugin-owned tables are dropped in safe dependency order and only through prepared `%i` identifiers.
- A failed DROP throws `DatabaseException`; schema/policy options are not removed, preserving a retryable cleanup state.
- Version/delete-policy options are removed only after all plugin-owned table drops succeed.
- Real WordPress integration verifies default retention, opt-in deletion, option removal, and a clean reinstall.

### Scope/secrets
- M02 introduces no provider credentials, API keys, external model/vector calls, public REST endpoints, or new client-visible secrets.
- Package validation requires the uninstall runtime while continuing to exclude tests, docs, `.github`, environment files, Node modules, and dependency manifests/locks.

Artifact digest: `sha256:f77b32bf377b4f6fbb65cf1721a87b5e0408041ad5444816076227ab931aeab3`.

## Open security design work

Exact rate-limit storage/algorithm, encrypted-at-rest plugin credential strategy for pre-WP7 direct providers, URL crawler allow/deny policy, cross-site embed token scheme, anonymous session ownership mechanism, and later data-retention/privacy controls remain owned by their respective milestones. M02 does not weaken those future trust boundaries.
