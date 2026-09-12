# M14 Task 4D — Production Public Chat Handler

Status: COMPLETE on `feat/m14-frontend-chatbot-customizer` / PR #19.

## Scope

Task 4D adds the thin public execution adapter over the existing production M11 chat responder boundary. It does not create another retrieval, grounding, prompt, memory, citation, provider, embedding, or vector-store pipeline.

`ProductionPublicChatExecutor` accepts a validated `PublicChatRequest`, a trusted server-derived `ChatAccessContext`, and the persisted server-owned model identifier. It constructs one internal `ChatRequest` with repository-owned strict grounding policy, invokes `ChatResponder::respond()` exactly once, and projects the result through `PublicChatResponse` / `PublicChatCitation`.

`ChatOrchestrator` implements `ChatResponder`, making the existing production M11 orchestrator the concrete responder authority used by this adapter rather than requiring a parallel public chat graph.

The public projection exposes only answer text, conversation identifier, and public citation id/title/canonical URL. It does not expose provider usage, internal message identifiers, chunk identifiers, document identifiers, source identifiers, credentials, or runtime configuration.

## Strict TDD evidence

### Initial genuine RED

- Commit: `0d39a1f31099f635406f04623357adacf6752818`
- CI: `34696794418`
- Composer validation, PHPCS, and PHPStan passed.
- PHPUnit executed 789 tests / 3,294 assertions and failed exactly the intended Task 4D specification because `ChatResponder` was missing.
- `js-quality`, `package`, and `wordpress-smoke` were GREEN.

This is a genuine behavior-level RED.

### Intermediate checkpoints — NOT GREEN

The implementation was introduced incrementally and the history is preserved honestly:

- `fbeea1b7a04f9882eb124fb3d4a74fb3b003ca68` / CI `34696860256` — `ChatResponder` boundary added; validation/PHPCS/PHPStan passed but PHPUnit still failed, so **NOT GREEN**.
- `ce722b516ba98dbd3b7f6b2eb95185a49316d169` / CI `34696879559` — public citation projection added; PHPCS failed before PHPStan/PHPUnit, so **NOT GREEN**.
- `dee2b743cabc8af8ae2e15d16a0c97a409e75dce` / CI `34696898708` — public response projection added; PHPCS failed before PHPStan/PHPUnit, so **NOT GREEN**.
- `8554736ab8bca6b36b41e19a5eaf8ce633372b93` / CI `34696912571` — production public executor added; PHPCS failed before PHPStan/PHPUnit, so **NOT GREEN**.
- `7b8fcf865062493e0f4020aa354e65177e642349` / CI `34696969948` — citation documentation repair; PHP quality still stopped at PHPCS on `PublicChatResponse.php`, so **NOT GREEN**.

No failed checkpoint is relabeled as GREEN.

### Initial genuine GREEN

- Commit: `be996339327822b7d6da3851b79430f7f9d083b9`
- CI: `34697251223`
- `php-quality`: GREEN, including Composer validation, PHPCS, PHPStan, PHPUnit, and Composer audit.
- `js-quality`: GREEN.
- `package`: GREEN.
- `wordpress-smoke`: GREEN.

This exact implementation SHA verified the thin executor/projection behavior.

### Production-responder composition review finding

A follow-up architecture review found one Important composition gap: the new `ChatResponder` boundary existed, and `ChatOrchestrator` already exposed the matching `respond( ChatRequest, ChatAccessContext ): ChatResult` behavior, but the production orchestrator did not implement the boundary. Without that conformance, the public adapter could not be composed directly over the existing M11 production authority without another adapter/graph.

This finding was repaired under its own strict RED -> GREEN cycle.

#### Genuine RED

- Commit: `0b8ef6bb3c8b733e4b381159873341a1ca845aa8`
- CI: `34697493122`
- Composer validation, PHPCS, and PHPStan passed.
- PHPUnit executed 790 tests / 3,311 assertions and failed exactly one intended specification: `ChatOrchestrator must implement ChatResponder so public chat reuses the production M11 graph.`

#### Genuine GREEN

- Commit: `059ba3a9fa2a46c88ef2cd2ab35aac772498f8e8`
- CI: `34697588222`
- `php-quality`: GREEN, including PHPCS, PHPStan, PHPUnit, and Composer audit.
- `js-quality`: GREEN.
- `package`: GREEN.
- `wordpress-smoke`: GREEN.

The fix is deliberately minimal: `ChatOrchestrator` now implements `ChatResponder`; its production behavior is otherwise unchanged.

## Scoped review

Fallback correctness/security/performance/architecture review has **0 Critical / 0 Important unresolved** findings for this subunit after the responder composition repair.

### Correctness

- `ProductionPublicChatExecutor` invokes the existing `ChatResponder` exactly once.
- `ChatOrchestrator` is the concrete production `ChatResponder`, so public execution can reuse the exact existing M11 orchestration authority.
- The internal `ChatRequest` uses the validated public question/conversation identifier plus the trusted server-owned model identifier.
- Grounding remains server-owned and fixed to the repository policy used by this public adapter.
- The public result projection preserves answer/conversation/citation display data required by the widget.

### Security / privacy

- Public callers cannot supply provider/model authority through the executor constructor; the model value comes from trusted persisted runtime composition.
- No credentials, embedding/vector-store options, source/collection overrides, retrieval limits, grounding controls, output-token controls, provider usage, or internal lineage identifiers are exposed by the public response.
- Citation projection strips internal chunk/document/source lineage.

### Performance

The adapter performs bounded request mapping and response projection around one existing M11 execution. It introduces no duplicate retrieval or generation call.

### Architecture / duplication

The public handler depends directly on the production `ChatResponder` authority now implemented by `ChatOrchestrator`; it does not duplicate M11 orchestration. Task 4E is responsible only for trusted WordPress runtime composition, abuse-control ordering, and the public HTTP adapter.

Accessibility is not applicable to this backend-only subunit. Independent reviewer/subagent transport was not available, so no independent-review claim is made.

## Exact continuation

Continue immediately with Task 4E — WordPress REST adapter.

Add the smallest public route/runtime adapter under strict TDD. Parse only through `PublicChatRequest`, derive client scope server-side, apply `PublicChatAbuseGuard` before any expensive runtime/provider/retrieval execution, resolve trusted persisted runtime authority, compose the existing `ChatOrchestrator`/M10-M11 authorities exactly once, delegate once to `ProductionPublicChatExecutor`, and return stable non-sensitive errors.

Do not expose request-level credentials, provider/model selection, source/collection selection, embedding/vector-store configuration, grounding/output-token controls, or retrieval limits.
