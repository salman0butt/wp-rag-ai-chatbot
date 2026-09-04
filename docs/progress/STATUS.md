# Global Status

- Completed milestones on `main`: **M00-M07**.
- Latest integrated milestone: **M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing — COMPLETE**.
- Current `main` SHA: `b642813c92ee152805c16a0bd6902b4ce67e33df`.
- M07 feature PR: **#9 — merged**.
- M07 durable closeout PR: **#10 — merged** on 2026-09-04.
- M07 closeout merge SHA: `b642813c92ee152805c16a0bd6902b4ce67e33df`.
- M07 post-closeout `main` CI: `33860207844` — all four permanent jobs passed.
- M07 post-closeout artifact: `9931964669`, digest `sha256:39b33e3716b4c99e0b8b3239ab2a92f95077476b97208ed8c50bc1dee73c8413`.
- Current milestone: **M08 — Embeddings & Vector Stores — IN PROGRESS**.
- M08 branch: `feat/m08-embeddings-vector-stores`.
- M08 PR: **#11 — open draft**.
- M08 design/spec and implementation plan: **AUTO-APPROVED — SCHEDULED MODE**.
- M08 Tasks 1-2: **COMPLETE on feature branch**; Task 3 is next.

## M07 final state — COMPLETE

M07 delivers the pure-PHP deterministic pipeline:

`DocumentRecord -> ContentNormalizer -> StructureAwareChunker -> ChunkDeduplicator -> IncrementalIndexPlanner -> DocumentIndexResult`

PR #10 reconciled the durable milestone ledger after PR #9's implementation merge. Exact PR #10 head `52daa443823b0e10c15cd813471222886dd2ef92` passed CI `33855408235`, received fresh review with Critical 0 / Important 0 / no blockers, and was merged with expected-head protection. Fresh `main` CI `33860207844` at `b642813c92ee152805c16a0bd6902b4ce67e33df` passed `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.

## M08 — IN PROGRESS

### Architecture gate

- Design/spec: `docs/superpowers/specs/2026-09-04-m08-embeddings-vector-stores-design.md`, commit `b63add146280f5770939f52c9806ef928c0d39ca` — **AUTO-APPROVED — SCHEDULED MODE** after self-review.
- Implementation plan: `docs/superpowers/plans/2026-09-04-m08-embeddings-vector-stores.md`, commit `6b3ae63ed84c1e0e81884d32ae6f3c1577c4bf0a` — **AUTO-APPROVED — SCHEDULED MODE**.
- Architecture uses capability-aware vector-store contracts, keeps provider HTTP/auth inside `Providers`, keeps generic embedding orchestration in `Embeddings`, and excludes M09 queue/retries and M10 hybrid retrieval.

### Task 1 — Embedding contracts and compatibility fingerprints — COMPLETE

Implemented optional provider embedding capability, embedding request/result/vector/usage value objects, embedding/index profiles, normalization/distance enums, deterministic versioned compatibility fingerprints, finite/list validation, unknown usage semantics, and registry ID consistency.

Genuine TDD evidence:

- Initial RED `17a77855d4634cb8f72d3327f442ef2cd0e76b3f` / CI `33861460302`: PHPStan 0 errors; PHPUnit 320 tests / 1,443 assertions with 7 missing-class errors + 2 expected-exception failures caused by absent M08 behavior.
- Initial GREEN `5b071adb9426fbca4b632d77a6c09196e01c5e6a` / CI `33862552126`: PHPStan 0 errors; PHPUnit 320/320, 1,463 assertions; Composer audit clean.
- Independent review found two Important issues: associative arrays violated ordered-list semantics, and control characters could make the line-delimited fingerprint payload ambiguous.
- Review RED `b5e282d5c8c228b871e04e0f7512484ae4d1e275` / CI `33862913794`: PHPStan 0 errors; PHPUnit 324 tests / 1,467 assertions / exactly 4 intended failures.
- Review GREEN `9e3b9c85351b383d246860c8f786a9e74ff1dda0` / CI `33863156655`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 324/324, 1,467 assertions; Composer audit clean; full WordPress smoke green.
- Review outcome after fixes: Critical 0; Important 0 unresolved.
- Package artifact `9933055991`, digest `sha256:8182986d6e45269b7f943716628edb501e3a5a97ebf14f605e0a331f1ee9e4a0`.

Invalid RED attempts that failed lint before behavioral execution are deliberately excluded from TDD evidence: `8e6b9318842620a4cc6d3e2f136a4973ee952dac`, `6c969c5f21edacb214caf6e3eee15d265cd8913d`, and review-test attempt `64175895da76d473ce68b27d8bef6871fbea5676`.

### Task 2 — Embedding batching/validation and direct OpenAI/OpenRouter adapters — COMPLETE

Implemented vendor-neutral deterministic batching, strict response count/index/order/model/provider/dimension validation, usage aggregation with explicit unknown semantics, bounded batch configuration, direct fixed-endpoint OpenAI/OpenRouter embedding capabilities, optional embedding registration, and fake transport coverage. M08 still performs no automatic retries and CI performs no paid provider calls.

Genuine TDD evidence:

- Initial Task 2 test-only commit `6cefe1d07821e792f9c6af880354976d695ac9a2` / CI `33865141425` is excluded because PHPCS stopped before behavioral execution.
- Initial RED `89767ff0b09915fb5fe1c7709fee565149d107c7` / CI `33866391356`: PHPStan 0 errors; PHPUnit 335 tests / 1,470 assertions with 8 errors + 3 failures caused by intentionally absent Task 2 service/config/provider behavior.
- Initial assembled implementation `1179d51750c0317061f7ddeb481b61b6e8a3a0bd` / CI `33866742477` exposed repository-owned PHPCS issues; follow-up static analysis also exposed that the runtime-positive batch bound needed an explicit positive integer type contract. These were repaired without weakening runtime validation.
- Fresh independent review found one Important behavior defect: mixed dimensions inside the first batch were accepted when the caller did not specify dimensions.
- Review RED `74cd3a8cd439922befbc27f1e6ceb70abf63d6dc` / CI `33869958370`: PHPStan 0 errors; PHPUnit 336 tests / 1,513 assertions / exactly one intended failure proving that defect.
- Review GREEN `cb37685ad8955f578e38b5e851193d860cec1871` / CI `33870183605`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 336/336, 1,514 assertions; Composer audit clean; JS quality/package assertions green; full WordPress smoke green.
- Task 2 review outcome after fix: Critical 0; Important 0 unresolved.
- Package artifact `9935760721`, digest `sha256:8861e02662d910c2595d235a22c846f9a9659143289e7753ea1a900a97ee430b`.

## Exact next unfinished action

Begin M08 Task 3 with test-only RED coverage for vector-store contracts, truthful optional capability interfaces, portable metadata filters, registry consistency, and a reusable adapter contract suite. Require genuine exact-SHA RED before production implementation, then GREEN, independent review, and durable evidence update.
