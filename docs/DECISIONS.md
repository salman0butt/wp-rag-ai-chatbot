# Architectural Decisions

## ADR-001 — PHP-first modular monolith

Status: APPROVED

The server runtime is WordPress/PHP. React/TypeScript may compile admin/widget assets. No mandatory Node.js/Python/LangChain/LangGraph backend.

## ADR-002 — Proposed compatibility baseline

Status: APPROVED DIRECTION; VERIFY IN M01/M24

Target WordPress 6.9+ and PHP 8.2+. WordPress 7.0 enhances integrations but is not required solely for provider communication.

## ADR-003 — Provider abstraction

Status: APPROVED

OpenAI Direct, OpenRouter Direct, and WordPress AI Client adapters sit behind application contracts. Model IDs are configuration/discovery data, not hard-coded throughout the application.

## ADR-004 — Server-side feature REST boundary

Status: APPROVED

Frontend/admin clients call feature-specific plugin REST endpoints. They never receive AI credentials or arbitrary provider prompt access. This follows WordPress 7.0 AI Client guidance for distributed plugins.

## ADR-005 — Hybrid retrieval is mandatory

Status: APPROVED

Production retrieval combines semantic vector search with lexical/exact retrieval before optional reranking. Pure cosine-similarity RAG is insufficient for exact identifiers and commerce use cases.

## ADR-006 — Embedding compatibility invariant

Status: APPROVED

Provider/model/dimensions and relevant vector-normalization/distance configuration define index compatibility. Incompatible embeddings cannot silently coexist; incompatible changes trigger controlled reindexing.

## ADR-007 — Local vector store has explicit scale limits

Status: APPROVED

A WordPress-local vector implementation provides simple setup for modest installations but must not be presented as equivalent to dedicated vector engines at large scale.

## ADR-008 — Database-backed background queue

Status: APPROVED

Long work uses a WordPress database queue with leases, retries/backoff, idempotency, progress, recovery, WP-Cron, and server-cron/WP-CLI options. Redis/RabbitMQ/Kafka are not mandatory dependencies.

## ADR-009 — Retrieved content is untrusted

Status: APPROVED

Retrieved documents/webpages/files are data, never system instructions, tool permissions, or authorization policy. Prompt-injection/retrieval-poisoning defenses are architectural requirements.

## ADR-010 — Strict RAG no-answer is deterministic

Status: APPROVED

Strict Knowledge Only mode has an application-level insufficient-evidence decision and controlled no-answer response rather than relying only on model obedience.

## ADR-011 — Dynamic WooCommerce state is tool-fetched

Status: APPROVED

Current price, stock, cart/order/discount and similar transactional state is fetched from WooCommerce at execution time rather than trusted from embeddings.

## ADR-012 — Actions are application-authorized

Status: APPROVED

The LLM may request an action, but PHP code validates input, identity, permissions, risk policy, and execution. Arbitrary WordPress/PHP/SQL execution is forbidden.

## ADR-013 — Abilities/MCP exposure is explicit

Status: APPROVED

Application services may be exposed through WordPress Abilities where useful. MCP exposure is opt-in and permission-aware; destructive abilities are never automatically public.

## ADR-014 — Evaluations/debugging are product features

Status: APPROVED

RAG traces and saved regression evaluation suites are not developer-only afterthoughts; they are first-class admin capabilities.

## ADR-015 — Empty repository bootstrap exception

Status: APPROVED

The repository initially contained no commits, so a single administrative root commit on main was necessary before an isolated feature branch could exist. Root commit `a28f96047142a78064e966a66e0ea9ebc4af1996` contains README initialization only. All further work is isolated from main until integration is explicitly authorized.

## ADR-016 — Sandbox prevents native git worktree

Status: RECORDED ENVIRONMENT LIMITATION

The execution container cannot resolve external GitHub/package hosts, so native clone/worktree creation is unavailable in this chat runtime. Connected GitHub branches provide repository isolation. A real local/Codex runtime should use the standard Superpowers git-worktree flow once repository network access is available.

## ADR-017 — Inline executing-plans fallback

Status: RECORDED PROCESS RULING

Superpowers subagent-driven-development requires a real subagent dispatch interface. This chat runtime does not expose one, so milestone execution uses the Superpowers executing-plans workflow inline. This does not waive TDD, review, verification, documentation, or branch-isolation requirements.

## ADR-018 — GitHub Actions is the authoritative dependency-backed runner in this chat runtime

Status: RECORDED PROCESS RULING

The container has PHP 8.4 and Node 22 but no Composer and cannot resolve Packagist/npm registry hosts. To preserve genuine RED→GREEN evidence, feature branches may introduce test/CI configuration before corresponding production behavior and use GitHub Actions as the authoritative dependency-backed test environment. Test-only commits must be observed failing for the expected missing behavior before corresponding production code is committed. This is an execution-environment adaptation, not a product architecture change.

## ADR-019 — Integer schema version option

Status: APPROVED IMPLEMENTATION RULING

`wp_rag_ai_db_version` stores the last successfully applied integer schema version. M02 targets version 2. A migration advances the version only after that migration completes successfully.

## ADR-020 — Incremental M02 source/document migrations

Status: APPROVED IMPLEMENTATION RULING

V001 creates the per-site knowledge sources table and V002 creates the per-site documents table. M02 intentionally does not pre-create chunks, vectors, jobs, conversations, analytics, or other later-milestone tables.

## ADR-021 — Portable per-site database schema

Status: APPROVED IMPLEMENTATION RULING

M02 tables use `$wpdb->prefix`, JSON-encoded `longtext`, application-managed relationships without foreign keys, and UTC timestamps. Network-wide multisite activation fan-out is deferred to the compatibility audit rather than forcing network-global tables.

## ADR-022 — Named migration advisory lock

Status: APPROVED IMPLEMENTATION RULING

Migration concurrency uses a deterministic MySQL/MariaDB `GET_LOCK`/`RELEASE_LOCK` identity derived from database name plus site prefix. Lock contention returns a non-error `LOCKED` outcome; migration failures remain errors and never advance the failed schema version.

## ADR-023 — Retain-by-default uninstall

Status: APPROVED IMPLEMENTATION RULING

Uninstall preserves plugin data unless `wp_rag_ai_delete_data_on_uninstall` is exactly boolean `true`. Destructive cleanup is confined to the guarded WordPress uninstall path.
