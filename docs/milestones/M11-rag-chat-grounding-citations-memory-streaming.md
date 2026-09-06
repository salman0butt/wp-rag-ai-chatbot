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
2. **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED** — ownership-scoped conversation persistence, V007–V009 migrations, prepared-SQL repositories, cross-owner denial, persistence bounds, and real WordPress lifecycle verification.
3. **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED** — deterministic bounded memory assembly with owner-scoped history, newest-message retention, hard 12-message / 24 KiB ceilings, chronological output, and one bounded versioned summary.
4. PENDING — citation registry and validator.
5. PENDING — prompt/context builder with evidence isolation.
6. PENDING — grounding policy and deterministic strict no-answer.
7. PENDING — non-streaming ChatOrchestrator.
8. PENDING — normalized streaming and cancellation.
9. PENDING — persistence/analytics hooks plus end-to-end acceptance/security/performance closeout.

## Task 1 Delivered Behavior
- Immutable normalized `ChatRequest` with question/model/conversation validation.
- Current question hard ceiling: 16 KiB.
- Caller-controlled model/conversation identifier ceiling: 255 bytes.
- Requested output token range: 1..4096.
- `GroundingMode`: `STRICT` and `ASSISTED`.
- Stable client-safe `ChatFailureReason` categories.
- Normalized `ChatResult` without raw provider/database diagnostics.
- Transport-owned identity/access values remain outside the request contract.

## Task 2 Delivered Behavior
- Additive V007 conversations, V008 messages, and V009 message-citations migrations; global schema version 9.
- Owner-scoped conversation/message persistence contracts.
- `WpdbConversationRepository` uses prepared owner-scoped lookup with `LIMIT 1` and bounded identifiers.
- `WpdbMessageRepository` uses one prepared `INSERT ... SELECT` guarded by conversation ID + owner scope, with 191-byte identifier, 32-byte role, and 65,536-byte content ceilings.
- Cross-owner reads/writes fail closed without existence leakage.
- SQL-looking identifiers/content remain bound values.
- Real WordPress smoke covers clean install, V1→V9 upgrade, idempotency, indexes, uninstall retention/deletion, clean reinstall, owner-scoped create/find/append, malicious-value round trip, and unchanged message count after denied append.

## Task 3 Delivered Behavior
- `ConversationHistory` requires both `conversation_id` and trusted `owner_scope` for recent messages and optional summary access.
- `ConversationMemory` carries chronological recent messages plus at most one optional versioned summary.
- `MemoryAssembler` requests no more than 12 messages, retains the newest contiguous window, drops older messages first to remain within a 24 KiB total text budget, and preserves chronological output order.
- Summary inclusion requires a positive version, non-empty trimmed text, and room within the same 24 KiB summary + message budget.

## TDD Evidence
### Task 1
Primary behavioral RED:
- `2b6b708d5f8e11249630f2417c67cfb9ff416ebc`
- CI `34002442391` — behavior suite failed because planned M11 request contracts did not yet exist.

Review regression RED:
- `65e93ba3d55295cf950c8687ca07ba751ec40fa0`
- CI `34003104992` — proved caller-controlled identifiers lacked a hard bound.
- fix `c624a90669692876bec1546e1582a10db8acc335`.

Verified implementation head:
- `993ed2705d4dc3665c238c01038deb0a9669b9f9`
- CI `34003238289` — SUCCESS.

### Task 2
Schema/contract behavioral RED:
- `b7bc8f57c1b6197d63a6412e1fed2dc51e976222`
- CI `34007381906` — 563-test suite reached behavior and failed exactly the new missing persistence-contract assertions.

SQL-repository behavioral RED:
- initial `c1f81cbb3f75627620f5f1afb235fca602f28f51` stopped at PHPCS and is not counted as RED.
- `9102bce1dfcc6df50f9e117c3f693ca41758a1dc`
- CI `34010133168` — PHPStan passed, then PHPUnit reached 570 tests / 2,263 assertions with seven expected missing repository-class errors.

Production sequence:
- `9e3996605a07925e8b18e16bc02aacc2c93b2ba6` — V007–V009/schema/contracts.
- `27a7ff05a95b278c67b4277aeb8c0960296f5e7d` — owner-scoped conversation repository.
- `5839cbed2d9ff21b8c34bbec7e8772179fea2423` — atomic owner-scoped message repository.
- standards/static-analysis corrections through `5970ad7d3b5fde1e629047493415564fe0a2abda`.
- `0e947f78802266b6d5c455a15077f6af3cfe15fb` / `34182b7144cc99417bb1cd2df6213cfaa146909b` — real WordPress persistence/lifecycle verification.

Task 2 integration CI:
- `34010412433` on `34182b7144cc99417bb1cd2df6213cfaa146909b` — SUCCESS across php-quality, js-quality, package, and wordpress-smoke.
- reviewed head `b20798c14ec6169b8ffddfed2a8e03199c1fd822` passed CI `34010581646`.

### Task 3
Initial test-only commit:
- `68d05fa4bc87c7fdfcc7d311891686e810c71d80`.
- CI `34020220657` stopped in PHPCS before behavior and is not counted as behavioral RED.

Valid behavioral RED:
- `2e063c2fd59334d454d7b17d7399a8f796d52cab`.
- CI `34020263699` — static analysis passed; PHPUnit ran 575 tests / 2,301 assertions and failed exactly five new Task 3 assertions because `ConversationHistory` / memory contracts did not yet exist.

Production sequence:
- `5d640763de1d6d4c48e9d2301bb41f9591e196dc` — bounded memory implementation.
- `153c2e62021950ae06073341cf0bd6f144a9458c`, `699370c7349ed5f6ec909d8eb61087ace2d5cbe3`, `bddd28a6bf8ddb94d46c16641ba86bc22501746b` — standards/type alignment.
- `ac4b99aa6921db0f0fcc6dd5571497a352237fa3` — final static-analysis correction without intended behavior change.

Verified implementation head:
- `ac4b99aa6921db0f0fcc6dd5571497a352237fa3`.
- CI `34020490179` — SUCCESS; PHPStan 0 errors; PHPUnit 575/575 tests / 2,320 assertions; Composer audit clean; JS/package/WordPress smoke GREEN.
- artifact `9985321552`, 842,459 bytes, digest `sha256:533e633d00a5a14d553aef7a7add97403ffc8b76766a47ff154401910f84824d`.
- documentation head `89a9f42efb685cba2bab17794b5ffba9a1034421` passed exact-head CI `34020724462`.

## Integration Test Evidence
Task 2 persistence is exercised against real WordPress/MySQL in addition to unit contracts. Full indexed-fixture -> retrieval -> answer/citation integration remains Task 9. Task 3 is deterministic application logic and is covered by focused unit tests plus the full repository CI/smoke suite.

## E2E / Visual Verification
Backend streaming smoke remains later M11 work; visual widget remains M14.

## Security Review
Task 1 reviewed SHA `993ed2705d4dc3665c238c01038deb0a9669b9f9`: **0 Critical / 0 Important**. PR review `5123774888`.

Task 2 reviewed SHA `b20798c14ec6169b8ffddfed2a8e03199c1fd822`: **0 Critical / 0 Important**. PR review `5124482278`.

Task 3 reviewed implementation SHA `ac4b99aa6921db0f0fcc6dd5571497a352237fa3`: **0 Critical / 0 Important**. Review covered owner-scope propagation, transcript privacy, newest-message retention, chronological ordering, 12-message / 24 KiB ceilings, summary budgeting, type safety, bounded work, and test adequacy. PR review `5124784589`.

No unresolved blocking inline review threads exist.

## Performance Review where relevant
- Task 1 bounds question bytes, identifiers, and generation output tokens before later dispatch.
- Task 2 uses bounded indexed owner-scoped reads and one atomic authorization/write SQL statement.
- Task 3 requests at most 12 recent messages and performs bounded in-memory assembly against a 24 KiB text ceiling; no external calls are introduced.

## Code Review Findings
- Task 1 had one Important identifier-bound issue; fixed by `c624a90669692876bec1546e1582a10db8acc335`.
- Task 2 review: 0 Critical / 0 Important; no regression fix required.
- Task 3 review: 0 Critical / 0 Important; no regression fix required.

## Fresh Verification Results
- Task 1 reviewed head CI `34003238289` — SUCCESS.
- Task 2 real integration CI `34010412433` — SUCCESS; reviewed-head CI `34010581646` — SUCCESS.
- Task 3 implementation CI `34020490179` — SUCCESS; prior documentation-head CI `34020724462` — SUCCESS.
- A fresh exact-head CI run is required for the Task 3 closeout documentation commit before Task 4 production work begins.

## Files Changed Through Task 3
Task 1:
- `src/Chat/ChatRequest.php`
- `src/Chat/ChatResult.php`
- `src/Chat/ChatFailureReason.php`
- `src/RAG/GroundingMode.php`
- `tests/Unit/Chat/ChatRequestContractTest.php`
- `tests/Unit/Chat/ChatResultContractTest.php`

Task 2:
- V007–V009 migration/schema/table-name files.
- conversation/message domain + repository contracts.
- `src/Database/Repository/WpdbConversationRepository.php`.
- `src/Database/Repository/WpdbMessageRepository.php`.
- unit/WordPress persistence fixtures and lifecycle smoke.

Task 3:
- `src/Memory/ConversationHistory.php`.
- `src/Memory/ConversationMemory.php`.
- `src/Memory/MemoryAssembler.php`.
- `tests/Unit/Memory/MemoryAssemblerTest.php`.
- `docs/progress/M11-TASK3-PROGRESS.md`.
- this milestone ledger.

## Known Limitations
Citation validation, prompt construction, deterministic grounding policy, generation orchestration, streaming, persistence/analytics composition, and milestone-wide acceptance remain Tasks 4–9. General conversation-listing/UI semantics remain outside this milestone scope.

## Documentation Updated
The M11 ledger and Task 3 progress record now match actual Git/code/CI/review evidence through Task 3 without overstating later work.

## Completion Checklist
M11 remains incomplete until Tasks 4–9 and all milestone-wide security/performance/integration/merge gates pass.

## Exact Next Unfinished Action
After exact-head CI passes on the Task 3 closeout documentation head, begin **Task 4 — citation registry and validator** with a test-only behavioral RED proving deterministic `C1..Cn` IDs in final context order, unknown citation rejection, duplicate/ill-formed marker handling, model-authored URL non-authority, canonical lineage preservation, and the registry hard limit. Do not add citation production classes until that RED reaches the behavior suite for the expected missing-contract reason.

## Next Milestone
M12 — Admin Onboarding/Bot Management.
