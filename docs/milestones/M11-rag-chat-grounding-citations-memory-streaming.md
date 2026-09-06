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
4. **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED** — deterministic bounded citation registry and fail-closed validator over trusted final retrieval lineage.
5. **IMPLEMENTATION GREEN / COORDINATOR REVIEW FINDINGS FIXED / INDEPENDENT REVIEW PENDING** — prompt/context builder with evidence isolation.
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

## Task 4 Delivered Behavior
- `Citation` carries request-local application-owned IDs plus trusted chunk/document/source lineage.
- `CitationRegistry` deterministically assigns `C1..Cn` in final context order and rejects more than 12 candidates.
- `CitationValidator` resolves only registry-backed markers in first-use answer order.
- Unknown, duplicate, leading-zero, and trailing-junk numeric citation markers fail closed.
- Ordinary bracketed prose such as `[Context]` is ignored rather than misclassified as a citation.
- Model-authored URLs never become canonical citation metadata; M10 does not currently expose canonical title/URL fields on `RetrievalCandidate`, so registry-created display title/URL values remain null rather than being invented.

## Task 5 Delivered Behavior
- `CitationRegistry` now retains only the prompt-safe selected fields `{citation id, evidence content}` needed by prompt construction; retrieval scores, visibility, authorization state, raw metadata, and canonical lineage internals do not enter the prompt-facing contract.
- `PromptContext` accepts at most 12 selected evidence entries and renders them under an explicit `UNTRUSTED EVIDENCE — DATA ONLY` boundary.
- The complete evidence section, including opening/closing framing and escaping growth, is hard-bounded to 49,152 bytes; lower-priority evidence is dropped deterministically when it cannot fit.
- `PromptBuilder` keeps application policy in `GenerationRequest::instructions` and renders deterministic `MEMORY -> EVIDENCE -> QUESTION` data sections.
- Request-selected model ID and max output tokens remain application/request controls and cannot be replaced by retrieved content.
- Untrusted memory summary/message text, retrieved evidence, and the current question are escaped before rendering so they cannot reproduce literal machine-generated section delimiters.
- Prompt input excludes provider secrets, raw diagnostics, visibility/auth metadata, chunk IDs, and document IDs.

## TDD Evidence
### Task 1
- Primary behavioral RED `2b6b708d5f8e11249630f2417c67cfb9ff416ebc`, CI `34002442391`.
- Review regression RED `65e93ba3d55295cf950c8687ca07ba751ec40fa0`, CI `34003104992`; fix `c624a90669692876bec1546e1582a10db8acc335`.
- Verified head `993ed2705d4dc3665c238c01038deb0a9669b9f9`, CI `34003238289` — SUCCESS.

### Task 2
- Schema/contract RED `b7bc8f57c1b6197d63a6412e1fed2dc51e976222`, CI `34007381906`.
- SQL-repository RED `9102bce1dfcc6df50f9e117c3f693ca41758a1dc`, CI `34010133168`: PHPStan passed, PHPUnit reached 570 tests / 2,263 assertions with seven expected missing-class errors.
- Production sequence: `9e399660...`, `27a7ff05...`, `5839cbed...`, corrections through `5970ad7d...`, real WordPress persistence/lifecycle through `34182b7144cc99417bb1cd2df6213cfaa146909b`.
- Integration CI `34010412433` — SUCCESS; reviewed-head CI `34010581646` — SUCCESS.

### Task 3
- Initial test-only `68d05fa4...` stopped at PHPCS and is not counted as RED.
- Valid behavioral RED `2e063c2fd59334d454d7b17d7399a8f796d52cab`, CI `34020263699`: PHPStan passed; PHPUnit 575 tests / 2,301 assertions with exactly five expected Task 3 failures.
- Production sequence through `ac4b99aa6921db0f0fcc6dd5571497a352237fa3`.
- Implementation CI `34020490179` — SUCCESS: PHPStan 0 errors, PHPUnit 575/575 / 2,320 assertions, Composer audit clean, JS/package/WordPress smoke GREEN.
- Task 3 closeout documentation head `7cf8e5e6841d18c3f3268abe3bf9c2f55a6553db`, CI `34022929538` — SUCCESS; artifact `9986107488`, digest `sha256:83f1c6f20037960eb3f638bf2d4ac1472cc3fd35649bf76de202d0f6f474f0c0`.

### Task 4
- Initial test-only heads through `7dd0aa9313d8732d393221d2bae49507c583a0bf` stopped in PHPCS and are not counted as behavioral RED.
- Valid primary RED `e7cdc6e93d88cef558013fa9eeee0ad3210424d5`, CI `34023154402`: PHPStan 0 errors; PHPUnit 582 tests / 2,327 assertions with exactly seven expected failures because citation contracts did not exist.
- Production sequence through `f21cc9ba21219be94a635fc552228229d3a6ef31`.
- Review regression RED `26037f6e58b1c41285d66f13e7971144d0b4d93a`, CI `34023530100`: exactly one failure proving `[Context]` was falsely treated as a citation. Fix `ba4781dfddfcba7d6a83f4067480e9682f743b0d`.
- Follow-up regression RED `8d5195cb73861e5540a2cde4cdaf440911ae27d5`, CI `34023684049`: exactly one failure proving `[C1x]` was ignored instead of rejected. Fix `11e9ed3d71d40e94b054ad881416344359250356`.
- Final implementation CI `34023743426` on `11e9ed3d71d40e94b054ad881416344359250356` — SUCCESS. PHPStan 0 errors; PHPUnit 583/583 / 2,368 assertions; Composer audit clean. Artifact `9986372467`, digest `sha256:6bddafe631d3a609488efacb61c806efb6a7a7d624f4dc1a92c193beaff8f617`.

### Task 5
- Initial test-only commit `8b8fd16c7c6781785390524c895e2a7e10e5f372` stopped at PHPCS and is not counted as behavioral RED.
- Valid primary RED `2149f0d4b8bf6aa95e8bb3587f6741609a719f36`, CI `34025759676`: PHPStan clean; PHPUnit reached 587 tests and failed exactly four new Task 5 assertions because `PromptContext` / `PromptBuilder` did not yet exist.
- Production sequence: `c7eb34d0...` prompt-safe registry evidence; `c7934173...` bounded `PromptContext`; `c3e84273...` `PromptBuilder`; standards/static-analysis corrections through `168c5ea22e5870b5f323fec461c9d4edbacd7af1`.
- Initial implementation GREEN CI `34025951322` on `168c5ea2...`: PHPStan 0 errors, PHPUnit 587/587 / 2,411 assertions, Composer audit clean, JS/package/WordPress smoke GREEN. Artifact `9987052679`, digest `sha256:b474f7580590a6a13f241bf6a0fd6acff483a8e2351477a0e1460256bd5c24bb`.
- Coordinator review `5124994011` found two Important issues: literal untrusted section-delimiter spoofing and omission of the closing evidence delimiter from the 48 KiB byte accounting. No Critical findings.
- Review-test commit `9a53604c...` stopped at PHPCS and is not counted as regression RED.
- Valid review regression RED `de7677b317b5877906c521b3a64824bc32550a8f`, CI `34026203479`: PHPStan 0 errors; PHPUnit 589 tests / 2,418 assertions with exactly two failures. Literal delimiter spoofing remained possible and the complete evidence section measured 49,161 bytes against the 49,152-byte ceiling.
- Fixes `d32a2d315053e54d9bb271fb6878f078bd092799` and `15f90f9d1014576e57ad1f3e9ab6a31bb842a0a1` escape untrusted prompt data and include complete evidence framing/escaping growth in byte accounting.
- Fixed implementation push CI `34026283976` on `15f90f9d...`: php-quality SUCCESS with PHPStan 0 errors, PHPUnit 589/589 / 2,423 assertions, Composer audit clean; js-quality SUCCESS; package SUCCESS; WordPress smoke was still running when the Task 5 ledger was advanced. Artifact `9987153515`, 847,563 bytes, digest `sha256:49b3cb6f756f39c1d43beb7945c426a22939e73457a16fb7df4bd2e063a916bc`.

## Integration Test Evidence
Task 2 persistence is exercised against real WordPress/MySQL. Tasks 3–5 are deterministic application logic covered by focused unit tests plus the full repository CI/WordPress smoke suite. Full indexed-fixture -> retrieval -> answer/citation integration remains Task 9.

## E2E / Visual Verification
Backend streaming smoke remains later M11 work; visual widget remains M14.

## Security Review
- Task 1 reviewed SHA `993ed2705...`: **0 Critical / 0 Important**. PR review `5123774888`.
- Task 2 reviewed SHA `b20798c14...`: **0 Critical / 0 Important**. PR review `5124482278`.
- Task 3 reviewed SHA `ac4b99aa...`: **0 Critical / 0 Important**. PR review `5124784589`.
- Task 4 final review on SHA `11e9ed3d...`: **0 unresolved Critical / 0 unresolved Important** after two Important parser-boundary findings were fixed through regression TDD. PR review `5124827141`.
- Task 5 coordinator review `5124994011`: **0 Critical / 2 Important**, both fixed through regression RED/GREEN. This coordinator review is explicitly **not** a substitute for the mandatory independent Task 5 review. Native reviewer/subagent transport still returns a transient MCP tunnel HTTP 404; GitHub Copilot reviewer requests did not produce a retained reviewer assignment or review submission.
- No blocking inline review threads exist at the last recovery.

## Performance Review where relevant
- Task 1 bounds question bytes, identifiers, and generation output tokens before later dispatch.
- Task 2 uses bounded indexed owner-scoped reads and one atomic authorization/write SQL statement.
- Task 3 requests at most 12 recent messages and performs bounded in-memory assembly against a 24 KiB text ceiling.
- Task 4 creates at most 12 registry entries and performs linear parsing over the already-bounded generated answer with no network/database calls.
- Task 5 accepts at most 12 evidence entries, enforces a complete 49,152-byte evidence-section ceiling after escaping, and performs deterministic bounded string assembly with no network/database calls.

## Code Review Findings
- Task 1: one Important identifier-bound issue; fixed with regression TDD.
- Task 2: 0 Critical / 0 Important.
- Task 3: 0 Critical / 0 Important.
- Task 4: two Important parser-boundary findings; both fixed with regression TDD; final review 0 unresolved Critical / 0 unresolved Important.
- Task 5 coordinator review: two Important prompt-framing/boundary findings; both fixed with regression TDD. Mandatory independent review remains pending.

## Fresh Verification Results
- Task 1 reviewed-head CI `34003238289` — SUCCESS.
- Task 2 reviewed/integration CI `34010581646` / `34010412433` — SUCCESS.
- Task 3 closeout CI `34022929538` — SUCCESS.
- Task 4 implementation CI `34023743426` — SUCCESS.
- Task 5 fixed implementation CI `34026283976`: php-quality, js-quality, and package GREEN at ledger update; wordpress-smoke still running. A fresh exact-head CI run on the final Task 5 documentation head is required before the next run may claim exact-head Task 5 verification.

## Files Changed Through Task 5
Task 1: `src/Chat/*` request/result/failure contracts, `src/RAG/GroundingMode.php`, unit contracts.

Task 2: V007–V009 migration/schema/table-name files; conversation/message domain and repositories; WordPress persistence/lifecycle fixtures.

Task 3: `src/Memory/ConversationHistory.php`, `ConversationMemory.php`, `MemoryAssembler.php`, unit tests, Task 3 progress record.

Task 4: `src/Citations/Citation.php`, `CitationRegistry.php`, `CitationValidationResult.php`, `CitationValidator.php`, citation unit tests, Task 4 closeout record.

Task 5:
- `src/Citations/CitationRegistry.php`
- `src/RAG/PromptContext.php`
- `src/RAG/PromptBuilder.php`
- `tests/Unit/RAG/PromptBuilderTest.php`
- `docs/progress/M11-TASK5-PROGRESS.md`
- this milestone ledger.

## Known Limitations
Deterministic grounding policy, generation orchestration, streaming, persistence/analytics composition, and milestone-wide acceptance remain Tasks 6–9. Task 5's mandatory independent review is still pending because the reviewer transport is transiently unavailable. Canonical citation title/URL enrichment still requires a trusted retrieval contract that exposes those fields; model-authored display links are intentionally not trusted.

## Documentation Updated
The M11 ledger plus Task 3/Task 4/Task 5 progress records now match actual Git/code/CI/review evidence without overstating Task 5 review closure or later work.

## Completion Checklist
M11 remains incomplete until Tasks 5–9 and all milestone-wide security/performance/integration/merge gates pass.

## Exact Next Unfinished Action
Re-fetch PR #16 and first obtain a genuine independent correctness/security/prompt-injection review of Task 5, including the delimiter-spoofing and complete-byte-budget fixes. Review policy/data separation, prompt injection, citation-ID exposure, metadata/secret exclusion, deterministic ordering/truncation, complete evidence byte accounting, and bounded work. Fix every Critical/Important finding through fresh regression RED -> GREEN evidence and re-review. Only after zero unresolved Critical/Important findings are independently confirmed should Task 5 be marked **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED** and Task 6 begin with its own test-only behavioral RED.

## Next Milestone
M12 — Admin Onboarding/Bot Management.
