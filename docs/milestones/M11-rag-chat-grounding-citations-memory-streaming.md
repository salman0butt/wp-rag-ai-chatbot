# M11 — RAG Chat Orchestration, Grounding, Citations, Memory & Streaming

Status: IN PROGRESS

## Goal
Deliver the backend RAG conversation path with deterministic grounding, bounded memory, validated citations, and normalized streaming.

## Dependencies
M03, M10, M09 where async support is needed.

## In Scope
Chat request pipeline; ownership/session foundation; rate/cost checks; memory assembly; retrieval; grounding modes; prompt/context builder; provider generation; citation IDs/validation; strict no-answer; streaming events; persistence hooks; feedback/analytics hooks.

## Out of Scope
Polished frontend widget/admin UIs; full action framework M19.

## Architecture
Server-side orchestration treats retrieved content as untrusted data and keeps authorization outside the model. Provider streaming normalizes into plugin events.

Design/spec: `docs/superpowers/specs/2026-09-06-m11-rag-chat-orchestration-design.md` — **AUTO-APPROVED — SCHEDULED MODE**.

Implementation plan: `docs/superpowers/plans/2026-09-06-m11-rag-chat-orchestration.md` — **AUTO-APPROVED — SCHEDULED MODE**.

## Acceptance Criteria
Strict no-answer deterministic tests pass; citations only reference selected sources; malicious retrieved instructions cannot elevate policy; memory is bounded; stream events order/error paths tested; provider secrets absent from client responses.

## Tasks
1. **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED** — chat request, grounding mode, failure/result contracts, and hard request bounds.
2. PENDING — ownership-scoped conversation persistence.
3. PENDING — deterministic bounded memory assembly.
4. PENDING — citation registry and validator.
5. PENDING — prompt/context builder with evidence isolation.
6. PENDING — grounding policy and deterministic strict no-answer.
7. PENDING — non-streaming ChatOrchestrator.
8. PENDING — normalized streaming and cancellation.
9. PENDING — persistence/analytics hooks plus end-to-end acceptance/security/performance closeout.

## Task 1 Delivered Behavior
- Immutable `ChatRequest` with trimmed non-empty question/model identifiers.
- Current question hard ceiling: 16 KiB bytes.
- Caller-controlled model/conversation identifiers hard ceiling: 255 bytes.
- Requested output token range: 1..4096.
- `GroundingMode`: `STRICT` and `ASSISTED`.
- Stable client-safe `ChatFailureReason` values.
- Normalized `ChatResult` containing application-level answer, usage, citations, and optional persisted identifiers without raw provider/database diagnostics.
- Transport-owned identity/access values remain outside the request contract.

## TDD Evidence
Task 1 primary behavioral RED:
- test-only SHA `2b6b708d5f8e11249630f2417c67cfb9ff416ebc`
- CI `34002442391` — FAILURE after reaching the behavior suite because the planned M11 request contracts did not yet exist.

Task 1 review regression RED:
- test-only SHA `65e93ba3d55295cf950c8687ca07ba751ec40fa0`
- CI `34003104992` — FAILURE proving caller-controlled model/conversation identifiers were not yet hard-bounded.
- fix SHA `c624a90669692876bec1546e1582a10db8acc335` — adds the 255-byte identifier ceilings.

Task 1 final verified implementation head before this ledger closeout:
- SHA `993ed2705d4dc3665c238c01038deb0a9669b9f9`
- CI `34003238289` — SUCCESS across the repository's permanent CI jobs.

## Integration Test Evidence
Task 1 is value-object/domain-contract scope; full indexed-fixture -> retrieval -> answer/citation integration remains Task 9.

## E2E / Visual Verification
Backend streaming smoke remains later M11 work; visual widget remains M14.

## Security Review
Task 1 scoped review covered caller-controlled authorization leakage, question/output/identifier bounds, stable client-safe failure categories, normalization, and raw diagnostic exposure.

Result at reviewed SHA `993ed2705d4dc3665c238c01038deb0a9669b9f9`: **0 Critical / 0 Important**. PR review record: `5123774888`.

## Performance Review where relevant
Task 1 enforces construction-time hard ceilings for question bytes, caller-controlled identifiers, and requested generation output tokens before later orchestration/provider dispatch.

## Code Review Findings
Task 1 review history includes one Important issue already resolved before closeout: caller-controlled model/conversation identifiers lacked an explicit hard byte bound.

Current unresolved Task 1 findings: **0 Critical / 0 Important**.

## Fixes
- `c624a90669692876bec1546e1582a10db8acc335` — bound M11 request identifiers to 255 bytes.
- `993ed2705d4dc3665c238c01038deb0a9669b9f9` — restore standards-required request property documentation.

## Fresh Verification Results
Exact reviewed Task 1 head `993ed2705d4dc3665c238c01038deb0a9669b9f9` passed CI `34003238289`.

A fresh CI run on the documentation closeout SHA is required before the next task may claim exact-head GREEN.

## Commits
Task 1 implementation/history is retained on PR #16 (`feat/m11-rag-chat-orchestration`).

## Files Changed
- `src/Chat/ChatRequest.php`
- `src/Chat/ChatResult.php`
- `src/Chat/ChatFailureReason.php`
- `src/RAG/GroundingMode.php`
- `tests/Unit/Chat/ChatRequestContractTest.php`
- `tests/Unit/Chat/ChatResultContractTest.php`
- M11 design/plan and this milestone ledger.

## Known Limitations
Task 1 intentionally contains no persistence, memory, retrieval orchestration, citation validation, prompt construction, generation orchestration, or streaming implementation. Those remain Tasks 2–9.

## Documentation Updated
This ledger now reflects the actual M11 design/plan and Task 1 Git/CI/review evidence rather than the stale pre-work `NOT STARTED` state.

## Completion Checklist
M11 remains incomplete until Tasks 2–9 and all milestone-wide security/performance/integration/merge gates pass.

## Exact Next Unfinished Action
Begin Task 2 — ownership-scoped conversation persistence — with a test-only RED covering additive/idempotent V007–V009 migrations plus cross-owner read/write denial before production migration/repository classes are added.

## Next Milestone
M12 — Admin Onboarding/Bot Management.
