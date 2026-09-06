# M10 — Semantic + Keyword + Hybrid Retrieval, Filters & Reranking

Status: **COMPLETE / MERGED / POST-MERGE CI GREEN**

## Goal

Build explainable production retrieval that combines semantic and exact/lexical signals with trusted filters, deterministic fusion, bounded context selection, safe diagnostics, and optional reranking.

## Dependencies

Completed M07 chunk/indexing contracts, M08 embedding/vector contracts, and M09 durable synchronization.

## Architecture

The finalized M10 architecture is:

`bounded query preprocessing -> semantic + lexical/exact channels -> deterministic weighted RRF -> fail-closed trusted access recheck -> optional bounded rerank -> bounded context candidates + safe trace`

A durable local chunk-search projection is synchronized from the accepted indexing plan. Semantic retrieval hydrates vector matches back through canonical local chunk lineage before candidate admission. Retrieved content and metadata remain untrusted data and never become authorization rules.

## Implemented scope

- identifier-preserving query preprocessing and immutable retrieval contracts;
- deterministic weighted Reciprocal Rank Fusion and confidence;
- V006 local chunk-search projection with prepared bounded SQL;
- synchronized vector + lexical projections from accepted indexing plans;
- exact/lexical retrieval for phrases, SKUs/model identifiers, and general terms;
- bounded semantic retrieval over M08 embedding/vector contracts;
- portable trusted filtering and fail-closed post-search/post-fusion access checks;
- explicit controlled single-channel degradation;
- normalized safe retrieval traces;
- optional bounded post-access reranking with injection/score validation;
- final hard context-candidate bounds.

## Acceptance criteria

Task 8 end-to-end acceptance fixture `tests/Integration/Retrieval/HybridRetrievalAcceptanceTest.php` verifies that:

- an exact `SKU-42/A` fixture is retrievable through lexical evidence;
- a paraphrase fixture is retrievable through semantic evidence;
- hybrid evidence fuses deterministically;
- restricted/private content does not escape a trusted public policy;
- traces are deterministic and omit raw query/provider error content;
- final results obey configured hard bounds.

## TDD evidence

Tasks 1–7 retain their exact strict RED/GREEN evidence in `docs/progress/M10-PROGRESS.md`, `docs/progress/M10-TASK6-CLOSEOUT.md`, and `docs/progress/M10-TASK7-CLOSEOUT.md`.

Task 8 acceptance sequence:

- `1a2c1ace58b270a5ef83d126533771578c75c438` / CI `33999819218`: test-only acceptance fixture was stopped by PHPCS before PHPUnit due only to test documentation/type-comment defects, so this is **not** counted as behavioral RED.
- `19ab7eea5262369e8ced90238605a354e87ba6f6` / CI `33999864905`: all permanent jobs GREEN; PHPStan 0 errors; PHPUnit 552/552 tests with 2,230 assertions; Composer audit clean; WordPress smoke GREEN.
- Existing production composition already satisfied the Task 8 acceptance fixture. No unnecessary production wiring was added and no artificial RED was manufactured.

## Integration verification

The acceptance fixture composes the real M10 `QueryPreprocessor`, lexical retriever/scorer, semantic retriever, fusion, confidence, access policy, and hybrid orchestrator while substituting only deterministic embedding/vector/store test dependencies where external I/O would otherwise occur.

Real WordPress lexical projection behavior is separately exercised by `scripts/test-wp-chunk-search.php` in the permanent smoke pipeline, including idempotent replacement, stale-row deletion, collection/source/language/visibility filtering, and candidate ceilings.

## Security review

Final Task 8 scoped review `5123489610` covered SQL preparation, trusted filter/access boundaries, query abuse bounds, vector/local lineage revalidation, post-fusion fail-closed access, candidate ceilings, reranker validation, trace redaction, controlled degradation, and external-call bounds.

Result: **0 Critical / 0 Important**.

## Performance review

M10 enforces hard bounds at each material work boundary:

- query bytes/tokens;
- lexical terms and SQL candidate count;
- semantic top-K;
- fused candidates;
- rerank top-N;
- final context candidates.

Lexical persistence/search uses indexed trusted scope fields and prepared SQL. Semantic retrieval performs one query embedding per request and bounded vector search. Normal CI uses deterministic fake provider/vector dependencies rather than paid external calls.

## Code review findings

Task-level independent review findings and RED/GREEN fixes are durably recorded in the M10 progress/Task 6/Task 7 closeouts. Final Task 8 review: **0 Critical / 0 Important**. No blocking review thread remained at merge.

## Merge verification

Final PR head: `83bad6f16e45bd67f9658487ab5b030064d00da2`.

Final pre-merge exact-head CI: `34000059826` — all four permanent jobs GREEN.

PR #15 merged with expected-head protection.

Merge SHA: `4c1f54e667b36c6c8ec09b1dffc81fb20c2034de`.

Fresh post-merge `main` CI: `34000242280` on the exact merge SHA — all four permanent jobs GREEN.

Post-merge package artifact: `9979266115`, digest `sha256:d4674298b858b70de5181883f824974ababf3580fc990b0b15cfd67452db7c66`.

## Known limitations

- M10 retrieves and ranks context candidates; final grounded answer generation and citation rendering belong to M11.
- Provider-specific or LLM-based rerank adapters may be added behind the completed bounded reranker contract in later provider milestones; M10 includes deterministic local/no-op adapters and safe orchestration.
- M13 owns retrieval-debugger/admin UI; M10 provides the safe trace data contract only.

## Documentation

Detailed evidence:

- `docs/progress/M10-PROGRESS.md`
- `docs/progress/M10-TASK6-CLOSEOUT.md`
- `docs/progress/M10-TASK7-CLOSEOUT.md`
- `docs/progress/M10-CLOSEOUT.md`
- M10 design/spec and implementation plan under `docs/superpowers/`.

## Completion checklist

- [x] Tasks 1–7 implementation and independent review gates.
- [x] Task 8 end-to-end acceptance fixture reaches real M10 adapters.
- [x] Final scoped security/performance review: 0 Critical / 0 Important.
- [x] Final documentation head exact-SHA CI GREEN.
- [x] PR #15 ready/merged with expected-head protection.
- [x] Fresh post-merge `main` CI GREEN on merge SHA.
- [x] Durable M10 closeout contains exact merge/post-merge evidence.

## Next milestone

M11 — RAG Chat Orchestration. Recover its milestone/roadmap state and perform any required Scheduled Mode design/spec/plan sequence before implementation.
