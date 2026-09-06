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
2. **IN PROGRESS — SCHEMA/CONTRACT SUB-UNIT GREEN** — ownership-scoped conversation persistence. V007–V009 migrations, schema registration, lifecycle smoke coverage, and owner-scoped repository contracts are delivered; prepared-SQL repository behavior and explicit cross-owner read/write denial remain unfinished.
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

## Task 2 Delivered So Far
- Additive V007 conversations, V008 messages, and V009 message-citations migrations registered through `DatabaseBootstrap`.
- Global database schema version advanced to 9 without changing prior migration version identities.
- `TableNames` exposes the three new plugin-owned tables and includes them in safe uninstall order.
- V007 scopes conversation identity through the `(owner_scope, conversation_id)` unique key and owner/update index.
- V008 carries owner scope with each persisted message and indexes `(conversation_id, owner_scope, id)`.
- V009 stores canonical citation lineage attached to persisted messages with deterministic message/citation uniqueness.
- Domain contracts `ConversationRepository` and `MessageRepository` require owner scope on every declared find/create/append operation.
- Real WordPress database smoke now covers clean install, V1→V9 automatic upgrade, migration idempotency, new indexes, default uninstall retention, opt-in uninstall deletion, and clean reinstall through V009.

Task 2 is not complete: `WpdbConversationRepository` / `WpdbMessageRepository`, bounded prepared-SQL behavior, and explicit cross-owner read/write denial tests remain required.

## TDD Evidence
Task 1 primary behavioral RED:
- test-only SHA `2b6b708d5f8e11249630f2417c67cfb9ff416ebc`
- CI `34002442391` — FAILURE after reaching the behavior suite because the planned M11 request contracts did not yet exist.

Task 1 review regression RED:
- test-only SHA `65e93ba3d55295cf950c8687ca07ba751ec40fa0`
- CI `34003104992` — FAILURE proving caller-controlled model/conversation identifiers were not yet hard-bounded.
- fix SHA `c624a90669692876bec1546e1582a10db8acc335` — adds the 255-byte identifier ceilings.

Task 1 final verified implementation head before ledger closeout:
- SHA `993ed2705d4dc3665c238c01038deb0a9669b9f9`
- CI `34003238289` — SUCCESS across the repository's permanent CI jobs.

Task 2 schema/contract primary behavioral RED:
- test-only SHA `b7bc8f57c1b6197d63a6412e1fed2dc51e976222`
- CI `34007381906` — PHPStan passed, then PHPUnit reached 563 tests and failed exactly three new assertions because V007–V009 / owner-scoped persistence contracts did not yet exist.

Task 2 production/schema sequence:
- `9e3996605a07925e8b18e16bc02aacc2c93b2ba6` — V007–V009, schema version 9, table names, owner-scoped domain/repository contracts.
- `092b4e61a1b34a0df33686d74b547e186758c29f` — standards-only cleanup; not counted as behavioral evidence.
- `3969ba881ca01d12eca08923ddee22bbca9e864c` — preserves the V006 migration-version contract while permitting later global schema versions.

Task 2 WordPress lifecycle regression evidence:
- CI `34007639730` on `3969ba881ca01d12eca08923ddee22bbca9e864c` passed PHPStan, 563 PHPUnit tests / 2263 assertions, Composer audit, JavaScript, and packaging; WordPress activation passed, then the database smoke correctly exposed its stale hard-coded V006 schema expectation.
- `5d6e73ffc04627ba850cfb8608c301ec29671529` and `589783c3ce19ec5f538b67ba696ded660f480d08` update the real WordPress database/lifecycle smoke through V009.
- CI `34007787191` on `589783c3ce19ec5f538b67ba696ded660f480d08` passed PHP, JavaScript, package, activation, V009 database lifecycle, providers, knowledge, file-ingestion, and WooCommerce smoke checks before this documentation closeout.

## Integration Test Evidence
Task 2's V009 schema/lifecycle sub-unit is exercised in the real WordPress environment. Repository-level cross-owner denial remains intentionally unclaimed until the next Task 2 TDD slice.

Full indexed-fixture -> retrieval -> answer/citation integration remains Task 9.

## E2E / Visual Verification
Backend streaming smoke remains later M11 work; visual widget remains M14.

## Security Review
Task 1 scoped review covered caller-controlled authorization leakage, question/output/identifier bounds, stable client-safe failure categories, normalization, and raw diagnostic exposure.

Task 1 result at reviewed SHA `993ed2705d4dc3665c238c01038deb0a9669b9f9`: **0 Critical / 0 Important**. PR review record: `5123774888`.

Task 2 schema/contract review so far confirms owner scope is represented in the conversation/message schema and required by the declared repository APIs. Full independent Task 2 security review remains open until the prepared-SQL repositories and cross-owner denial behavior exist.

## Performance Review where relevant
Task 1 enforces construction-time hard ceilings for question bytes, caller-controlled identifiers, and requested generation output tokens before later orchestration/provider dispatch.

Task 2 schema indexes bound the intended owner/conversation lookup paths; repository query/result ceilings remain part of the unfinished SQL implementation slice.

## Code Review Findings
Task 1 review history includes one Important issue already resolved before closeout: caller-controlled model/conversation identifiers lacked an explicit hard byte bound.

Current unresolved Task 1 findings: **0 Critical / 0 Important**.

No Critical/Important finding has been identified in the completed Task 2 schema/contracts sub-unit. Task 2's independent review gate remains open because the repository implementation is unfinished.

## Fixes
- `c624a90669692876bec1546e1582a10db8acc335` — bound M11 request identifiers to 255 bytes.
- `993ed2705d4dc3665c238c01038deb0a9669b9f9` — restore standards-required request property documentation.
- `3969ba881ca01d12eca08923ddee22bbca9e864c` — remove the stale assumption that V006 must remain the current global schema version.
- `5d6e73ffc04627ba850cfb8608c301ec29671529` / `589783c3ce19ec5f538b67ba696ded660f480d08` — advance real WordPress schema/lifecycle assertions from V006 through V009.

## Fresh Verification Results
Task 1 reviewed head `993ed2705d4dc3665c238c01038deb0a9669b9f9` passed CI `34003238289`.

Task 2 schema/lifecycle head `589783c3ce19ec5f538b67ba696ded660f480d08` passed the PHP, JavaScript, package, and all substantive WordPress smoke steps in CI `34007787191` before this ledger update. A fresh exact-head CI run on this documentation commit is required before the branch can claim exact-head GREEN.

## Commits
Task 1 and Task 2 implementation/history are retained on PR #16 (`feat/m11-rag-chat-orchestration`).

## Files Changed
Task 1:
- `src/Chat/ChatRequest.php`
- `src/Chat/ChatResult.php`
- `src/Chat/ChatFailureReason.php`
- `src/RAG/GroundingMode.php`
- `tests/Unit/Chat/ChatRequestContractTest.php`
- `tests/Unit/Chat/ChatResultContractTest.php`

Task 2 so far:
- `src/Database/Migrations/V007CreateConversationsTable.php`
- `src/Database/Migrations/V008CreateMessagesTable.php`
- `src/Database/Migrations/V009CreateMessageCitationsTable.php`
- `src/Database/DatabaseBootstrap.php`
- `src/Database/DatabaseSchema.php`
- `src/Database/TableNames.php`
- `src/Conversations/Conversation.php`
- `src/Conversations/ConversationMessage.php`
- `src/Conversations/ConversationRepository.php`
- `src/Conversations/MessageRepository.php`
- `tests/Unit/Conversations/ConversationPersistenceContractTest.php`
- `tests/Unit/Database/ChunkSearchMigrationContractTest.php`
- `scripts/test-wp-database.php`
- `scripts/test-wp-database.sh`
- this milestone ledger.

## Known Limitations
Task 2 intentionally remains open until real prepared-SQL conversation/message repositories enforce ownership on every read/write and fresh tests prove cross-owner denial. Memory, retrieval orchestration, citation validation, prompt construction, generation orchestration, and streaming remain Tasks 3–9.

## Documentation Updated
This ledger reflects the actual M11 Task 1 closeout and Task 2 schema/contracts/lifecycle evidence instead of treating persistence as untouched.

## Completion Checklist
M11 remains incomplete until Tasks 2–9 and all milestone-wide security/performance/integration/merge gates pass.

## Exact Next Unfinished Action
Continue Task 2 with a **test-only behavioral RED** for `WpdbConversationRepository` and `WpdbMessageRepository`: create/fetch/append using prepared SQL, reject cross-owner conversation reads and message appends, hard-bound caller-controlled persistence fields and result counts, and prove malicious-looking identifiers round-trip without widening scope. Do not mark Task 2 complete until that RED is followed by production GREEN, independent review, and durable closeout evidence.

## Next Milestone
M12 — Admin Onboarding/Bot Management.
