# M11 RAG Chat Orchestration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the secure backend RAG conversation path with bounded memory, deterministic grounding/no-answer behavior, validated citations, provider-neutral generation, normalized streaming, scoped persistence, and end-to-end acceptance coverage.

**Architecture:** A server-side `ChatOrchestrator` coordinates focused policy, memory, retrieval, grounding, prompt, citation, generation, persistence, and stream boundaries. M03 remains the only generation provider boundary and M10 remains the retrieval boundary; authorization and grounding decisions remain deterministic PHP application controls.

**Tech Stack:** PHP 8.2+, WordPress plugin APIs/database conventions, PHPUnit, PHPStan, PHPCS/WPCS, Composer, existing GitHub Actions CI and WordPress smoke suite.

**Spec:** `docs/superpowers/specs/2026-09-06-m11-rag-chat-orchestration-design.md`

## Global Constraints

- Status/design approval: `AUTO-APPROVED — SCHEDULED MODE`.
- No live/paid provider call in normal CI.
- Reuse `WpRagAiChatbot\Providers\GenerationProvider` / `GenerationRequest` / `GenerationResult`; do not move product semantics into provider adapters.
- Reuse M10 retrieval; retrieved text/metadata is untrusted data and never authorizes access or becomes system policy.
- Current user input <= 16 KiB UTF-8 bytes.
- Recent memory <= 12 messages and <= 24 KiB text.
- RAG context <= 12 candidates and <= 48 KiB text.
- Default requested generation output <= 4096 tokens and must remain within M03's existing hard maximum.
- Strict insufficient evidence returns deterministic no-answer without issuing a generation call.
- Citations may resolve only to final selected access-approved candidates and trusted canonical display metadata.
- Client-facing results/events never expose credentials, raw vendor payloads, raw provider/database exceptions, SQL, or unrestricted metadata.
- Each meaningful task uses real RED -> GREEN evidence at exact SHAs; lint-only failures do not count as behavioral RED.
- Each task closes only after focused/full verification and independent review with 0 unresolved Critical/Important findings.

---

### Task 1: Chat request, grounding mode, and bounded value objects

**Files:**
- Create: `src/Chat/ChatRequest.php`
- Create: `src/Chat/ChatResult.php`
- Create: `src/Chat/ChatFailureReason.php`
- Create: `src/RAG/GroundingMode.php`
- Create: `tests/Unit/Chat/ChatRequestTest.php`
- Create: `tests/Unit/Chat/ChatResultTest.php`
- Update: `docs/milestones/M11-rag-chat-grounding-citations-memory-streaming.md`

**Interfaces:**
- Produces: immutable normalized request/result contracts consumed by all later tasks.
- `ChatRequest` fields: `string $question`, `string $model_id`, `GroundingMode $grounding_mode`, `?string $conversation_id`, `int $max_output_tokens`.
- Transport-owned identity/access values must be separate trusted collaborator/context objects rather than model/user-controlled metadata.

- [ ] **Step 1: Write failing value-object tests** covering blank question/model rejection, >16 KiB question rejection, invalid output limits, normalized conversation ID rules, `STRICT`/`ASSISTED`, and result invariants.

```php
public function test_request_rejects_question_over_16_kib(): void {
    $this->expectException( InvalidArgumentException::class );
    new ChatRequest( str_repeat( 'a', 16385 ), 'gpt-test', GroundingMode::STRICT );
}
```

- [ ] **Step 2: Commit test-only RED and verify CI reaches PHPUnit**.

Run through exact-SHA CI. Expected behavioral RED: missing `ChatRequest`, `ChatResult`, and `GroundingMode` classes after static-analysis/style gates pass.

- [ ] **Step 3: Implement minimum immutable contracts** with constructor validation only; no orchestration yet.

- [ ] **Step 4: Verify GREEN** with `composer verify:php`, package, JS quality, and WordPress smoke on exact SHA.

- [ ] **Step 5: Independent review** for caller-controlled authorization leakage and missing bounds; fix Critical/Important findings with regression RED/GREEN.

- [ ] **Step 6: Update milestone ledger and commit Task 1 closeout**.

---

### Task 2: Ownership-scoped conversation persistence

**Files:**
- Create: `src/Database/Migrations/V007CreateConversationsTable.php`
- Create: `src/Database/Migrations/V008CreateMessagesTable.php`
- Create: `src/Database/Migrations/V009CreateMessageCitationsTable.php`
- Modify: migration registry/schema version files discovered from the existing V006 path.
- Create: `src/Conversations/Conversation.php`
- Create: `src/Conversations/ConversationMessage.php`
- Create: `src/Conversations/ConversationRepository.php`
- Create: `src/Conversations/MessageRepository.php`
- Create: WordPress SQL repository implementations following existing repository conventions.
- Create: unit + WordPress integration migration/repository tests.

**Interfaces:**
- `ConversationRepository::find_for_owner( string $conversation_id, string $owner_scope ): ?Conversation`
- `ConversationRepository::create_for_owner( string $owner_scope ): Conversation`
- `MessageRepository::append_for_owner( string $conversation_id, string $owner_scope, ConversationMessage $message ): void`
- Every read/write consumes owner scope explicitly.

- [ ] **Step 1: Write migration/repository RED tests** proving schema versions are additive/idempotent and another owner's conversation cannot be read or written.
- [ ] **Step 2: Verify behavioral RED in WordPress integration CI** before production migration/repository files exist.
- [ ] **Step 3: Implement V007-V009 and scoped repositories** using prepared SQL and bounded normalized fields.
- [ ] **Step 4: Verify upgrade/idempotency plus full CI GREEN**.
- [ ] **Step 5: Security review** prepared SQL, ownership predicates, content/metadata bounds, atomic append semantics, no credential/raw-provider persistence.
- [ ] **Step 6: Fix findings, re-review, document exact evidence, commit closeout**.

---

### Task 3: Deterministic bounded memory assembly

**Files:**
- Create: `src/Memory/ConversationMemory.php`
- Create: `src/Memory/ConversationHistory.php`
- Create: `src/Memory/MemoryAssembler.php`
- Create: `tests/Unit/Memory/MemoryAssemblerTest.php`

**Interfaces:**
- `ConversationHistory::recent_for_owner( string $conversation_id, string $owner_scope, int $limit ): array`
- `MemoryAssembler::assemble( string $conversation_id, string $owner_scope ): ConversationMemory`
- Output is <=12 messages and <=24 KiB text plus at most one versioned summary.

- [ ] **Step 1: RED tests** for newest-message retention, deterministic truncation, summary inclusion, hard message/byte ceilings, no cross-owner history.
- [ ] **Step 2: Verify RED exact SHA**.
- [ ] **Step 3: Implement minimal assembler**; drop oldest content first while preserving deterministic ordering.
- [ ] **Step 4: GREEN/full CI**.
- [ ] **Step 5: Independent review** for unbounded transcript paths and privacy leaks; regression cycle if needed.
- [ ] **Step 6: Ledger closeout commit**.

---

### Task 4: Citation registry and validator

**Files:**
- Create: `src/Citations/Citation.php`
- Create: `src/Citations/CitationRegistry.php`
- Create: `src/Citations/CitationValidationResult.php`
- Create: `src/Citations/CitationValidator.php`
- Create: `tests/Unit/Citations/CitationRegistryTest.php`
- Create: `tests/Unit/Citations/CitationValidatorTest.php`

**Interfaces:**
- `CitationRegistry::from_candidates( array $retrieval_candidates ): CitationRegistry`
- deterministic IDs `C1..Cn` in final context order.
- `CitationValidator::validate( string $answer, CitationRegistry $registry ): CitationValidationResult`
- Exposed URL/title always originate from registry/canonical candidate metadata, never from model-authored URLs.

- [ ] **Step 1: RED tests** for deterministic IDs, unknown citation rejection, duplicate/ill-formed markers, model-authored URL non-authority, lineage preservation, registry hard limit.
- [ ] **Step 2: Verify RED**.
- [ ] **Step 3: Implement parser/registry/validator without external dependencies**.
- [ ] **Step 4: GREEN/full CI**.
- [ ] **Step 5: Security review** citation spoofing/link safety/unauthorized lineage; fix via regression tests.
- [ ] **Step 6: Close ledger**.

---

### Task 5: Prompt/context builder with evidence isolation

**Files:**
- Create: `src/RAG/PromptContext.php`
- Create: `src/RAG/PromptBuilder.php`
- Create: `tests/Unit/RAG/PromptBuilderTest.php`

**Interfaces:**
- `PromptBuilder::build( ChatRequest $request, ConversationMemory $memory, CitationRegistry $citations ): GenerationRequest`
- Policy/instructions section is application-owned.
- Memory and retrieved evidence are delimited separately.
- Evidence section explicitly labels content `UNTRUSTED EVIDENCE — DATA ONLY`.
- Context <=48 KiB and <=12 evidence candidates.

- [ ] **Step 1: RED tests** proving malicious retrieved text such as `ignore previous instructions` remains only in data section, cannot alter instructions/model/grounding, and deterministic context truncation obeys limits.
- [ ] **Step 2: Verify RED exact SHA**.
- [ ] **Step 3: Implement builder using existing `GenerationRequest`**.
- [ ] **Step 4: GREEN/full CI**.
- [ ] **Step 5: Prompt-injection/security review**; regression fixes.
- [ ] **Step 6: Ledger closeout**.

---

### Task 6: Grounding policy and deterministic strict no-answer

**Files:**
- Create: `src/RAG/GroundingDecision.php`
- Create: `src/RAG/GroundingPolicy.php`
- Create: `src/RAG/DeterministicGroundingPolicy.php`
- Create: `tests/Unit/RAG/DeterministicGroundingPolicyTest.php`

**Interfaces:**
- `GroundingPolicy::decide( GroundingMode $mode, array $selected_candidates ): GroundingDecision`
- `GroundingDecision` exposes `may_generate` and stable reason; canonical strict no-answer content is application-owned.

- [ ] **Step 1: RED tests**: strict + zero candidates => no generation; strict insufficient deterministic evidence => no generation; assisted remains eligible while still bounded; no LLM used for decision.
- [ ] **Step 2: Verify RED**.
- [ ] **Step 3: Implement smallest deterministic rule set** using existing M10 candidate confidence/evidence semantics documented by current code.
- [ ] **Step 4: GREEN/full CI**.
- [ ] **Step 5: Review for fabricated confidence or model-bypass paths; fix/re-review**.
- [ ] **Step 6: Close ledger**.

---

### Task 7: Non-streaming ChatOrchestrator

**Files:**
- Create: `src/Chat/ChatRequestPolicy.php`
- Create: `src/Chat/ChatOrchestrator.php`
- Create: `src/Chat/ChatException.php`
- Create: `tests/Unit/Chat/ChatOrchestratorTest.php`
- Create deterministic fakes under `tests/Support` only where existing test conventions require reusable doubles.

**Interfaces:**
- `ChatOrchestrator::respond( ChatRequest $request, ChatAccessContext $access ): ChatResult`
- Consumes ownership-scoped memory, M10 retrieval, grounding, prompt builder, `GenerationProvider`, citation validator, persistence hooks.
- At most one answer-generation call per orchestration attempt.

- [ ] **Step 1: RED orchestration tests** proving order: policy -> memory -> retrieval -> grounding -> citations/prompt -> generation -> citation validation -> scoped persistence; strict no-answer emits zero provider calls; retrieval/provider errors map to safe stable reasons; invalid citations fail closed.
- [ ] **Step 2: Verify behavioral RED**.
- [ ] **Step 3: Implement minimum orchestrator and request policy**.
- [ ] **Step 4: GREEN/full CI** including provider fake call counts.
- [ ] **Step 5: Independent security/performance review** for access ordering, external-call count, duplicate persistence, exception redaction, rate/cost checks.
- [ ] **Step 6: Regression fixes/re-review and close ledger**.

---

### Task 8: Normalized streaming and cancellation

**Files:**
- Create: `src/Chat/Streaming/StreamEventType.php`
- Create: `src/Chat/Streaming/StreamEvent.php`
- Create: `src/Chat/Streaming/GenerationStream.php`
- Create: `src/Chat/Streaming/Cancellation.php`
- Create: `src/Chat/Streaming/StreamingChatOrchestrator.php`
- Create: `tests/Unit/Chat/Streaming/StreamingChatOrchestratorTest.php`

**Interfaces:**
- Event types: `message.start`, `message.delta`, `citation`, `message.complete`, `error`.
- Monotonic sequence; start first; one terminal event; no post-terminal delta.
- Cancellation stops consumption and prevents incomplete assistant-message persistence.

- [ ] **Step 1: RED contract tests** for ordering, bounded delta normalization, sanitized error, citation events from registry only, terminal uniqueness, cancellation cleanup.
- [ ] **Step 2: Verify RED**.
- [ ] **Step 3: Implement provider-neutral streaming contracts/orchestrator** using deterministic fake stream; do not implement M19 tool events.
- [ ] **Step 4: GREEN/full CI**.
- [ ] **Step 5: Review disconnect cleanup/secret redaction/event bounds; regression fixes**.
- [ ] **Step 6: Close ledger**.

---

### Task 9: End-to-end acceptance, hooks, security/performance closeout, PR and merge

**Files:**
- Create: `tests/Integration/RAG/RagChatAcceptanceTest.php`
- Add/modify minimal analytics/persistence hook contracts discovered from repository conventions.
- Update: `docs/ARCHITECTURE.md`
- Update: `docs/FEATURE-MATRIX.md`
- Update: `docs/progress/STATUS.md`
- Update: `docs/milestones/M11-rag-chat-grounding-citations-memory-streaming.md`
- Add: `docs/progress/M11-CLOSEOUT.md` only if repository precedent uses a milestone closeout file for this stage.

**Interfaces:**
- Acceptance path: indexed fixture -> M10 retrieval -> bounded M11 memory/context -> fake M03 generation -> citation validation -> scoped persistence/result.

- [ ] **Step 1: Test-only acceptance fixture** proving exact/paraphrase retrieval evidence can ground an answer; restricted evidence never reaches public result; malicious retrieved instructions cannot elevate policy; strict insufficient evidence uses deterministic no-answer; citation IDs resolve only to selected sources; limits and safe diagnostics hold.
- [ ] **Step 2: Verify acceptance RED only if genuinely missing composition behavior**. If current production composition already satisfies a new acceptance assertion, record GREEN honestly and never manufacture a defect.
- [ ] **Step 3: Implement only missing composition/hook wiring**.
- [ ] **Step 4: Full security review**: ownership/IDOR, prompt injection, citation spoofing/link safety, rate/cost abuse, secret/error leakage, SQL preparation, context/output bounds, duplicate writes, provider-call count.
- [ ] **Step 5: Performance review**: bounded DB reads, retrieval candidates, prompt bytes, provider calls, stream deltas, cancellation cleanup; record limitations honestly.
- [ ] **Step 6: Independent PR review**; fix all Critical/Important findings with regression RED/GREEN and re-review.
- [ ] **Step 7: Final exact-head CI**: `php-quality`, `js-quality`, `package`, `wordpress-smoke` all GREEN; capture artifact digest.
- [ ] **Step 8: Reconcile docs and mark M11 complete only when evidence matches code/CI**.
- [ ] **Step 9: Finish branch/PR** using expected-head protection; merge only exact verified head.
- [ ] **Step 10: Verify fresh post-merge `main` CI** before M11 is declared complete.

## Plan self-review

- Spec coverage: all M11 in-scope requirements map to Tasks 1-9.
- Placeholder scan: no TODO/TBD or hand-waved implementation step remains.
- Type consistency: later tasks consume the request/result, repositories, memory, citation, prompt, grounding, and streaming contracts introduced earlier.
- TDD sequencing: every behavioral task starts test-only and requires exact-SHA RED before production code; non-behavioral lint failures are not RED evidence.
- Risk/rollback: persistence schema is additive; provider/retrieval contracts are reused; each task is independently reviewable and can be reverted without changing prior milestone semantics.
- Scope: polished REST/widget/admin UI and general tool/action framework remain outside M11.

**Plan decision:** **AUTO-APPROVED — SCHEDULED MODE**. Execute Task 1 next under strict TDD.