# M10 Hybrid Retrieval & Reranking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

Status: **AUTO-APPROVED — SCHEDULED MODE**

**Goal:** Build bounded, explainable hybrid retrieval that combines semantic and lexical/exact search, enforces trusted access filters, supports optional reranking, and returns deterministic candidates/traces for M11.

**Architecture:** Add a `Retrieval` application/domain subsystem around the existing M08 embedding/vector contracts. Persist indexed chunk text in a dedicated local lexical/search projection, rank semantic and lexical channels independently, fuse with weighted reciprocal-rank fusion, recheck access fail-closed, optionally rerank only approved top-N candidates, and return bounded context candidates plus safe diagnostics.

**Tech Stack:** PHP 8.2+, WordPress `$wpdb`, PHPUnit, PHPStan, PHPCS, existing provider/vector-store contracts and GitHub Actions CI.

**Spec:** `docs/superpowers/specs/2026-09-05-m10-hybrid-retrieval-reranking-design.md`

## Global Constraints

- PHP-first WordPress runtime; no mandatory external backend.
- Retrieved content and metadata are untrusted data and never authorization/instructions.
- Hybrid retrieval is baseline behavior; lexical/exact retrieval must preserve identifiers such as SKUs/model numbers/error codes.
- Trusted access/visibility filtering must fail closed and is rechecked after fusion.
- All query, candidate, filter, fusion, rerank, and context sizes are hard-bounded.
- Default fusion is deterministic weighted RRF with `rrf_k=60`, semantic weight `1.0`, lexical weight `1.0`.
- No paid/external provider calls in normal CI.
- Every production behavior task uses real RED then GREEN evidence and an independent review gate.

---

## File structure

New domain/application files live under `src/Retrieval/` and focused subdirectories. Database persistence lives under `src/Retrieval/Lexical/` plus migration `V006CreateChunkSearchTable.php`. Tests mirror these boundaries under `tests/php/Unit/Retrieval/` and `tests/php/Integration/Retrieval/`. Existing indexing/queue composition is modified only where needed to update the search projection in the same accepted indexing workflow.

### Task 1: Query, configuration, candidate/result, and trace contracts

**Files:**
- Create: `src/Retrieval/RetrievalConfig.php`
- Create: `src/Retrieval/RetrievalQuery.php`
- Create: `src/Retrieval/QueryPreprocessor.php`
- Create: `src/Retrieval/RetrievalCandidate.php`
- Create: `src/Retrieval/ChannelEvidence.php`
- Create: `src/Retrieval/RetrievalTrace.php`
- Create: `src/Retrieval/RetrievalResult.php`
- Test: `tests/php/Unit/Retrieval/QueryPreprocessorTest.php`
- Test: `tests/php/Unit/Retrieval/RetrievalContractsTest.php`

**Interfaces:**
- Produces: `QueryPreprocessor::preprocess(string $query): RetrievalQuery`.
- Produces: immutable bounded config/result/trace DTOs consumed by all later tasks.

- [ ] **Step 1: Write failing tests** covering whitespace normalization, empty rejection, byte/token bounds, Unicode text, identifier tokens (`SKU-42/A`, `ERR_CONNECTION_RESET`, `model.x:7`), invalid config ceilings, finite scores, and immutable candidate lineage.
- [ ] **Step 2: Push the test-only commit and verify CI RED** because the `Retrieval` types do not exist. Record the exact commit SHA, workflow run, and expected missing-class failures.
- [ ] **Step 3: Implement minimal contracts/preprocessor**. Normalize whitespace with `preg_replace('/\s+/u', ' ', trim($query))`; reject invalid UTF-8/empty/oversized input; tokenize with a Unicode-aware identifier-friendly pattern; validate every numeric bound before any work.
- [ ] **Step 4: Run focused/full quality gates through CI and verify GREEN** with PHPUnit, PHPStan, PHPCS, package and smoke jobs.
- [ ] **Step 5: Commit** with `feat: add M10 retrieval query contracts` and update plan checkboxes/evidence.

### Task 2: Deterministic weighted reciprocal-rank fusion and confidence

**Files:**
- Create: `src/Retrieval/Fusion/RankedCandidate.php`
- Create: `src/Retrieval/Fusion/ReciprocalRankFusion.php`
- Create: `src/Retrieval/Confidence/RetrievalConfidence.php`
- Create: `src/Retrieval/Confidence/ConfidenceEstimator.php`
- Test: `tests/php/Unit/Retrieval/Fusion/ReciprocalRankFusionTest.php`
- Test: `tests/php/Unit/Retrieval/Confidence/ConfidenceEstimatorTest.php`

**Interfaces:**
- Consumes: `RetrievalConfig`, stable chunk IDs and channel-native scores.
- Produces: `ReciprocalRankFusion::fuse(array $channels): array` ordered fused candidates with `ChannelEvidence`.
- Produces: deterministic `[0,1]` score + `high|medium|low` confidence level.

- [ ] **Step 1: Write failing tests** proving `weight/(k+rank)`, cross-channel dedupe, semantic/lexical agreement boost, configured weights, finite/positive validation, max-result cap, and deterministic chunk-ID tie-breaks.
- [ ] **Step 2: Verify focused CI RED** before production classes exist.
- [ ] **Step 3: Implement RRF/confidence** without native-score normalization assumptions. Sort each channel deterministically before fusion, sum contributions by chunk ID, then order by fused score desc, best rank asc, chunk ID asc.
- [ ] **Step 4: Verify focused and full CI GREEN**.
- [ ] **Step 5: Commit** `feat: add deterministic hybrid fusion` and record RED/GREEN SHAs/runs.

### Task 3: Durable chunk-search projection and synchronization contract

**Files:**
- Create: `src/Database/Migrations/V006CreateChunkSearchTable.php`
- Modify: migration registry/composition file discovered from V005 registration.
- Create: `src/Retrieval/Lexical/ChunkSearchRecord.php`
- Create: `src/Retrieval/Lexical/ChunkSearchStore.php`
- Create: `src/Retrieval/Lexical/WpdbChunkSearchStore.php`
- Create: `src/Retrieval/Lexical/LexicalSearchRequest.php`
- Create: `src/Retrieval/Lexical/LexicalSearchMatch.php`
- Create: `src/Retrieval/Lexical/LexicalFilter.php`
- Modify: the concrete M08 index-plan executor/composition boundary that consumes `ChunkRecord` records.
- Test: `tests/php/Integration/Retrieval/WpdbChunkSearchStoreTest.php`
- Test: focused indexing integration test adjacent to the existing M08 index execution tests.

**Interfaces:**
- Produces: `ChunkSearchStore::replace_document_chunks(string $collection_id, string $document_key, ChunkSearchRecord ...$chunks): void`.
- Produces: `delete_document(...)` and `search(LexicalSearchRequest): array`.
- Consumes M07 `ChunkRecord`; projection fields are copied from accepted chunk lineage and safe bounded metadata only.

- [ ] **Step 1: Write failing migration/store tests** for idempotent replacement, deletion, collection/document/source/language/visibility scope, bounded safe metadata, and hard candidate ceiling.
- [ ] **Step 2: Verify WordPress integration CI RED** because V006/store do not exist.
- [ ] **Step 3: Implement V006 and store** using prepared SQL only. Add indexes for collection/visibility/language/document/source. Never load an unbounded collection into PHP.
- [ ] **Step 4: Write failing index synchronization test** proving the accepted M07/M08 execution path also replaces/deletes lexical rows.
- [ ] **Step 5: Implement minimal synchronization hook/boundary** so vector and lexical projections are both derived from the same accepted chunk plan; preserve retry/idempotency semantics from M09.
- [ ] **Step 6: Verify GREEN** across migration, integration, static analysis, and complete CI.
- [ ] **Step 7: Commit** `feat: persist searchable chunk projection` and record evidence.

### Task 4: Lexical/exact retriever

**Files:**
- Create: `src/Retrieval/Lexical/LexicalRetriever.php`
- Create: `src/Retrieval/Lexical/LexicalScorer.php`
- Test: `tests/php/Unit/Retrieval/Lexical/LexicalScorerTest.php`
- Test: `tests/php/Integration/Retrieval/LexicalRetrieverTest.php`

**Interfaces:**
- Consumes: `RetrievalQuery`, trusted lexical filters, `ChunkSearchStore`, config bounds.
- Produces: ranked `RankedCandidate` list for channel `lexical` with native deterministic score and safe chunk content/lineage.

- [ ] **Step 1: Write RED tests** with fixtures where exact phrase and exact identifier/SKU outrank generic overlap, title boosts are bounded, restricted rows never appear, and candidate/result caps are enforced.
- [ ] **Step 2: Verify focused RED CI**.
- [ ] **Step 3: Implement scoring** with strongest exact full-query boost, strong exact token/identifier boost, bounded title boost, normalized term coverage, score-desc + chunk-ID tie-break.
- [ ] **Step 4: Verify focused/full GREEN CI**.
- [ ] **Step 5: Commit** `feat: add bounded lexical retrieval` and record evidence.

### Task 5: Semantic retriever over M08 contracts

**Files:**
- Create: `src/Retrieval/Semantic/SemanticRetriever.php`
- Create: `src/Retrieval/Semantic/SemanticRetrievalContext.php`
- Create: `src/Retrieval/Filter/RetrievalFilter.php`
- Create: `src/Retrieval/Filter/VectorFilterMapper.php`
- Test: `tests/php/Unit/Retrieval/Semantic/SemanticRetrieverTest.php`
- Test: `tests/php/Unit/Retrieval/Filter/VectorFilterMapperTest.php`

**Interfaces:**
- Consumes: `EmbeddingService`, `EmbeddingProfile`, `VectorCollection`, `VectorSearchStore`, `RetrievalQuery`, trusted filter, semantic top-K.
- Produces: ranked channel `semantic` candidates carrying safe lineage from required vector metadata.

- [ ] **Step 1: Write RED tests** using fake embedding/vector services for one-query embedding, bounded top-K, portable filter mapping, missing lineage drop, deterministic rank tie-break, and unsupported mandatory filter failure-before-search.
- [ ] **Step 2: Verify focused RED CI**.
- [ ] **Step 3: Implement retriever/filter mapper** reusing M08 contracts. Require `chunk_id`, `document_id`, `source_id`, and `visibility`; never infer authorization from query/content.
- [ ] **Step 4: Verify focused/full GREEN CI**.
- [ ] **Step 5: Commit** `feat: add semantic retrieval adapter` and record evidence.

### Task 6: Hybrid orchestrator and fail-closed access guard

**Files:**
- Create: `src/Retrieval/Access/CandidateAccessPolicy.php`
- Create: `src/Retrieval/Access/DefaultCandidateAccessPolicy.php`
- Create: `src/Retrieval/HybridRetriever.php`
- Create: `src/Retrieval/RetrievalException.php`
- Test: `tests/php/Unit/Retrieval/Access/DefaultCandidateAccessPolicyTest.php`
- Test: `tests/php/Unit/Retrieval/HybridRetrieverTest.php`

**Interfaces:**
- Consumes: semantic/lexical retrievers, `ReciprocalRankFusion`, confidence estimator, trusted policy/config.
- Produces: bounded `RetrievalResult` before optional reranking/final context selection.

- [ ] **Step 1: Write RED tests** proving both channels are fused, duplicate chunks collapse, fail-closed post-fusion access removes disallowed/malformed candidates, explicit single-channel degradation works only when enabled, both-channel failure fails, and trace reasons are sanitized.
- [ ] **Step 2: Verify focused RED CI**.
- [ ] **Step 3: Implement orchestrator/access guard**. Apply guard after fusion regardless of store-side filters and before any reranker.
- [ ] **Step 4: Verify focused/full GREEN CI**.
- [ ] **Step 5: Commit** `feat: orchestrate fail-closed hybrid retrieval` and record evidence.

### Task 7: Optional reranking and bounded context selection

**Files:**
- Create: `src/Retrieval/Rerank/Reranker.php`
- Create: `src/Retrieval/Rerank/RerankRequest.php`
- Create: `src/Retrieval/Rerank/RerankResult.php`
- Create: `src/Retrieval/Rerank/NoOpReranker.php`
- Create: `src/Retrieval/Rerank/LexicalOverlapReranker.php`
- Modify: `src/Retrieval/HybridRetriever.php`
- Test: `tests/php/Unit/Retrieval/Rerank/RerankerContractTest.php`
- Test: `tests/php/Unit/Retrieval/Rerank/LexicalOverlapRerankerTest.php`
- Modify: `tests/php/Unit/Retrieval/HybridRetrieverTest.php`

**Interfaces:**
- Reranker input is only access-approved top-N candidates.
- Reranker output must reference only supplied IDs with finite scores; injection/unknown IDs fail validation.
- Hybrid result returns at most configured final-context count.

- [ ] **Step 1: Write RED contract tests** for no-op stability, deterministic local rerank, top-N cap, unknown-ID rejection, score validation, and configured reranker-failure fallback to fused order.
- [ ] **Step 2: Verify focused RED CI**.
- [ ] **Step 3: Implement reranking contracts/adapters** and integrate after access guard. Reranker cannot modify candidate content/lineage/visibility.
- [ ] **Step 4: Verify focused/full GREEN CI**.
- [ ] **Step 5: Commit** `feat: add optional bounded reranking` and record evidence.

### Task 8: End-to-end fixture, documentation, security/performance closeout

**Files:**
- Create/modify: integration fixture(s) under `tests/php/Integration/Retrieval/` using real local chunk-search/vector-store paths with fake deterministic embeddings.
- Modify: `docs/milestones/M10-hybrid-retrieval-reranking.md`
- Modify: `docs/progress/STATUS.md`
- Modify: `docs/ARCHITECTURE.md` only for finalized M10 facts not already documented.
- Modify: `docs/FEATURE-MATRIX.md`
- Create: `docs/progress/M10-CLOSEOUT.md`

**Interfaces:**
- End-to-end local path must retrieve an exact SKU/identifier through lexical evidence and a paraphrase through semantic evidence, fuse deterministically, and never return a restricted chunk to a public policy.

- [ ] **Step 1: Write RED end-to-end acceptance fixture** for exact SKU, paraphrase, hybrid ordering, restricted visibility, trace determinism, and hard result bounds.
- [ ] **Step 2: Verify RED if any acceptance wiring is missing; implement only missing composition/wiring required for the fixture.**
- [ ] **Step 3: Verify GREEN** with focused PHPUnit then complete CI; record exact test/assertion counts and artifact digest.
- [ ] **Step 4: Perform security/performance review** for SQL preparation, access fail-closed semantics, query abuse bounds, trace redaction, no untrusted authorization, candidate ceilings, and external-call bounds. Fix Critical/Important findings with fresh RED/GREEN evidence where behavior changes.
- [ ] **Step 5: Update durable milestone/progress/architecture/feature docs** with exact SHAs/runs and no premature completion claim.
- [ ] **Step 6: Request independent code review** on the PR and resolve every Critical/Important finding; rerun exact-head CI after fixes.
- [ ] **Step 7: Verify exact PR-head SHA CI GREEN**, merge only with expected-head protection, then verify fresh `main` CI GREEN on the merge SHA.
- [ ] **Step 8: Finalize M10 closeout** and set the exact next milestone/action only after verified post-merge main.

## Self-review

- **Spec coverage:** Tasks 1-8 cover preprocessing, contracts, lexical projection, lexical and semantic retrieval, RRF fusion, filters/access, confidence, traces, optional reranking, exact/paraphrase fixtures, security/performance, docs/review/CI/merge.
- **Placeholder scan:** no TODO/TBD/"similar to" placeholder remains; each implementation task has explicit behavior and RED/GREEN commands/gates.
- **Type consistency:** later tasks consume the exact contract names introduced in earlier tasks; channel candidate flow is `RankedCandidate` -> fused candidate/evidence -> access-approved `RetrievalCandidate` -> reranker -> `RetrievalResult`.
- **Scope:** answer generation/citations/evaluation UI remain outside M10.
- **Execution mode:** repository ADR-017 requires inline execution fallback in this runtime; use `superpowers:executing-plans` task-by-task.

**Approved outcome:** This implementation plan is **AUTO-APPROVED — SCHEDULED MODE** and ready for strict TDD execution.