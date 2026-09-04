# M08 — Embeddings & Vector Store Abstraction/Implementations

Status: IN PROGRESS — Tasks 1-3 complete; Task 4 next.

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

Delivered:

- infrastructure-neutral `VectorStore` base identity/health/capability contract;
- truthful optional raw upsert/delete/search operation interfaces;
- bounded `VectorCollection`, `VectorRecord`, `VectorSearchRequest`, `VectorSearchResult`, `VectorMatch`, and write-result contracts;
- strict compatibility fingerprint and dimension validation before adapter execution;
- portable typed equality, membership, and conjunction filter AST with bounded key/value/cardinality rules and no raw vendor-filter escape hatch;
- `VectorStoreRegistry` duplicate-ID, adapter-ID/capability consistency, and unsupported-operation enforcement;
- test-only in-memory reference adapter and reusable contract assertions proving stable-ID replacement, collection-scoped idempotent deletion, compatibility/filter-aware search, score-descending/stable-ID deterministic ordering, and cross-collection isolation;
- normalized vector-store error-code/exception boundary;
- runtime validation of untrusted metadata on both writes and adapter-returned search matches;
- identical stable vector-ID grammar enforced for written records and adapter-returned matches.

### Task 3 TDD evidence

Genuine initial RED:

- SHA `fb14f05ffba4a322570d02a6eb7079dadb154c9d`
- CI `33874688946`
- PHPStan: 0 errors
- PHPUnit: 341 tests / 1,515 assertions with 4 errors + 1 failure caused by intentionally absent collection/filter/in-memory vector-store behavior.
- `js-quality`, `package`, and `wordpress-smoke` were green on the same RED SHA.

During implementation/review, exact head `980528cc98f4e09f98f470fc4effce65f47af3c8` / CI `33876558057` exposed one genuine behavioral RED: `VectorRecord` accepted non-scalar untrusted metadata. The minimum fix `41b26b18cf0afbcef0a10b5e7dddd28220d373ed` made PHPStan clean and PHPUnit 345/345, 1,527 assertions.

Fresh independent review then found two additional Important trust-boundary issues:

1. adapter-returned `VectorMatch` metadata was described as safe scalar metadata but was never runtime-validated;
2. adapter-returned `VectorMatch` IDs rejected only blanks instead of enforcing the same bounded stable-ID grammar as `VectorRecord`.

Genuine review REDs:

- `85f93be9d92cb53c979a9f4a722b3da11a6ac009` / CI `33880518572`: PHPStan 0 errors; PHPUnit 346 tests / 1,528 assertions / exactly one intended failure proving non-scalar match metadata was accepted.
- `54762f61f2da98309e767359986b39cb76762467` / CI `33880825526`: PHPStan 0 errors; PHPUnit 347 tests / 1,529 assertions / exactly one intended failure proving malformed match IDs were accepted.

Review-fix GREEN:

- SHA `d5fa24f1cbe29a1e163c791546fc0293774d0255`
- CI `33880952765`
- `php-quality`: success — PHPStan 0 errors; PHPUnit 347/347 tests, 1,529 assertions; Composer audit clean.
- `js-quality`: success.
- `package`: success.
- `wordpress-smoke`: success — activation, database, providers, knowledge, file ingestion, and WooCommerce knowledge.
- Package artifact: `9939828694`, digest `sha256:eb9067510c8bbf9f791c3c9448e8decf262ade7800805d53e54f9fe5ade98bb7`.

### Task 3 review result

Fresh independent review after the regression-driven fixes:

- Critical: 0
- Important: 0 unresolved
- Non-scalar write metadata, non-scalar adapter-returned match metadata, and malformed adapter-returned stable IDs are fixed and regression-covered.
- Collection isolation, compatibility enforcement, deterministic tie ordering, portable filters, and truthful unsupported capabilities remain covered by the Task 3 contract suite.

## Remaining Tasks

- Task 4 — Local WordPress vector store with dedicated migrations and bounded candidate search.
- Task 5 — Qdrant adapter.
- Task 6 — Pinecone adapter.
- Task 7 — Chroma adapter.
- Task 8 — OpenAI managed vector-store capability adapter using truthful current public capabilities only.
- Task 9 — M07 plan-to-embedding/vector integration, security/performance review, benchmark, durable docs, whole-M08 review, exact-SHA CI, merge, post-merge verification.

## Security Review
Tasks 1-3 keep credentials server-side behind the existing M03 boundary, expose no raw vendor-filter escape hatch, validate compatibility before vector operations, scope contract operations to explicit collections, and bound/validate untrusted metadata and stable IDs at both write and adapter-returned result boundaries. Direct embedding calls use fixed HTTPS endpoints with redirects disabled. No arbitrary public endpoint, client secret exposure, paid CI call, or automatic retry was introduced.

## Performance Review
Task 2 uses bounded embedding batching. Task 3 bounds top-K/filter cardinality/metadata and keeps the in-memory implementation test-only. Production local candidate ceilings and database-backed bounded similarity calculation remain Task 4.

## Known Limitations
M08 remains incomplete. Tasks 1-3 establish embedding execution and vector-store application contracts, but production local vector persistence/search, external vector adapters, and M07 indexing integration remain unfinished.

## Exact Next Unfinished Action
Begin Task 4 with a test-only RED covering dedicated versioned WordPress vector tables/migrations, collection/fingerprint isolation, stable-ID upsert replacement, collection-scoped idempotent delete, portable SQL filter translation, cosine similarity over a hard-bounded candidate set, deterministic ordering, and explicit scale-limit failure rather than unbounded PHP scanning. Require genuine behavioral RED before production implementation, then GREEN, WordPress integration verification, independent review, and durable evidence update.

## Next Milestone
M09 — Job Queue & Synchronization, only after M08 is fully reviewed, merged, and post-merge verified.
