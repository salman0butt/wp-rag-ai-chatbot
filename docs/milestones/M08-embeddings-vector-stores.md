# M08 — Embeddings & Vector Store Abstraction/Implementations

Status: IN PROGRESS — Task 1 complete; Task 2 next.

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

## Remaining Tasks

- Task 2 — Embedding service batching/validation and OpenAI/OpenRouter direct embedding adapters.
- Task 3 — Vector-store contracts, portable filters, registry, reusable contract suite.
- Task 4 — Local WordPress vector store with dedicated migrations and bounded candidate search.
- Task 5 — Qdrant adapter.
- Task 6 — Pinecone adapter.
- Task 7 — Chroma adapter.
- Task 8 — OpenAI managed vector-store capability adapter using truthful current public capabilities only.
- Task 9 — M07 plan-to-embedding/vector integration, security/performance review, benchmark, durable docs, whole-M08 review, exact-SHA CI, merge, post-merge verification.

## Security Review
Task 1 contains no credentials or network execution. Inputs are validated as ordered lists; vectors must be finite; compatibility IDs reject control characters. Provider credentials/network execution enter later tasks through the existing M03 server-side provider boundary.

## Performance Review
Task 1 is immutable value/registry work only and introduces no network loops or vector scans. Batching bounds are Task 2; local candidate limits are Task 4.

## Known Limitations
M08 remains incomplete. No embedding provider network execution, vector persistence/search, local vector database, or external vector-store adapter is enabled by Task 1.

## Exact Next Unfinished Action
Begin Task 2 with test-only RED coverage for deterministic bounded batching, count/index/dimension/order validation, usage aggregation, and fake-transport OpenAI/OpenRouter embedding requests. Do not implement provider behavior before exact RED evidence.

## Next Milestone
M09 — Job Queue & Synchronization, only after M08 is fully reviewed, merged, and post-merge verified.
