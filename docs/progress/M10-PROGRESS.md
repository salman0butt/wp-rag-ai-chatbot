# M10 — Hybrid Retrieval Progress

Status: **IN PROGRESS**

Milestone: M10 — Semantic + Keyword + Hybrid Retrieval, Filters & Reranking.

Design/spec: `docs/superpowers/specs/2026-09-05-m10-hybrid-retrieval-reranking-design.md` — **AUTO-APPROVED — SCHEDULED MODE**.

Implementation plan: `docs/superpowers/plans/2026-09-05-m10-hybrid-retrieval-reranking.md` — **AUTO-APPROVED — SCHEDULED MODE**.

Selected architecture: bounded semantic + lexical retrieval fused by deterministic weighted Reciprocal Rank Fusion, with trusted fail-closed access filtering, a durable local lexical chunk-search projection, safe traces, and optional post-filter reranking.

## Task 1 — Retrieval query and result contracts

Status: **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED**.

Delivered:

- bounded immutable `RetrievalConfig`;
- deterministic `QueryPreprocessor` and `RetrievalQuery`;
- whitespace normalization, empty/invalid/oversized query rejection, and identifier-friendly lexical terms such as `SKU-42/A`, `ERR_CONNECTION_RESET`, and `model.x:7`;
- immutable `ChannelEvidence`, `RetrievalCandidate`, `RetrievalTrace`, and `RetrievalResult` contracts;
- trace diagnostics store query hash/length rather than raw query text by default;
- finite score/rank/lineage validation at the contract boundaries;
- runtime validation that `RetrievalCandidate` contains only `ChannelEvidence` members and `RetrievalResult` contains only `RetrievalCandidate` members.

### Strict TDD evidence

Query preprocessing cycle:

- RED: `f174c9a0304fa1f78e34c29434b63380c83a35bf`, CI `33964465323` — test-only commit failed because the M10 retrieval classes did not exist.
- Initial implementation head `6dc831a6c5a8d5eb88ab3652134dd6ee9a25570e`, CI `33964514214` exposed coding-standard defects; these were debugged rather than treated as GREEN.
- GREEN: `3e6a0532476464b2888ba7dcbc4c7586756e6b38`, CI `33964682166` — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed.

Result-contract cycle:

- `f1e305cd118768fa4506dc6795fbe94a00db4eea`, CI `33964805508` was an invalid behavioral RED because PHPCS stopped before PHPUnit; only test formatting was changed.
- Valid RED: `bdcda050717c4952a64de90b57abe65aae0d067f`, CI `33964867925` — PHPCS/PHPStan passed, then PHPUnit reached 501 tests / 2,067 assertions and failed with exactly three missing-class errors plus one expected-exception mismatch caused by missing `ChannelEvidence`/`RetrievalTrace` contracts.
- Implementation candidates `a02a1ae7eedd917672c757a1282aec33a4796e3b` and `d00b93af999f78e2d76a4cc0fe9410aee92cd998` exposed PHPCS/PHPStan defects and are not claimed as GREEN.
- GREEN: `3ad17cd996c0eedbb07dbbc97a6629956c60a93b`, CI `33965091221` — all four permanent jobs passed.

Independent-review fix cycle:

- Independent review on `889f100a790618310ef032f4cf5481ea4597fa59`: **0 Critical / 1 Important**. The Important finding was that PHPStan list annotations did not enforce runtime member types for candidate channel evidence or result candidates.
- RED: `b51c5f04baf76c6c1ef6aacf8da968af696e7b1d`, CI `33965616503` — PHPStan completed cleanly, then PHPUnit ran 503 tests / 2,080 assertions and failed exactly two regression tests because malformed array members were accepted.
- Implementation/debug candidates `4a6405530cd69cbb1c6763a0da6ccc2a11a6c610`, `cf1a4473103a6e539a0530dd99d6a3ffbce91125`, `cfdb87570eec92a59df8bbc2eedbcff6adf1d7c0`, `a5d87dae2f2971d651f8ba9819b8b8704f7808d8`, `5113ec8ba9aa1760e224c849f725800f4d2668b6`, `3e9269d440cc51095e0d0dc2873cfbbab769ec6d`, `91582d7db36b27e9785eff1d514e7e35a85ef7bf`, `1372472b354eee6f5ffe302f31a7475d45d7796c`, and `0b17c3031080f2d57f784b49eb3ba16923e521c0` exposed or corrected static-analysis/coding-standard modeling details and are not claimed as GREEN.
- GREEN: `0db18b01a027936212477bd69ef7dfd1c845f073`, CI `33966059170` — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed.
- Scoped re-review on the GREEN head: **0 unresolved Critical / 0 unresolved Important**.

## Review / integration state

Task 1 is complete and independently reviewed. M10 remains intentionally draft and is not merge-ready because Tasks 2–8 are unfinished.

## Exact next unfinished action

Begin Task 2 — deterministic weighted Reciprocal Rank Fusion and confidence — by writing and pushing test-only RED coverage for RRF contribution math, cross-channel deduplication/agreement boost, configured weights, invalid numeric inputs, result caps, deterministic tie-breaking, and confidence classification. Do not write Task 2 production code until the RED is verified in CI.
