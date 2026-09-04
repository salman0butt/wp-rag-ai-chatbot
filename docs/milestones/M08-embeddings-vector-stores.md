# M08 — Embeddings & Vector Store Abstraction/Implementations

Status: IN PROGRESS — Tasks 1-4 complete; Task 5 next.

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

Delivered optional provider embedding capability, immutable embedding request/result/vector/usage contracts, explicit unknown usage semantics, finite/list validation, embedding/index profiles, deterministic versioned compatibility fingerprints, and registry consistency.

TDD / verification:

- Genuine initial RED `17a77855d4634cb8f72d3327f442ef2cd0e76b3f` / CI `33861460302`: PHPStan 0 errors; PHPUnit 320 tests / 1,443 assertions with 7 missing-class errors and 2 expected-exception failures caused by absent Task 1 behavior.
- Initial GREEN `5b071adb9426fbca4b632d77a6c09196e01c5e6a` / CI `33862552126`: PHPStan 0 errors; PHPUnit 320/320, 1,463 assertions; Composer audit clean.
- Independent review found two Important issues: ordered-list contracts accepted associative arrays, and control characters could make the fingerprint payload ambiguous.
- Review RED `b5e282d5c8c228b871e04e0f7512484ae4d1e275` / CI `33862913794`: PHPStan 0 errors; PHPUnit 324 tests / 1,467 assertions / exactly 4 intended failures.
- Review GREEN `9e3b9c85351b383d246860c8f786a9e74ff1dda0` / CI `33863156655`: all four permanent jobs green; PHPUnit 324/324, 1,467 assertions; Composer audit clean.
- Final review: Critical 0; Important 0 unresolved.
- Package artifact `9933055991`, digest `sha256:8182986d6e45269b7f943716628edb501e3a5a97ebf14f605e0a331f1ee9e4a0`.

Invalid RED attempts that stopped before behavioral execution are not counted as TDD evidence.

## Task 2 — Embedding service batching/validation and direct adapters — COMPLETE

Delivered deterministic bounded `EmbeddingService` batching, strict count/index/order/model/provider/dimension validation, known/unknown usage aggregation, bounded batch configuration, fixed-endpoint OpenAI/OpenRouter embedding capabilities, provider registration, and offline fake-transport coverage. M08 still performs no automatic retries and CI performs no paid provider calls.

TDD / verification:

- Genuine initial RED `89767ff0b09915fb5fe1c7709fee565149d107c7` / CI `33866391356`: PHPStan 0 errors; PHPUnit 335 tests / 1,470 assertions with 8 errors + 3 failures caused by absent Task 2 behavior.
- Fresh review found one Important defect: mixed dimensions inside the first batch were accepted when the caller did not specify dimensions.
- Review RED `74cd3a8cd439922befbc27f1e6ceb70abf63d6dc` / CI `33869958370`: PHPStan 0 errors; PHPUnit 336 tests / 1,513 assertions / exactly one intended failure.
- Review GREEN `cb37685ad8955f578e38b5e851193d860cec1871` / CI `33870183605`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 336/336, 1,514 assertions; Composer audit clean; full WordPress smoke green.
- Final review: Critical 0; Important 0 unresolved.
- Package artifact `9935760721`, digest `sha256:8861e02662d910c2595d235a22c846f9a9659143289e7753ea1a900a97ee430b`.

## Task 3 — Vector-store contracts, portable filters, registry, reusable contract suite — COMPLETE

Delivered infrastructure-neutral store identity/health/capability contracts, truthful optional raw operation interfaces, bounded collection/record/search/result contracts, compatibility validation, a portable typed equality/membership/conjunction filter AST without raw vendor-filter escape hatches, duplicate-safe registry behavior, a test-only in-memory reference adapter, normalized errors, and runtime trust-boundary validation for vector metadata and result IDs.

TDD / verification:

- Genuine initial RED `fb14f05ffba4a322570d02a6eb7079dadb154c9d` / CI `33874688946`: PHPStan 0 errors; PHPUnit 341 tests / 1,515 assertions with 4 errors + 1 failure caused by intentionally absent Task 3 behavior.
- Review RED `980528cc98f4e09f98f470fc4effce65f47af3c8` / CI `33876558057`: exactly one behavioral failure proving non-scalar `VectorRecord` metadata was accepted.
- Review RED `85f93be9d92cb53c979a9f4a722b3da11a6ac009` / CI `33880518572`: PHPStan 0 errors; PHPUnit 346 tests / 1,528 assertions / exactly one intended failure proving adapter-returned non-scalar match metadata was accepted.
- Review RED `54762f61f2da98309e767359986b39cb76762467` / CI `33880825526`: PHPStan 0 errors; PHPUnit 347 tests / 1,529 assertions / exactly one intended failure proving malformed adapter-returned stable IDs were accepted.
- Review GREEN `d5fa24f1cbe29a1e163c791546fc0293774d0255` / CI `33880952765`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 347/347, 1,529 assertions; Composer audit clean; full WordPress smoke green.
- Final review: Critical 0; Important 0 unresolved.
- Package artifact `9939828694`, digest `sha256:eb9067510c8bbf9f791c3c9448e8decf262ade7800805d53e54f9fe5ade98bb7`.

## Task 4 — Local WordPress vector store — COMPLETE

Delivered:

- dedicated per-site `rag_ai_vector_collections` and `rag_ai_vectors` tables through versioned V003/V004 migrations, with schema version 4 and safe uninstall ordering;
- a database-backed `LocalVectorStore` with stable-ID replacement, collection-scoped idempotent deletion, collection/profile compatibility enforcement, and health/capability reporting;
- prepared portable equality/membership/conjunction filter translation with no raw SQL/vendor-filter escape hatch;
- database candidate selection scoped by collection, compatibility fingerprint, and portable filters before any PHP cosine scoring;
- a hard `candidate_limit + 1` SQL bound so overflow is detected without expanding into an unbounded PHP scan;
- deterministic score-descending/stable-ID ordering and bounded top-K;
- explicit `LOCAL_SCALE_LIMIT` error normalization when the configured local candidate ceiling is exceeded;
- migration, scoring, adapter-boundary, compatibility, filter, replacement/delete, and real WordPress database integration coverage.

### Task 4 TDD evidence

Genuine initial RED:

- SHA `a0f49b3645a26889bca6c58b0d7f2349c89427c0`
- CI `33885763320`
- PHPStan: 0 errors.
- PHPUnit: 353 tests / 1,535 assertions / exactly 6 intended failures proving absent V003/V004 migrations, cosine similarity, and bounded local-store configuration behavior.
- `js-quality`, `package`, and `wordpress-smoke` were green on the same RED SHA.

Implementation then progressed through regression-first compatibility/isolation fixes for adapter boundaries and delete behavior. The pre-review Task 4 head `b58fce7c5c520a0df4dbf9cd17b6c71893934821` / CI `33893539465` passed all four permanent jobs.

Fresh independent review found one Important operational-contract defect: candidate-ceiling overflow was correctly fail-closed but normalized as generic `operation_failed`, while the approved M08 design requires a distinct local-scale-limit category.

Review RED:

- SHA `31a9f25d6492a7df3189184487cf75eb51b70a24`
- CI `33897449155`
- PHPStan: 0 errors.
- PHPUnit: 358 tests / 1,575 assertions with exactly one error because `VectorStoreErrorCode::LOCAL_SCALE_LIMIT` did not yet exist.

Review-fix GREEN:

- SHA `69dce20f2c7c58239d999cbb414e07c5dac100fb`
- CI `33898085114`
- `php-quality`: success — PHPStan 0 errors; PHPUnit 358/358 tests, 1,576 assertions; Composer audit clean.
- `js-quality`: success.
- `package`: success.
- `wordpress-smoke`: success — activation, database, providers, knowledge, file ingestion, and WooCommerce knowledge.
- Package artifact `9946564686`, digest `sha256:0753a05c42901db841a805e6c0f1305554ec56a19b4b64fb7ff7c7a9a8310740`.

### Task 4 review result

Fresh independent review after the regression-driven scale-limit fix:

- Critical: 0
- Important: 0 unresolved
- Review submission: PR #11 review `5115843007`.
- Migration/version ordering, collection/fingerprint isolation, prepared SQL/filter translation, stable-ID replacement, idempotent deletion, hard candidate bounding before PHP similarity scoring, deterministic ordering, and compatibility boundaries were reviewed.
- No unresolved PR review threads remain.

### Local-store performance boundary

The local adapter intentionally has no unbounded fallback. The database query requests at most `candidate_limit + 1` rows after collection/fingerprint/filter narrowing; PHP therefore scores at most the configured `candidate_limit` rows and rejects overflow as `LOCAL_SCALE_LIMIT`. The deterministic work bound is O(`candidate_limit × embedding_dimensions`) for similarity calculation plus bounded row decoding/sorting. No wall-clock SLO is asserted from CI hardware.

Practical limit: use the local WordPress adapter only when normal collection/profile/filter narrowing keeps the candidate set at or below the configured hard candidate ceiling. If a workload routinely exceeds that ceiling, treat `LOCAL_SCALE_LIMIT` as an explicit signal to use a purpose-built external vector store rather than raising the ceiling until PHP/database scans become effectively unbounded.

## Remaining Tasks

- Task 5 — Qdrant adapter.
- Task 6 — Pinecone adapter.
- Task 7 — Chroma adapter.
- Task 8 — OpenAI managed vector-store capability adapter using truthful current public capabilities only.
- Task 9 — M07 plan-to-embedding/vector integration, security/performance review, benchmark, durable docs, whole-M08 review, exact-SHA CI, merge, post-merge verification.

## Security Review
Tasks 1-4 keep credentials server-side behind the existing M03 boundary, expose no raw vendor-filter or SQL escape hatch, validate compatibility before vector operations, scope local operations by explicit collection/profile boundaries, prepare local filter query values, and bound/validate untrusted metadata and stable IDs at write/result boundaries. Direct embedding calls use fixed HTTPS endpoints with redirects disabled. No arbitrary public endpoint, client secret exposure, paid CI call, automatic retry, or unbounded local vector scan was introduced.

## Performance Review
Task 2 uses bounded embedding batching. Task 3 bounds top-K/filter cardinality/metadata. Task 4 database-narrows candidates before PHP similarity scoring and enforces a hard configured candidate ceiling with an explicit scale-limit failure. External purpose-built vector-store performance remains Tasks 5-8.

## Known Limitations
M08 remains incomplete. Tasks 1-4 provide embedding execution, common vector-store contracts, and a bounded local WordPress implementation, but external vector adapters and M07 indexing integration remain unfinished. The local adapter is intentionally for modest/narrowable candidate sets and fails explicitly rather than silently scanning beyond its configured ceiling.

## Exact Next Unfinished Action
Begin Task 5 — Qdrant adapter — with a test-only RED against the existing common contract suite and offline fake transport. Cover fixed/allowlisted endpoint construction, server-side credentials, collection/profile compatibility, deterministic payload mapping, stable-ID upsert/delete, portable filter translation, bounded top-K search result mapping, health/capability truthfulness, normalized error behavior, and no automatic retries. Require genuine behavioral RED before production implementation, then GREEN, independent review, full exact-SHA CI, and durable Task 5 evidence before Pinecone Task 6.

## Next Milestone
M09 — Job Queue & Synchronization, only after M08 is fully reviewed, merged, and post-merge verified.
