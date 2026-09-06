# M10 — Hybrid Retrieval Closeout

Status: **COMPLETE / MERGED / POST-MERGE CI GREEN**

Milestone: M10 — Semantic + Keyword + Hybrid Retrieval, Filters & Reranking.

Design/spec: `docs/superpowers/specs/2026-09-05-m10-hybrid-retrieval-reranking-design.md` — **AUTO-APPROVED — SCHEDULED MODE**.

Implementation plan: `docs/superpowers/plans/2026-09-05-m10-hybrid-retrieval-reranking.md` — **AUTO-APPROVED — SCHEDULED MODE**.

## Delivered scope

M10 provides:

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

Task 8 added `tests/Integration/Retrieval/HybridRetrievalAcceptanceTest.php` and composes the actual M10 adapters rather than replacing orchestration with a fake:

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
- Exact acceptance head `19ab7eea5262369e8ced90238605a354e87ba6f6`, CI `33999864905`: all permanent jobs GREEN; PHPStan **0 errors**; PHPUnit **552/552 tests, 2,230 assertions**; Composer audit **no security vulnerability advisories**; WordPress activation/database/providers/knowledge/file-ingestion/WooCommerce smoke checks all passed.
- Acceptance artifact: `9979163515`, 830,993 bytes, digest `sha256:c9bd54c124bedaaa99c421e6a292a0ed0397a6a92317237ae677b25bc9a645c5`.
- The acceptance fixture passed against existing M10 production composition. No missing production wiring existed, and no artificial production failure/change was manufactured solely to create a RED.

## Security and performance review

Final scoped Task 8 review `5123489610` reviewed:

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

Review result: **0 Critical / 0 Important**. PR #15 had zero unresolved review threads at merge time.

## Final pre-merge verification

Final PR head: `83bad6f16e45bd67f9658487ab5b030064d00da2`.

Exact-head CI: `34000059826` — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all GREEN.

Artifact: `9979220629`, 830,998 bytes, digest `sha256:c7b0e16edc315f1aaf02d378bdc5235a1fb77dd0d2327546422d0e3c7eff027c`.

## Merge and post-merge verification

PR #15 was marked ready only after exact-head GREEN CI and merged with expected-head protection against `83bad6f16e45bd67f9658487ab5b030064d00da2`.

Merge SHA: `4c1f54e667b36c6c8ec09b1dffc81fb20c2034de`.

Fresh push-triggered `main` CI: `34000242280` on the exact merge SHA — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all GREEN.

Post-merge artifact: `9979266115`, 830,989 bytes, digest `sha256:d4674298b858b70de5181883f824974ababf3580fc990b0b15cfd67452db7c66`.

## Earlier task evidence

Tasks 1–7 are **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED**. Their strict RED/GREEN SHAs, CI runs, findings, fixes, and exact verification are recorded in:

- `docs/progress/M10-PROGRESS.md`;
- `docs/progress/M10-TASK6-CLOSEOUT.md`;
- `docs/progress/M10-TASK7-CLOSEOUT.md`.

## Final state

M10 is **COMPLETE** on `main`. All milestone implementation, acceptance, security/performance review, pre-merge verification, protected merge, and post-merge verification gates are satisfied.

## Exact next unfinished action

Recover **M11 — RAG Chat Orchestration** from its milestone/roadmap documentation and repository state. If M11 is architectural under repository rules, perform the Scheduled Mode design/spec/plan sequence first with auto-approval and durable docs; only then begin the first implementation task with a test-only RED commit.
