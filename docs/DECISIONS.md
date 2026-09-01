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

The repository initially contained no commits, so a single administrative root commit on main was necessary before an isolated feature branch could exist. Root commit `a28f96047142a78064e966a66e0ea9ebc4af1996` contains README initialization only. All further work is on `feat/m00-master-architecture` until integration is explicitly authorized.

## ADR-016 — Sandbox prevents native git worktree

Status: RECORDED ENVIRONMENT LIMITATION

The execution container could not resolve github.com, so `git clone`/native local worktree creation was unavailable in this chat runtime. The connected GitHub integration is used to maintain equivalent branch isolation. A real local/Codex runtime should use the standard Superpowers git-worktree flow once repository network access is available.
