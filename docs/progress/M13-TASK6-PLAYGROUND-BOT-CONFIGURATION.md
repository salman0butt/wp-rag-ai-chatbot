# M13 Task 6 — Playground persisted bot configuration resolver

Status: COMPLETE SUBUNIT — Task 6 remains IN PROGRESS.

This document records the bounded persisted-bot selection seam required before the protected Playground HTTP route can be safely exposed.

## Scope

`PlaygroundBotConfigurationResolver` accepts one explicit canonical persisted bot identifier, loads that exact bot through the existing `BotRepository`, rejects missing and disabled records, and returns the persisted `Bot` aggregate unchanged for downstream production composition.

The resolver does not select a fallback bot, invent provider/model defaults, accept credentials or provider options from the request, construct a second RAG pipeline, perform retrieval, or serialize the bot to an HTTP response.

The M12 bot aggregate remains the source of truth for the persisted `provider_id` and `model_id` used by later Playground composition.

## TDD evidence

### Convention-only checkpoints — NOT RED

- `3062408a061d3a62ce71a11acb1a13d1f405e528` / CI `34468544863`: the new test was blocked by PHPCS before PHPUnit. This is not RED evidence.
- `27a48d0edcd73d9813d3988218f15a3f0fa92ff9` / CI `34468635844`: the formatted test still stopped in PHPCS on missing parameter documentation. This is not RED evidence.

### Genuine RED

- `c9f810dcd6dd06faa6ec926a095dc18eb1a621c3` / CI `34468732722`.
- PHPCS and PHP static analysis passed.
- PHPUnit reached the intended behavior boundary: 704 tests / 2,991 assertions / exactly 3 errors.
- All three errors were `Class "WpRagAiChatbot\\Admin\\Rest\\PlaygroundBotConfigurationResolver" not found`, covering enabled selection, missing configuration, and disabled configuration.

### GREEN

- Production implementation: `c68957b3cc25efde62f996b881f10ab7253565c6`.
- Exact-head CI: `34468976306`.
- Permanent jobs GREEN: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.
- WordPress smoke completed environment startup, activation, database, provider, knowledge, file-ingestion, WooCommerce-knowledge, and environment cleanup successfully.

## Correctness / security / performance review

Scoped coordinator review found 0 unresolved Critical and 0 unresolved Important findings for this subunit.

- Correctness: lookup is by the exact canonical `BotId`; missing records fail closed; disabled bots fail closed; enabled bots preserve the exact persisted `provider_id` and `model_id` rather than accepting request-supplied runtime substitutions.
- Security: the resolver accepts only a bot identifier and exposes no credential values, arbitrary provider options, provider error bodies, query text, or session data. It has no fallback behavior that could silently execute the wrong bot.
- Performance: one bounded repository lookup plus constant-time status checks; no retrieval/network/provider work is introduced.
- Accessibility: not applicable to this server-side internal composition subunit.

This scoped review is not the mandatory independent final Task 6 review. That review remains required after the complete production composition and REST route are implemented.

## Remaining Task 6 composition gap

This subunit resolves persisted bot/provider/model selection only. It does **not** claim that the full persisted retrieval configuration has been resolved.

`RetrievalConfig` currently defines immutable execution bounds, while the M13 design also requires the Playground request to use explicit existing retrieval configuration identifiers. The next implementation unit must recover the existing persisted retrieval/collection/configuration representation and compose it into the existing M10/M11 production graph without creating a Playground-specific retrieval engine.

## Exact continuation point

1. Recover the concrete persisted retrieval/collection/configuration representation and the existing production factories for embedding/vector search, lexical retrieval, grounding, prompt, memory, citations, and generation.
2. Under fresh strict TDD, implement the smallest typed request-local composition/resolver seam that combines the explicitly selected enabled `Bot` with the explicitly selected persisted retrieval configuration.
3. Do not accept arbitrary credentials, provider options, model overrides, or retrieval bounds from the Playground request.
4. Only after that composition seam is GREEN, restore the protected `POST /admin/debug/playground` route regression and wire the real production executor.
5. Add REST/integration/WordPress smoke coverage, perform the genuinely independent Task 6 correctness/security/performance review, resolve every Critical/Important finding under RED → GREEN, and require all four permanent jobs GREEN on the exact final Task 6 SHA before marking Task 6 COMPLETE.
