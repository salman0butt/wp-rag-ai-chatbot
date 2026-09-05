# M10 Task 7 — Optional Reranking Closeout

Status: **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED**

Milestone: M10 — Semantic + Keyword + Hybrid Retrieval, Filters & Reranking.

This closeout supplements `docs/progress/M10-PROGRESS.md` and `docs/progress/M10-TASK6-CLOSEOUT.md`. Tasks 1–7 are now complete; Task 8 remains unfinished, so PR #15 stays draft and M10 is not merge-ready.

## Delivered behavior

- explicit `Reranker`, `RerankRequest`, and `RerankResult` contracts for optional post-filter reranking;
- runtime validation that reranker requests contain only unique `RetrievalCandidate` members and results contain only non-empty supplied candidate IDs with finite scores;
- `NoOpReranker` preserving existing fused ordering/scores when reranking is intentionally neutral;
- deterministic local `LexicalOverlapReranker` using normalized query-term coverage with supplied-order tie stability and no external calls;
- `HybridRetriever` invokes the reranker only after fail-closed post-fusion access filtering and only for the configured bounded `rerank_top_n` prefix;
- reranked candidates preserve canonical chunk/document/source/content/language/visibility lineage, existing channel evidence, fused score, and deterministic confidence while adding only an optional finite rerank score;
- unknown candidate IDs from a reranker are rejected so reranking cannot inject unauthorized candidates;
- configurable reranker-failure behavior: deterministic fallback to the already access-approved fused order when enabled, otherwise a normalized `RetrievalException` with no provider/runtime error leakage;
- deterministic rerank ordering by score descending with original fused rank as the tie-break, while the unreranked tail remains in its existing fused order;
- final results remain bounded by `context_candidate_limit` after reranking;
- safe `RetrievalTrace` rerank status is limited to normalized `disabled`, `applied`, or `fallback_unavailable` codes.

## Strict TDD evidence

### Reranking contract / integration cycle

- Initial test-only commit `ed378dffbb4b1c3b600b3249cf74aa2b6e17f8ca`, CI `33997105750`, was stopped by PHPCS before PHPUnit and is **not** counted as behavioral RED.
- Test-only cleanup `dd52d8a3d9ebf836ac0524b02cf3c5703250210c` changed only test formatting/documentation so the intended behavior could reach PHPUnit.
- Valid RED: `dd52d8a3d9ebf836ac0524b02cf3c5703250210c`, CI `33997158050` — PHPStan passed, then PHPUnit reached 551 tests / 2,204 assertions and produced exactly six errors plus three failures because the Task 7 reranking contracts/adapters did not yet exist.
- Implementation: `22ccbb06a43e7f43a07df35f0301005b70d07e7a` added the optional reranking contracts/adapters and hybrid integration.
- CI `33997309925` on `22ccbb06a43e7f43a07df35f0301005b70d07e7a` exposed only repository coding-standard/documentation defects in `HybridRetriever.php`; it is not claimed as GREEN.
- Root-cause/style correction: `54636cfca990b9e885dd3e2a37e7316346275a65` aligned the production documentation/assignment formatting without changing behavior.
- GREEN implementation verification: `54636cfca990b9e885dd3e2a37e7316346275a65`, CI `33997363175` — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed; PHPStan reported no errors; PHPUnit passed 551 tests / 2,216 assertions; Composer audit reported no security advisories.
- Exact implementation artifact: `9978463764`, 830,997 bytes, digest `sha256:cf9212e4f1fcb58b239f45ab91416ba579f705815ea4b59fae803e97d7084597`.

## Independent review

Scoped Task 7 review on exact implementation head `54636cfca990b9e885dd3e2a37e7316346275a65` covered:

- request/result runtime validation;
- access-filter-before-rerank ordering;
- rerank top-N and final-context hard ceilings;
- preservation of trusted candidate lineage/evidence/confidence;
- finite-score and unknown-ID injection rejection;
- deterministic local rerank/tie behavior;
- configurable fail-closed versus fused-order fallback behavior;
- normalized rerank trace diagnostics with no raw exception leakage.

Result: **0 Critical / 0 Important**. The scoped review was recorded on PR #15 as review `5123317835`; no blocking inline review thread remains.

## Merge state

Task 7 is complete, but M10 remains intentionally unmerged. Task 8 must be genuinely completed, reviewed, documented, and exact-SHA GREEN before PR #15 can be made merge-ready.

## Exact next unfinished action

Begin **Task 8 — End-to-end fixture, documentation, security/performance closeout** with a test-only acceptance RED. Prove the real local retrieval path retrieves an exact SKU/identifier through lexical evidence and a paraphrase through semantic evidence, fuses deterministically, excludes restricted visibility under a public policy, emits deterministic safe traces, and enforces hard result bounds. Implement only missing composition/wiring after that RED, then complete the security/performance review, durable milestone documentation, independent PR review, exact-head CI, expected-head merge, and fresh post-merge `main` CI before declaring M10 complete.
