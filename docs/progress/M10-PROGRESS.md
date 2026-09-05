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

## Task 2 — Deterministic weighted reciprocal-rank fusion and confidence

Status: **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED**.

Delivered:

- immutable validated `RankedCandidate` pre-fusion lineage;
- deterministic weighted Reciprocal Rank Fusion using `weight / (rrf_k + rank)` without normalizing incomparable native channel scores;
- native channel ordering by score descending with stable chunk-ID tie-breaks;
- duplicate suppression within a channel and cross-channel deduplication by stable chunk ID;
- fail-fast validation when duplicate chunk IDs carry inconsistent document/source/content/language/visibility lineage;
- deterministic fused ordering by fused score descending, best native rank ascending, then chunk ID;
- configured fused candidate ceiling and semantic/lexical weights;
- canonical channel-evidence ordering independent of caller map order;
- bounded deterministic `RetrievalConfidence` and `ConfidenceEstimator` where only distinct retrieval channels count as agreement.

### Strict TDD evidence

Primary Task 2 cycle:

- RED: `b6f52b7d1bf639b7a650239b987d5166ecf3f39b`, CI `33966659129` — PHPStan passed, then PHPUnit ran 511 tests / 2,081 assertions and produced seven expected errors because `RankedCandidate`, `ReciprocalRankFusion`, `RetrievalConfidence`, and `ConfidenceEstimator` were not implemented. `js-quality`, `package`, and `wordpress-smoke` passed.
- Implementation commits: `ae9aae08b9b312fa2c3cdca4fa1ad64da9dab3f0`, `51500c8b56be48a0e8991d377b069d94293ffb92`, `e0eeda30d92c45c3a36a37366952db1a2bc91173`, and `9b3dd0d99ef1afc5664e643662b3aa138d8d6360`.
- Debug/style commits `253fa9961b53c37be565e810d650ae112ade19fc` and `d9802243d585884161c9d62afd0bca704f9bff52` corrected PHPCS documentation and made runtime member validation consistent with PHPStan rather than treating intermediate failures as GREEN.
- Initial GREEN: `d9802243d585884161c9d62afd0bca704f9bff52`, CI `33970190290` — all four permanent CI jobs passed; PHP verification reported 511 tests / 2,102 assertions.

Independent-review determinism fix:

- Review finding: **Important** — diagnostic `channel_evidence` ordering depended on caller associative-map order, violating deterministic trace semantics.
- `9177b189a51e7e44d7ba04ff8c2c311c4dc3782c` failed only PHPCS alignment and is not counted as behavioral RED.
- Valid RED: `729adb9959a1006e9bff8eca39b2b00909a98d49`, CI `33970388941` — lint/static analysis passed, then PHPUnit failed exactly the new determinism regression because reversed channel-map input returned `lexical,semantic` instead of the canonical evidence order.
- Fix: `fc5233e323ec51af54f83c2defc167285d08ee32` canonically sorts channel evidence; CI `33970444638` confirmed `php-quality`, `js-quality`, and `package` GREEN while the smoke job continued independently.

Independent-review confidence fix:

- Review finding: **Important** — confidence used evidence count rather than distinct channel count, so duplicate evidence from one channel falsely received the cross-channel agreement bonus.
- Valid RED: `743a1b3e6d07ff20390bd8a112b3ff0cbaf5273d`, CI `33970539244` — PHPCS/PHPStan passed, then PHPUnit ran 513 tests / 2,105 assertions and failed exactly one regression: same-channel duplicate evidence produced confidence `1.0` instead of `0.65`.
- Fix: `fa749830f6d113f00b461bc59b7f9b28fa146e08` counts distinct evidence channel names before applying the agreement bonus.
- GREEN verification: CI `33970605061` — PHPStan reported no errors; PHPUnit passed 513 tests / 2,106 assertions; Composer audit reported no vulnerability advisories. JavaScript and package jobs also passed while WordPress smoke completed independently.

### Independent re-review

- Scoped Task 2 diff from `b6f52b7d1bf639b7a650239b987d5166ecf3f39b` through `fa749830f6d113f00b461bc59b7f9b28fa146e08`: four planned production primitives plus focused fusion/confidence regression tests only.
- PR #15 review threads: none.
- Re-review result after both fixes: **0 unresolved Critical / 0 unresolved Important**.

## Review / integration state

Tasks 1 and 2 are complete and independently reviewed. M10 remains intentionally draft and is not merge-ready because Tasks 3–8 are unfinished.

## Exact next unfinished action

Begin Task 3 — durable chunk-search projection and synchronization contract — by recovering the V005 migration registry/composition and the concrete M08 accepted index-plan execution boundary, then write and push RED migration/store tests for idempotent replacement, deletion, trusted collection/document/source/language/visibility scope, bounded safe metadata, and hard candidate ceilings. Do not implement V006 or the chunk-search store until that RED is verified in WordPress integration CI.
