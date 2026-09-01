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

## Open security design work

Exact rate-limit storage/algorithm, encrypted-at-rest plugin credential strategy for pre-WP7 direct providers, URL crawler allow/deny policy, cross-site embed token scheme, and anonymous session ownership mechanism are implementation decisions to resolve in their respective milestone plans without weakening the master trust model.
