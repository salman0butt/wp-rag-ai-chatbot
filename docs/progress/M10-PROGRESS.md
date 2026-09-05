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

## Task 3 — Durable chunk-search projection and synchronization contract

Status: **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED**.

Delivered:

- V006 searchable chunk-projection migration and schema/bootstrap/table registration;
- immutable bounded lexical projection records, filters, search requests, and matches;
- prepared-SQL `WpdbChunkSearchStore` with collection/document/source/language/visibility scoping and hard candidate ceilings;
- idempotent document replacement/deletion with real WordPress smoke assertions for stale-row removal and cross-collection isolation;
- bounded portable scalar metadata projection only;
- accepted M07/M08 plan synchronization so the lexical projection is derived from the same canonical upsert/metadata-refresh/unchanged chunk set as primary index execution;
- retry-safe ordering where primary index work executes before lexical projection persistence;
- safe retryable queue translation for local projection persistence failures.

### Verification / review evidence

- Exact Task 3 implementation head: `b76024065022b60a91e3b3be562006f70f31248c`.
- Exact-head CI: `33979758980` — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed.
- Scoped review on the exact head covered migration/registration, SQL preparation and filter bounds, candidate ceilings, accepted-plan lineage synchronization, retry behavior, and real WordPress projection smoke coverage.
- Review result: **0 Critical / 0 Important**; PR #15 has no blocking review threads.

## Task 4 — Lexical/exact retriever

Status: **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED**.

Delivered:

- deterministic `LexicalScorer` with strongest exact full-query evidence, strong identifier-like token evidence, normalized term coverage, and a bounded title boost;
- identifier-friendly ranking for terms such as `SKU-42/A` without requiring fuzzy matching or external calls;
- bounded `LexicalRetriever` that forwards trusted lexical scope to the prepared store, consumes at most `lexical_candidate_limit` returned rows, rechecks available document/source/language/visibility lineage fail-closed, sorts native scores descending with stable chunk-ID tie-breaks, and returns at most `fused_candidate_limit` candidates;
- defensive scope/candidate-cap enforcement even if a store implementation returns excess or mismatched rows; collection scope remains store-enforced because `ChunkSearchRecord` intentionally does not duplicate `collection_id`;
- deterministic equivalent-evidence scoring so downstream tie-breaking remains stable.

### Strict TDD evidence

Primary Task 4 cycle:

- Test-only commits: `bb31147bf10ea75b4d8c6cba4a5dd515138679f3` and `2842f3611fa2eb33c5e348752a8d62b462c9c775` defined lexical scoring and retriever behavior. Early CI was stopped by test-file coding standards and is not claimed as behavioral RED.
- RED cleanup commits: `0ecd4cbeaa9425108eebcc9b77c01119f1e22ffe`, `8b75dd1ecefc571f56d03824a50ac56c8dbe30c4`, and `c0a6184f3b06fa0db7547fb62384403bc4a9d2a0` changed only test formatting/documentation.
- Valid RED: `c0a6184f3b06fa0db7547fb62384403bc4a9d2a0`, CI `33982304341` — PHPCS/PHPStan passed, then PHPUnit ran 527 tests / 2,140 assertions and produced exactly five expected missing-class errors for `LexicalScorer` and `LexicalRetriever`.
- Implementation: `b333ea40cd404769e10cdefad6a42e609f3a43ba` (`LexicalScorer`) and `f8d316502d3929355270edf3ef16949dad6026c5` (`LexicalRetriever`).
- `1af06f5a5adde9b6285216a86e3653090dec3b35` fixed production doc alignment; CI `33982448979` then reached PHPUnit and exposed one test-fixture error in the expected SHA-256 chunk-ID ordering, not a production ordering defect.
- `bf3004effb4145cd3a8f0558c007e6aa22e9129b` corrected that expectation to the actual stable chunk-ID ordering; `php-quality`, `js-quality`, and `package` passed on CI `33982515681` before later review work superseded that head.

Independent-review fail-closed fix cycle:

- Scoped review found **0 Critical / 1 Important**: the initial retriever relied entirely on `ChunkSearchStore` to honor scope and returned-row candidate count, while Task 4 requires restricted rows never appear and hard candidate ceilings be enforced.
- Regression test commit `b4b7a4bf2233f6995ed6fca2e4e4c0537d2803a5` was followed by lint-only cleanup `3085d4239146d8faa573d87d7551c07bc6524827`.
- Valid RED: `3085d4239146d8faa573d87d7551c07bc6524827`, CI `33982751932` — PHPStan passed, then PHPUnit ran 528 tests / 2,153 assertions and failed exactly one regression because two candidates escaped where only one in-scope, within-cap candidate was permitted.
- Fix: `d0728630c56ecd9954cd991ef58fdf832d8514bb` enforces the returned-row hard cap before scoring and rechecks document/source/language/visibility scope; `be72422d848dd027d9e9c260cb26bd9f32909ef2` is doc-only coding-standard cleanup.
- GREEN: `be72422d848dd027d9e9c260cb26bd9f32909ef2`, CI `33982906694` — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed; PHPStan reported no errors, PHPUnit passed 528 tests / 2,155 assertions, and Composer audit reported no security advisories.
- Independent re-review on the GREEN exact head: **0 unresolved Critical / 0 unresolved Important**; PR #15 has no blocking review threads.

## Review / integration state

Tasks 1, 2, 3, and 4 are complete, GREEN, and independently reviewed. M10 remains intentionally draft and is not merge-ready because Tasks 5–8 are unfinished.

## Exact next unfinished action

Begin Task 5 — semantic retriever over M08 contracts — by recovering the accepted `EmbeddingService`, `EmbeddingProfile`, `VectorCollection`, and `VectorSearchStore` interfaces plus required vector metadata lineage, then write test-only RED coverage for one-query embedding, bounded semantic top-K, portable trusted filter mapping, missing-lineage drop, deterministic rank tie-break, and unsupported mandatory-filter failure-before-search. Do not add `SemanticRetriever`, `SemanticRetrievalContext`, `RetrievalFilter`, or `VectorFilterMapper` production classes until exact-head CI proves the expected RED.