# M10 — Hybrid Retrieval Closeout

Status: **PRE-MERGE VERIFIED / MERGE PENDING**

Milestone: M10 — Semantic + Keyword + Hybrid Retrieval, Filters & Reranking.

Design/spec: `docs/superpowers/specs/2026-09-05-m10-hybrid-retrieval-reranking-design.md` — **AUTO-APPROVED — SCHEDULED MODE**.

Implementation plan: `docs/superpowers/plans/2026-09-05-m10-hybrid-retrieval-reranking.md` — **AUTO-APPROVED — SCHEDULED MODE**.

This closeout records the pre-merge M10 state only. It does not claim M10 merged or complete on `main`; those claims require expected-head merge protection plus a fresh GREEN post-merge `main` CI run.

## Delivered scope

M10 now provides:

- bounded UTF-8 query preprocessing with identifier-preserving lexical terms;
- immutable validated retrieval configuration, candidate, evidence, result, confidence, and safe trace contracts;
- deterministic weighted Reciprocal Rank Fusion with stable tie-breaking and duplicate collapse;
- durable local chunk-search projection synchronized from the accepted M07/M08 indexing plan;
- prepared and bounded lexical/exact retrieval preserving identifiers such as SKUs/model numbers/error codes;
- one-query bounded semantic retrieval over accepted embedding/vector-store contracts;
- portable trusted filter mapping plus canonical lineage hydration/revalidation;
- fail-closed post-fusion access checking;
- explicit opt-in single-channel degradation and normalized failure reasons;
- deterministic bounded confidence;
- optional bounded reranking after access approval only, with lineage preservation and fail-closed output validation;
- hard semantic, lexical, fusion, rerank, and final-context candidate ceilings;
- safe diagnostic traces containing query hash/byte length and normalized channel/rerank status rather than raw query/provider error bodies.

## Task 8 acceptance fixture

Task 8 adds `tests/Integration/Retrieval/HybridRetrievalAcceptanceTest.php` and composes the actual M10 adapters rather than replacing orchestration with a fake:

- real `QueryPreprocessor`;
- real `LexicalRetriever` + `LexicalScorer`;
- real `SemanticRetriever` with deterministic fake embedding/vector dependencies;
- real `ReciprocalRankFusion`;
- real `DefaultCandidateAccessPolicy`;
- real `ConfidenceEstimator`;
- real `HybridRetriever`.

The fixture proves:

- `SKU-42/A` exact identifier evidence survives the lexical channel;
- a paraphrase candidate survives the semantic channel;
- hybrid evidence is fused deterministically;
- a higher-scoring private/restricted candidate cannot escape a trusted public policy;
- trace hash/length/failure/rerank fields remain deterministic and sanitized;
- final candidates respect `context_candidate_limit`.

## Task 8 TDD / acceptance evidence

- Test-only acceptance commit `1a2c1ace58b270a5ef83d126533771578c75c438`, CI `33999819218`, was stopped by PHPCS before PHPUnit due only to test documentation/type-comment violations. It is **not** counted as behavioral RED.
- Test-only standards correction `19ab7eea5262369e8ced90238605a354e87ba6f6` changed fixture documentation/type annotations only.
- On exact head `19ab7eea5262369e8ced90238605a354e87ba6f6`, `php-quality` reached the full behavior suite: PHPStan **0 errors**; PHPUnit **552/552 tests, 2,230 assertions**; Composer audit **no security vulnerability advisories**.
- `js-quality` and `package` also passed on CI `33999864905`.
- The WordPress smoke suite completed activation, database, providers, knowledge, file-ingestion, and WooCommerce-knowledge assertions successfully before cleanup; final workflow conclusion is still a required merge gate and must be rechecked before merge.
- The acceptance fixture passed against existing M10 production composition. Therefore no missing production wiring existed, and no artificial production failure/change was manufactured solely to create a RED.

## Security and performance review

Final scoped Task 8 review `5123489610` on implementation/acceptance head `19ab7eea5262369e8ced90238605a354e87ba6f6` reviewed:

- prepared lexical SQL and identifier-safe table-name preparation;
- trusted collection/document/source/language/visibility constraints;
- query UTF-8, byte, token, lexical-term, and per-term bounds;
- lexical SQL candidate ceilings;
- one-query embedding and bounded semantic top-K;
- vector metadata/canonical lineage revalidation;
- trusted scope recheck after semantic search and again after fusion;
- fail-closed mandatory-filter handling;
- deterministic bounded RRF and confidence;
- explicit single-channel degradation policy;
- bounded post-access rerank input and final context output;
- reranker finite-score / unknown-ID validation;
- sanitized trace/failure diagnostics with no raw query/provider exception bodies;
- no mandatory paid/external provider calls in normal CI.

Review result: **0 Critical / 0 Important**. No production behavior fix was requested by the final review.

## Earlier task evidence

Tasks 1–7 are **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED**. Their strict RED/GREEN SHAs, CI runs, findings, fixes, and exact verification are recorded in:

- `docs/progress/M10-PROGRESS.md`;
- `docs/progress/M10-TASK6-CLOSEOUT.md`;
- `docs/progress/M10-TASK7-CLOSEOUT.md`.

## Merge state

PR #15 remains intentionally unmerged at this pre-merge closeout point.

Required remaining gates:

1. verify the final documentation PR-head SHA with all permanent CI jobs GREEN;
2. confirm no unresolved Critical/Important review finding/thread exists;
3. mark PR #15 ready only after those gates pass;
4. merge with expected-head protection;
5. verify a fresh GREEN `main` CI run on the merge SHA;
6. finalize global status/feature/milestone closeout on `main` with exact merge/post-merge evidence;
7. only then declare M10 complete and identify the exact M11 starting action.

## Exact next unfinished action

Verify exact-head CI on the final M10 documentation head. If all permanent jobs are GREEN and no blocking review finding exists, make PR #15 merge-ready and merge only with expected-head protection. Then verify fresh post-merge `main` CI on the merge SHA before changing M10 status to COMPLETE or starting M11.
