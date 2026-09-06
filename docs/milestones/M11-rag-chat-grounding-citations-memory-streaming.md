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
2. **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED** — ownership-scoped conversation persistence. V007–V009 migrations, schema/lifecycle coverage, owner-scoped contracts, prepared-SQL repositories, cross-owner denial, hard persistence bounds, malicious-identifier checks, real WordPress repository verification, and scoped correctness/security review are complete.
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

## Task 2 Delivered Behavior
- Additive V007 conversations, V008 messages, and V009 message-citations migrations registered through `DatabaseBootstrap`.
- Global database schema version advanced to 9 without changing prior migration version identities.
- `TableNames` exposes the three new plugin-owned tables and includes them in safe uninstall order.
- V007 scopes conversation identity through the `(owner_scope, conversation_id)` unique key and owner/update index.
- V008 carries owner scope with each persisted message and indexes `(conversation_id, owner_scope, id)`.
- V009 stores canonical citation lineage attached to persisted messages with deterministic message/citation uniqueness.
- Domain contracts `ConversationRepository` and `MessageRepository` require owner scope on every declared find/create/append operation.
- `WpdbConversationRepository` hard-bounds/normalizes persisted identifiers, creates collision-resistant application conversation IDs, performs owner-scoped prepared lookup with `LIMIT 1`, and returns cross-owner lookup as unavailable.
- `WpdbMessageRepository` hard-bounds conversation/owner identifiers to 191 bytes, roles to 32 bytes, and message content to 65,536 bytes before SQL execution.
- Message append is one prepared `INSERT ... SELECT` guarded by both conversation ID and owner scope, avoiding a check-then-insert authorization race.
- Cross-owner append fails with an application-owned generic persistence error and does not reveal whether the conversation exists for another owner.
- Malicious-looking identifiers/content are bound as values and round-trip without widening SQL scope.
- Real WordPress database smoke covers clean install, V1→V9 automatic upgrade, migration idempotency, indexes, default uninstall retention, opt-in deletion, clean reinstall, owner-scoped create/find/append, cross-owner read/write denial, exact malicious-looking content round-trip, and unchanged persisted message count after a denied append.
- Scoped correctness/security review at exact head `b20798c14ec6169b8ffddfed2a8e03199c1fd822` closed with **0 Critical / 0 Important** findings. PR review record: `5124482278`.

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
- CI `34007639730` on `3969ba881ca01d12eca08923ddee22bbca9e864c` passed PHPStan, 563 PHPUnit tests / 2263 assertions, Composer audit, JavaScript, and packaging; WordPress activation passed, then database smoke exposed its stale hard-coded V006 schema expectation.
- `5d6e73ffc04627ba850cfb8608c301ec29671529` and `589783c3ce19ec5f538b67ba696ded660f480d08` update the real WordPress database/lifecycle smoke through V009.
- CI `34007787191` on `589783c3ce19ec5f538b67ba696ded660f480d08` passed PHP, JavaScript, package, activation, V009 database lifecycle, providers, knowledge, file-ingestion, and WooCommerce smoke checks.

Task 2 SQL-repository test-first sequence:
- `c1f81cbb3f75627620f5f1afb235fca602f28f51` introduced the repository behavior tests, but CI `34010071200` stopped in PHPCS before behavior and is **not** counted as RED.
- test-only standards cleanup head `9102bce1dfcc6df50f9e117c3f693ca41758a1dc` produced valid behavioral RED in CI `34010133168`: PHPStan passed with zero errors, then PHPUnit reached 570 tests / 2263 assertions and produced exactly seven expected missing-class errors for `WpdbConversationRepository` / `WpdbMessageRepository`.
- `27a7ff05a95b278c67b4277aeb8c0960296f5e7d` added the owner-scoped conversation SQL repository.
- `5839cbed2d9ff21b8c34bbec7e8772179fea2423` added the atomic owner-scoped message SQL repository.
- CI `34010188260` stopped in PHPCS on production documentation/standards and is not counted as GREEN.
- `1a76db4aa36bca53faeaa588986bb6306d06dd7c`, `19ad1a375a9631501b52c5fd374037cfd49fc9a5`, and `5970ad7d3b5fde1e629047493415564fe0a2abda` are standards/documentation corrections without intended behavior change.
- exact implementation head `5970ad7d3b5fde1e629047493415564fe0a2abda` reached GREEN PHP verification: PHPStan 0 errors, PHPUnit 570/570 tests / 2296 assertions, and Composer audit clean.

Task 2 real WordPress repository verification:
- `0e947f78802266b6d5c455a15077f6af3cfe15fb` added real WordPress repository assertions.
- `34182b7144cc99417bb1cd2df6213cfaa146909b` wires those assertions into the database lifecycle smoke on clean install, V1→V9 upgrade, and clean reinstall.
- CI `34010412433` on `34182b7144cc99417bb1cd2df6213cfaa146909b` — SUCCESS across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`; the database step specifically passed the owner-scoped repository assertions before the remaining provider/knowledge/file/WooCommerce smoke checks also passed.

## Integration Test Evidence
Task 2 persistence is exercised against a real WordPress/MySQL environment in addition to unit-contract coverage. The fixture proves owner-scoped create/find/append, cross-owner read/write denial, SQL-looking owner/content round-trip, and no persisted-state change after a denied append. Full indexed-fixture -> retrieval -> answer/citation integration remains Task 9.

## E2E / Visual Verification
Backend streaming smoke remains later M11 work; visual widget remains M14.

## Security Review
Task 1 scoped review covered caller-controlled authorization leakage, question/output/identifier bounds, stable client-safe failure categories, normalization, and raw diagnostic exposure.

Task 1 result at reviewed SHA `993ed2705d4dc3665c238c01038deb0a9669b9f9`: **0 Critical / 0 Important**. PR review record: `5123774888`.

Task 2 review covered V007–V009 migrations, prepared value binding, mandatory owner predicates, IDOR/cross-owner fail-closed behavior, atomic append authorization, hard persisted-field bounds, malicious-looking values, sensitive-data boundaries, uninstall/upgrade behavior, and indexed owner/conversation access paths.

Task 2 result at reviewed SHA `b20798c14ec6169b8ffddfed2a8e03199c1fd822`: **0 Critical / 0 Important**. PR review record: `5124482278`.

## Performance Review where relevant
Task 1 enforces construction-time hard ceilings for question bytes, caller-controlled identifiers, and requested generation output tokens before later orchestration/provider dispatch.

Task 2 limits conversation lookup to one owner-scoped row, rejects oversized persistence inputs before database execution, uses indexed `(owner_scope, conversation_id)` / `(conversation_id, owner_scope, id)` paths, and performs message authorization/write as one SQL statement instead of a race-prone read followed by insert.

## Code Review Findings
Task 1 review history includes one Important issue already resolved before closeout: caller-controlled model/conversation identifiers lacked an explicit hard byte bound.

Current unresolved Task 1 findings: **0 Critical / 0 Important**.

Task 2 scoped review found **0 Critical / 0 Important** findings; no review-fix regression cycle was required.

## Fixes
- `c624a90669692876bec1546e1582a10db8acc335` — bound M11 request identifiers to 255 bytes.
- `993ed2705d4dc3665c238c01038deb0a9669b9f9` — restore standards-required request property documentation.
- `3969ba881ca01d12eca08923ddee22bbca9e864c` — remove the stale assumption that V006 must remain the current global schema version.
- `5d6e73ffc04627ba850cfb8608c301ec29671529` / `589783c3ce19ec5f538b67ba696ded660f480d08` — advance real WordPress schema/lifecycle assertions from V006 through V009.
- `1a76db4aa36bca53faeaa588986bb6306d06dd7c` / `19ad1a375a9631501b52c5fd374037cfd49fc9a5` / `5970ad7d3b5fde1e629047493415564fe0a2abda` — resolve standards/documentation failures in the SQL repository production slice without changing the tested owner-scoped behavior.

## Fresh Verification Results
Task 1 reviewed head `993ed2705d4dc3665c238c01038deb0a9669b9f9` passed CI `34003238289`.

Task 2 real-repository integration head `34182b7144cc99417bb1cd2df6213cfaa146909b` passed CI `34010412433`: PHPStan 0 errors; PHPUnit 570 tests / 2296 assertions; Composer audit clean; JavaScript verification GREEN; package GREEN; WordPress activation/database/providers/knowledge/file-ingestion/WooCommerce smoke GREEN. Artifact `9982282541`, 840703 bytes, digest `sha256:521dfcac64a207611aa0298075f49eafd4dd43949048ffed7dc8d7bc4a65d1fc`.

Task 2 reviewed exact head `b20798c14ec6169b8ffddfed2a8e03199c1fd822` passed CI `34010581646` across all permanent jobs. Artifact `9982326787`, 840704 bytes, digest `sha256:ab15f8879949e72283e84da4a1dc78b4e02a5c8c01fef2a6ad3799d804e8818b`.

A fresh exact-head CI run on this Task 2 closeout documentation commit is required before advancing Task 3.

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

Task 2:
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
- `src/Database/Repository/WpdbConversationRepository.php`
- `src/Database/Repository/WpdbMessageRepository.php`
- `tests/Support/Database/RecordingConnection.php`
- `tests/Unit/Conversations/ConversationPersistenceContractTest.php`
- `tests/Unit/Conversations/WpdbConversationRepositoriesTest.php`
- `tests/Unit/Database/ChunkSearchMigrationContractTest.php`
- `scripts/test-wp-database.php`
- `scripts/test-wp-conversations.php`
- `scripts/test-wp-database.sh`
- this milestone ledger.

## Known Limitations
Memory, retrieval orchestration, citation validation, prompt construction, generation orchestration, and streaming remain Tasks 3–9. Task 2's persistence foundation is complete and reviewed; no general conversation-listing/UI semantics are added outside M11 scope.

## Documentation Updated
This ledger reflects the actual M11 Task 1 and Task 2 closeout evidence without overstating later memory/citation/orchestration work.

## Completion Checklist
M11 remains incomplete until Tasks 3–9 and all milestone-wide security/performance/integration/merge gates pass.

## Exact Next Unfinished Action
After exact-head CI passes on this Task 2 closeout commit, begin **Task 3 — deterministic bounded memory assembly** with a test-only behavioral RED proving newest-message retention, deterministic oldest-first truncation, at most 12 messages, at most 24 KiB memory text, at most one versioned summary, stable chronological output ordering, and no cross-owner history access. Do not add production memory classes until that RED reaches the behavior suite for the expected missing-contract reason.

## Next Milestone
M12 — Admin Onboarding/Bot Management.
