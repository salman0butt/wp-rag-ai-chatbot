# M08 — Embeddings & Vector Store Abstraction/Implementations

Status: IN PROGRESS — Tasks 1-2 complete; Task 3 next.

## Goal
Generate compatible embeddings and store/search them through replaceable vector-store adapters.

## Dependencies
M03 provider capabilities, M07 chunks.

## Design / Plan

- Architecture classification: architectural.
- Design/spec: **AUTO-APPROVED — SCHEDULED MODE**.
- Design: `docs/superpowers/specs/2026-09-04-m08-embeddings-vector-stores-design.md` (`b63add146280f5770939f52c9806ef928c0d39ca`).
- Implementation plan: **AUTO-APPROVED — SCHEDULED MODE**.
- Plan: `docs/superpowers/plans/2026-09-04-m08-embeddings-vector-stores.md` (`6b3ae63ed84c1e0e81884d32ae6f3c1577c4bf0a`).
- Selected architecture: capability-aware common vector-store contracts plus truthful optional operation interfaces; provider-specific HTTP/auth remains in `Providers`; vendor-neutral orchestration belongs in `Embeddings`; M09 queue/retries and M10 hybrid retrieval remain out of scope.

## In Scope
Embedding provider contract; batching/usage; compatibility fingerprint; local WordPress vector store; target external adapters Qdrant/Pinecone/Chroma/OpenAI Vector Store as milestone scope permits; health/capability/filter contracts.

## Out of Scope
Queue/retry/synchronization execution (M09) and hybrid retrieval orchestration/fusion (M10).

## Task 1 — Embedding contracts and compatibility fingerprints — COMPLETE

Delivered:

- optional `EmbeddingProvider` capability in the existing provider registry without changing generation-only providers;
- immutable `EmbeddingRequest`, `EmbeddingResult`, `EmbeddingVector`, and `EmbeddingUsage` contracts;
- explicit unknown-vs-known-zero usage semantics;
- finite, non-empty dense vectors and ordered-list enforcement;
- `EmbeddingProfile`, normalization mode, distance metric, and `VectorIndexProfile`;
- deterministic versioned SHA-256 compatibility fingerprint over provider/model/dimensions/normalization/distance;
- provider/model control-character rejection so the delimiter-based canonical fingerprint payload cannot be made ambiguous;
- registry ID consistency checks for optional embedding capability.

### TDD evidence

Initial test-only attempts `8e6b9318842620a4cc6d3e2f136a4973ee952dac` / CI `33861218089` and `6c969c5f21edacb214caf6e3eee15d265cd8913d` / CI `33861362647` are explicitly **not** counted as RED evidence because PHPCS stopped before behavioral execution.

Genuine initial RED:

- SHA `17a77855d4634cb8f72d3327f442ef2cd0e76b3f`
- CI `33861460302`
- PHPStan: 0 errors
- PHPUnit: 320 tests / 1,443 assertions with 7 missing-class errors and 2 expected-exception failures, all caused by absent M08 contracts/registry embedding behavior.

Initial GREEN after contract implementation and static-analysis/style corrections:

- SHA `5b071adb9426fbca4b632d77a6c09196e01c5e6a`
- CI `33862552126`
- PHPStan: 0 errors
- PHPUnit: 320/320 tests, 1,463 assertions
- Composer audit: no security advisories.

Independent review identified two Important boundary issues:

1. Request/vector/result arrays described as ordered still accepted associative PHP arrays, risking non-list JSON/data semantics.
2. Provider/model control characters could make the line-delimited compatibility fingerprint payload ambiguous.

The first review-test attempt `64175895da76d473ce68b27d8bef6871fbea5676` / CI `33862746744` is explicitly **not** counted because PHPCS stopped before PHPUnit.

Genuine review RED:

- SHA `b5e282d5c8c228b871e04e0f7512484ae4d1e275`
- CI `33862913794`
- PHPStan: 0 errors
- PHPUnit: 324 tests / 1,467 assertions / exactly 4 intended failures: control-character profile rejection plus associative request/vector/result list rejection.

Review-fix GREEN:

- SHA `9e3b9c85351b383d246860c8f786a9e74ff1dda0`
- CI `33863156655`
- `php-quality`: success — PHPStan 0 errors; PHPUnit 324/324 tests, 1,467 assertions; Composer audit clean.
- `js-quality`: success.
- `package`: success.
- `wordpress-smoke`: success — activation, database, providers, knowledge, file ingestion, and WooCommerce knowledge.
- Package artifact: `9933055991`, digest `sha256:8182986d6e45269b7f943716628edb501e3a5a97ebf14f605e0a331f1ee9e4a0`.

### Task 1 review result

Fresh independent review after the regression-driven fixes:

- Critical: 0
- Important: 0 unresolved
- The two Important findings above are fixed and regression-covered.
- Response count/index/order/dimension cross-validation remains intentionally Task 2 `EmbeddingService` responsibility per the approved design, not a Task 1 omission.

## Task 2 — Embedding service batching/validation and direct adapters — COMPLETE

Delivered:

- provider-neutral `EmbeddingService` with deterministic bounded application-level batching;
- strict response count, local index, duplicate/missing/out-of-range index, model/provider consistency, ordered reconstruction, and vector-dimension validation;
- usage aggregation that preserves unknown usage rather than fabricating totals;
- `EmbeddingBatchConfig` with a validated positive upper bound reflected in its static-analysis type contract;
- direct OpenAI and OpenRouter embedding capabilities using fixed HTTPS `/embeddings` endpoints, redirects disabled, existing credential/redaction boundaries, and exactly one provider request per service batch;
- provider registration through the existing optional embedding capability slot;
- offline fake/recording transport coverage; no paid provider calls in CI and no automatic retries in M08.

### Task 2 TDD evidence

The initial Task 2 test-only commit `6cefe1d07821e792f9c6af880354976d695ac9a2` / CI `33865141425` is explicitly **not** counted as RED evidence because PHPCS stopped before behavioral execution.

Genuine initial RED:

- SHA `89767ff0b09915fb5fe1c7709fee565149d107c7`
- CI `33866391356`
- PHPStan: 0 errors
- PHPUnit: 335 tests / 1,470 assertions with 8 errors and 3 failures, all attributable to the intentionally absent `EmbeddingService`, `EmbeddingBatchConfig`, and direct-provider embedding behavior.

The first assembled implementation head `1179d51750c0317061f7ddeb481b61b6e8a3a0bd` / CI `33866742477` exposed repository-owned PHPCS failures in the newly extracted provider traits and service docblock. Those style/static issues were corrected without changing the intended behavioral contract; PHPStan then identified that the runtime-positive batch bound was not represented in the property type, which was corrected by `80598faa132842967e156cf3b7785aa49f3a78b7`.

Fresh Task 2 review then identified one Important behavior gap: without an explicit requested dimension, mixed vector dimensions inside the first provider batch were accepted even though the approved design requires consistent dimensions across every result.

Genuine review RED:

- SHA `74cd3a8cd439922befbc27f1e6ceb70abf63d6dc`
- CI `33869958370`
- PHPStan: 0 errors
- PHPUnit: 336 tests / 1,513 assertions / exactly 1 intended failure: the service failed to reject mixed first-batch dimensions when no requested dimension was supplied.

Review-fix GREEN:

- SHA `cb37685ad8955f578e38b5e851193d860cec1871`
- CI `33870183605`
- `php-quality`: success — PHPStan 0 errors; PHPUnit 336/336 tests, 1,514 assertions; Composer audit clean.
- `js-quality`: success — dependency audit, lint/typecheck/tests/build, provider live-gating, and package assertion passed.
- `package`: success.
- `wordpress-smoke`: success — activation, database, providers, knowledge, file ingestion, and WooCommerce knowledge.
- Package artifact: `9935760721`, digest `sha256:8861e02662d910c2595d235a22c846f9a9659143289e7753ea1a900a97ee430b`.

### Task 2 review result

Fresh independent review after the regression-driven fix:

- Critical: 0
- Important: 0 unresolved
- The mixed-first-batch dimension finding is fixed and regression-covered.
- Provider-specific HTTP/auth remains behind `Providers`; generic orchestration remains in `Embeddings`; no M09 retries/queue execution or M10 retrieval behavior was introduced.

## Remaining Tasks

- Task 3 — Vector-store contracts, portable filters, registry, reusable contract suite.
- Task 4 — Local WordPress vector store with dedicated migrations and bounded candidate search.
- Task 5 — Qdrant adapter.
- Task 6 — Pinecone adapter.
- Task 7 — Chroma adapter.
- Task 8 — OpenAI managed vector-store capability adapter using truthful current public capabilities only.
- Task 9 — M07 plan-to-embedding/vector integration, security/performance review, benchmark, durable docs, whole-M08 review, exact-SHA CI, merge, post-merge verification.

## Security Review
Tasks 1-2 keep credentials server-side behind the existing M03 resolver/store boundary. Direct embedding calls use fixed HTTPS endpoints with redirects disabled; provider errors use the existing redaction boundary; malformed provider responses fail closed. No arbitrary endpoint input, client-exposed secret, paid CI call, or automatic retry was introduced.

## Performance Review
Task 1 remains immutable value/registry work. Task 2 adds deterministic bounded batching with a validated maximum and no unbounded provider retry loop. Local vector candidate limits remain Task 4.

## Known Limitations
M08 remains incomplete. Tasks 1-2 provide embedding contracts/orchestration and direct OpenAI/OpenRouter adapters, but vector persistence/search, local vector database behavior, external vector-store adapters, and M07 indexing integration are not yet complete.

## Exact Next Unfinished Action
Begin Task 3 with test-only RED coverage for vector-store contracts, truthful capability interfaces, portable metadata filters, registry consistency, and a reusable adapter contract suite. Require genuine exact-SHA RED before production implementation, then GREEN, independent review, and durable evidence update.

## Next Milestone
M09 — Job Queue & Synchronization, only after M08 is fully reviewed, merged, and post-merge verified.
