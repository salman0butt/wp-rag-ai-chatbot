# M08 — Embeddings & Vector Store Abstraction/Implementations

Status: IN PROGRESS — Tasks 1-8 complete; Task 9 next.

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
- Review RED `980528cc98f4e09f98f470fc4effce65f47af3c8`: exactly one behavioral failure proving non-scalar `VectorRecord` metadata was accepted.
- Review RED `85f93be9d92cb53c979a9f4a722b3da11a6ac009` / CI `33880518572`: PHPStan 0 errors; PHPUnit 346 tests / 1,528 assertions / exactly one intended failure proving adapter-returned non-scalar match metadata was accepted.
- Review RED `54762f61f2da98309e767359986b39cb76762467` / CI `33880825526`: PHPStan 0 errors; PHPUnit 347 tests / 1,529 assertions / exactly one intended failure proving malformed adapter-returned stable IDs were accepted.
- Review GREEN `d5fa24f1cbe29a1e163c791546fc0293774d0255` / CI `33880952765`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 347/347, 1,529 assertions; Composer audit clean; full WordPress smoke green.
- Final review: Critical 0; Important 0 unresolved.
- Package artifact `9939828694`, digest `sha256:eb9067510c8bbf9f791c3c9448e8decf262ade7800805d53e54f9fe5ade98bb7`.

## Task 4 — Local WordPress vector store — COMPLETE

Delivered dedicated versioned vector tables, schema version 4, safe uninstall ordering, a bounded database-backed local vector store, collection/profile isolation, stable-ID replacement, idempotent deletes, prepared portable filters, database narrowing before PHP cosine scoring, hard candidate limits, deterministic ordering, explicit `LOCAL_SCALE_LIMIT`, and real WordPress integration coverage.

TDD / verification:

- Initial RED `a0f49b3645a26889bca6c58b0d7f2349c89427c0` / CI `33885763320`: PHPStan 0 errors; PHPUnit 353 tests / 1,535 assertions / exactly 6 intended failures.
- Pre-review GREEN `b58fce7c5c520a0df4dbf9cd17b6c71893934821` / CI `33893539465`: all four permanent jobs green.
- Independent review found one Important operational-contract defect: candidate overflow used generic `operation_failed` instead of the design-required local scale-limit category.
- Review RED `31a9f25d6492a7df3189184487cf75eb51b70a24` / CI `33897449155`: PHPStan 0 errors; PHPUnit 358 tests / 1,575 assertions with exactly one intended error because `LOCAL_SCALE_LIMIT` did not yet exist.
- Review GREEN `69dce20f2c7c58239d999cbb414e07c5dac100fb` / CI `33898085114`: all four permanent jobs green; PHPUnit 358/358, 1,576 assertions; Composer audit clean; full WordPress smoke green.
- Artifact `9946564686`, digest `sha256:0753a05c42901db841a805e6c0f1305554ec56a19b4b64fb7ff7c7a9a8310740`.
- Independent review PR #11 review `5115843007`: Critical 0; Important 0 unresolved.

### Local-store performance boundary

The local adapter intentionally has no unbounded fallback. The database query requests at most `candidate_limit + 1` rows after collection/fingerprint/filter narrowing; PHP scores at most `candidate_limit` rows and rejects overflow as `LOCAL_SCALE_LIMIT`. Workloads that routinely exceed the ceiling should use a purpose-built external vector store rather than raising the ceiling indefinitely.

## Task 5 — Qdrant adapter — COMPLETE

Delivered an offline-testable Qdrant raw-vector adapter with validated administrator-owned HTTPS origins, server-side API keys, zero redirects, one transport send per request/no retries, compatibility-isolated physical collections, remote dimensions/distance verification, deterministic plugin stable-ID → Qdrant UUID mapping, portable filters, bounded top-K, fail-closed response validation, sanitized errors, truthful health/capabilities, and a default-off credential-gated live health hook.

TDD / verification:

- Initial RED `8cb3d00aa13edf33ac4c41ae0aceee5f90391c20` / CI `33900244344`: PHPStan 0 errors; PHPUnit 366 tests / 1,584 assertions / exactly 8 intended failures.
- Runtime-boundary GREEN `b8f7b3772e0cb6888084b0ea899f641c55029320` / CI `33907850449`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 366/366, 1,634 assertions; Composer audit clean.
- Review RED `4786d836258bec795dd18a2256ef3789ae7724db`: returned compatibility fingerprint was not verified; fixed by `a6ab35993216549ccad4419c67272585e3e2bcd4`.
- Review RED `955605e5590e89b4fdd81b7db7ec8e20badf3668`: untrusted response cardinality could exceed bounded `top_k`; fixed on path ending `0fbbda7cf057a50cc01d3ffd08ab0e716ad359a5`.
- Live-hook RED `251fdf8fce62c2378e678c9465cf9cb3b0efd2bd` / CI `33909386169`: live-gating wrapper absent.
- Final GREEN `974a9fb64a1bc66864b0cad82fe42a22225608c5` / CI `33909554302`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 368/368, 1,642 assertions; Composer audit clean; full WordPress smoke and offline live-Qdrant gating green.
- Package artifact `9950829684`, digest `sha256:e701359c6b955a54de73a0e9402db4d528f2e04049b2e6b584f4c0fbc2484958`.
- Independent review PR #11 review `5116935253`: Critical 0; Important 0 unresolved; no unresolved review threads.

## Task 6 — Pinecone adapter — COMPLETE

Delivered an offline-testable Pinecone raw-vector adapter with fixed validated HTTPS data/control-plane boundaries, server-side API-key handling, pinned Pinecone REST API version, redirects disabled/no retries, remote index compatibility verification, profile-isolated namespaces, deterministic stable-ID upsert/delete, portable filters, bounded/fail-closed result mapping, sanitized errors, truthful health/capabilities, and a default-off credential-gated live health hook.

TDD / verification:

- Genuine initial RED `e9334c8e0e15283988c6d1985426004c7e0c2956` / CI `33910441036`: PHPStan 0 errors; PHPUnit 378 tests / 1,652 assertions / exactly 10 intended failures because Pinecone production contracts were absent.
- Initial GREEN `c8485e361d4cbc919b73f9d4f9fdb3049315a2c8` / CI `33911164667`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 378/378, 1,709 assertions; Composer audit clean.
- API-version contract was pinned through RED `21688ddc294f7536df54adf89acba9a65e1c83db` and fix `22f81c4f57596bba5f14d30c10d05548ff612b56`.
- Live smoke gating landed through `03da15615014557213bf4b0d052004656ee1ecd1` → `52fe11c275891256578651138ec48462ce6f4853`; CI `33911943423` passed all four permanent jobs.
- Independent review found one Important vendor-capability mismatch: boolean membership values were sent to Pinecone even though Pinecone membership filtering does not support boolean `$in` values.
- Genuine review RED `d73f5bfc6309da8c435b4a9f2d31ae4e00e8133d` / CI `33913238466`: PHPStan 0 errors; PHPUnit 380 tests / 1,713 assertions / exactly one intended failure.
- Final GREEN `29f1d94394827615b82fadb7129d755a5bce50a3` / CI `33913502927`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 380/380, 1,714 assertions; Composer audit clean; full WordPress smoke green.
- Package artifact `9953323840`, digest `sha256:24ef3bd3794db00222d5999736304161a089465c32a55b68ced237ec45ce0c86`.
- Independent Task 6 review PR #11 review `5117304228`: Critical 0; Important 0 unresolved; no unresolved review threads.

## Task 7 — Chroma adapter — COMPLETE

Delivered an offline-testable Chroma v2 raw-vector adapter with validated administrator-owned HTTPS origins, validated tenant/database scope, optional server-side token handling, redirects disabled/no automatic retries, deterministic compatibility-isolated physical collections, remote dimension/metric/fingerprint verification, explicit embedding upserts, stable-ID delete behavior, portable equality/membership/conjunction filters, bounded top-K/result cardinality, deterministic score ordering, sanitized failures, truthful health/capabilities, and a default-off credential-gated live health hook.

TDD / verification:

- Genuine initial RED `c331890c617aa5d3bc3dcf1036a3fc85b6fb8074` / CI `33918105636`: PHPStan 0 errors; PHPUnit 390 tests / 1,724 assertions / exactly 10 intended failures because `ChromaConfig` and the Chroma production adapter behavior were absent.
- Initial GREEN `ba47b547345799aa1dffc1067a488f95ccfaf5cb` / CI `33919128725`: all four permanent jobs green after the production adapter and heartbeat-compatible health contract landed.
- Live-health smoke coverage was added behind explicit opt-in/credential gating; normal CI performs no Chroma network call.
- Independent review found one Important remote trust-boundary defect: the remote collection ID check accepted any 36-character hex/hyphen string, including 36 hyphens, allowing a malformed collection identifier to proceed to a mutation request.
- Review RED `ee19261237dd9fbf76363744d491172066ce247a` / CI `33922544502`: PHPStan 0 errors; PHPUnit 392 tests / 1,794 assertions / exactly one intended failure, proving a malformed remote collection ID caused a second network request.
- Review GREEN `121a930901ab1cf0f1eb9365a83a41d104b96b76` / CI `33922772281`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 392/392, 1,795 assertions; Composer audit clean; full WordPress smoke green.
- Package artifact `9955679919`, digest `sha256:c17aa5857594a453fec3ce433691a8847b641b72def4f2c5e5405d437a410db7`.
- Independent Task 7 review PR #11 review `5118131476`: Critical 0; Important 0 unresolved.
- No unresolved PR review threads at Task 7 closeout.

## Task 8 — OpenAI managed vector-store capability adapter — COMPLETE

Delivered a truthful capability-specific OpenAI managed vector-store adapter. Current public API semantics were verified before implementation: managed file attachment/deletion and text-query vector-store search are supported, while caller-supplied raw-vector upsert/delete/search semantics are not exposed. The adapter therefore implements a dedicated managed operation contract, keeps all raw-vector capability flags false, uses the fixed official OpenAI HTTPS origin, keeps authorization server-side, disables redirects, performs no automatic retries, bounds file IDs/attributes/query/results/content, validates untrusted search results, sanitizes provider failures, and reports health only when the configured vector store is `completed`/ready.

TDD / verification:

- Genuine initial RED `4125950e5598082ba1c70039f98f20e7304dfc6c` / CI `33926896274`: PHPStan 0 errors; PHPUnit 399 tests / 1,804 assertions with exactly 6 intended failures caused by the absent managed adapter/configuration contracts.
- Intermediate implementation/lint/static-analysis repair commits `08afdf527ec5aa80b97edadc7b01789d37b2d50a`, `a6a62d07ce7f017e1a88eb332494675484a2ecbb`, and `cdb5b2a146c162c76885ef3f3ef9f7a4ca89974f` are not counted as additional RED evidence; the last reached PHP GREEN with PHPUnit 399/399, 1,849 assertions.
- Independent review found two Important issues: the registry did not enforce/expose managed capability truth, and health incorrectly treated non-ready OpenAI vector-store statuses as healthy.
- The first review-test commit stopped at PHPCS and is not counted. Genuine review RED `13810bf7f8ee516ecee4138ba585c6cf2eccb21f` / CI `33927625909`: PHPStan 0 errors; PHPUnit 402 tests / 1,853 assertions with exactly 1 error + 2 failures for the intended managed-registry and readiness findings.
- Final implementation GREEN `dfbae21e1a4226a7ce285413a74b817e1089ce78` / CI `33927746937`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 402/402, 1,854 assertions; Composer audit clean; full WordPress smoke green.
- Package artifact `9957422625`, digest `sha256:dfef7a3064fdb8cc2bb90958481169d087f35767c46a28470bf8fcdb59808167`.
- Independent Task 8 review PR #11 review `5118469428`: Critical 0; Important 0 unresolved; no unresolved review threads.

## Remaining Tasks

- Task 9 — M07 plan-to-embedding/vector integration, security/performance review, benchmark, durable docs, whole-M08 review, exact-SHA CI, merge, post-merge verification.

## Security Review
Tasks 1-8 keep credentials server-side behind existing provider/transport boundaries, expose no raw vendor-filter or SQL escape hatch, validate compatibility before vector operations, scope operations by explicit collection/profile boundaries, validate untrusted metadata and stable/remote IDs, use fixed validated HTTPS/control-plane endpoints, disable redirects, sanitize remote failures, and perform no automatic retries. The OpenAI managed adapter additionally keeps raw-vector capabilities false and gates managed capability access through the registry. Live external-store hooks are explicit opt-in and credential-gated; normal CI performs no paid/external vector-store network calls.

## Performance Review
Task 2 uses bounded embedding batching. Task 3 bounds top-K/filter cardinality/metadata. Task 4 database-narrows candidates before PHP similarity and enforces a hard local candidate ceiling. Tasks 5-7 delegate raw-vector search to purpose-built external stores with bounded caller top-K and fail-closed response-cardinality checks. Task 8 bounds managed search requests to OpenAI's public result ceiling and fail-closes oversized responses. No unbounded adapter-side vector scan was introduced.

## Known Limitations
M08 remains incomplete until Task 9. Tasks 1-8 now provide embedding execution, common vector-store contracts, a bounded local WordPress implementation, Qdrant/Pinecone/Chroma raw-vector adapters, and truthful OpenAI managed vector-store capability mapping. M07 indexing integration and final whole-M08 closeout remain unfinished.

## Exact Next Unfinished Action
Begin Task 9 — M07 plan-to-embedding/vector integration and whole-M08 closeout. First add a test-only behavioral RED that proves accepted M07 indexing plans can be transformed into bounded embedding requests and deterministic vector records using the chosen embedding/index profile without bypassing compatibility, collection, metadata, or stable-ID contracts. Implement the minimum orchestration glue with no M09 queue/retry behavior and no M10 hybrid retrieval. Then run security/performance review and the required benchmark, add regression REDs for every Critical/Important finding, update durable docs, perform final whole-M08 independent review, require exact-final-head permanent CI green, mark PR #11 ready only when every M08 acceptance criterion is satisfied, merge only with expected exact SHA, and verify post-merge `main` CI before declaring M08 complete.

## Next Milestone
M09 — Job Queue & Synchronization, only after M08 is fully reviewed, merged, and post-merge verified.