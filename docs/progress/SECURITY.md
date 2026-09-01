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

- Runtime surface is intentionally minimal: no REST endpoints, database writes, external HTTP calls, credentials, file uploads, user input, or frontend/admin UI were introduced.
- Plugin bootstrap only registers activation/deactivation hooks and a `plugins_loaded` signal.
- Permanent GitHub Actions workflow declares `contents: read`; temporary write-enabled lockfile-generation workflows were removed after reproducible lockfiles were committed.
- Composer audit on the verified candidate reported no security vulnerability advisories.
- npm development tooling reported 32 transitive advisories (22 moderate, 10 high) and zero critical advisories. The M01 CI blocks critical npm findings. These packages are development/build dependencies and are excluded from the production plugin ZIP, but they remain a CI/dev supply-chain concern tracked as KI-003/TD-001.
- Production packaging is allow-list based and asserts required runtime files while rejecting tests, docs, `.github`, environment files, Node modules, and dependency manifests/locks.
- The package uses a production-only Composer install and includes `vendor/autoload.php`; no development Composer packages are distributed.

## Open security design work

Exact rate-limit storage/algorithm, encrypted-at-rest plugin credential strategy for pre-WP7 direct providers, URL crawler allow/deny policy, cross-site embed token scheme, and anonymous session ownership mechanism are implementation decisions to resolve in their respective milestone plans without weakening the master trust model.
