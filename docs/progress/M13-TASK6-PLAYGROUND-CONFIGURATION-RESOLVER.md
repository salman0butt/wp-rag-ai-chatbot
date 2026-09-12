# M13 Task 6 — Playground Configuration Resolver

Status: **COMPLETE SUBUNIT — production dependency composition remains next**

## Scope

This subunit adds the smallest request-local resolver that turns explicit persisted Playground selectors into the existing closed `PlaygroundConfiguration` aggregate.

`PlaygroundConfigurationResolver` delegates only to:

- `PlaygroundBotConfigurationResolver` for the explicit enabled persisted bot;
- `PlaygroundRetrievalConfigurationResolver` for the explicit persisted knowledge source and vector collection.

It introduces no fallback bot, provider/model override, credentials, request-level retrieval limits, retrieval/scoring/reranking, generation call, or Playground-specific RAG path.

## TDD evidence

- Test-only preparation `06703d49d771d00f0ccbe31e9900704c74c98c10` / CI `34485206226` stopped at four PHPCS assignment-alignment warnings. This is **NOT RED** because static analysis and PHPUnit were not reached.
- Genuine RED `7dfdbdee7c8e60551dd5f3651f2b106a2a1e79e5` / CI `34485370927` passed PHP conventions and PHPStan, then PHPUnit ran **709 tests / 3,011 assertions / exactly 1 error**: `PlaygroundConfigurationResolver` did not exist.
- GREEN production `fa9e1a64b4825f01f45a57bf53702663a5a23a8c` / CI `34485530222` passed all four permanent jobs: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.

## Result

`PlaygroundConfigurationResolver::resolve()` accepts exactly:

- persisted bot identifier;
- persisted source identifier;
- persisted vector collection identifier.

It returns one `PlaygroundConfiguration` containing the exact objects returned by the two existing persisted resolvers. Validation and fail-closed lookup behavior remain owned by those resolvers.

## Review

Correctness: the resolver is pure composition over the two already-verified persisted selection boundaries. It does not alter or substitute the resolved bot/source/collection.

Security/privacy: only identifiers cross this seam. Credentials, provider settings, arbitrary runtime options, raw provider payloads, and question text do not. Missing/disabled/malformed selections continue to fail closed through the existing resolvers.

Performance: one bot repository lookup, one knowledge-source lookup, and one vector-collection existence lookup; the resolver itself performs O(1) composition and no provider/retrieval work.

Accessibility: not applicable to this server-side composition seam.

Scoped review result: **0 Critical / 0 Important unresolved**. This does not replace the mandatory genuinely independent final Task 6 correctness/security/performance review after the full production composition and protected REST route exist.

## Exact next unit

Under a fresh strict-TDD cycle, implement the smallest request-local production dependency-composition boundary that consumes this resolved `PlaygroundConfiguration` and constructs the existing production M10/M11 graph: generation provider, embedding/vector and lexical retrieval, grounding, prompt, memory, citations, one `PlaygroundRetrievalCapture`, one `ChatOrchestrator`, and one `ProductionPlaygroundExecutor`.

The composition must use the persisted bot/provider/model and explicit persisted source/collection selection, execute retrieval through the existing production path exactly once, and must not accept arbitrary request-level credentials, provider/model overrides, retrieval limits, or create a second Playground-specific retrieval stack.

Only after that production composition is GREEN should the protected `POST /admin/debug/playground` regression be restored and implemented.
