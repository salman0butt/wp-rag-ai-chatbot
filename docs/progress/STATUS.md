# Global Status

- Completed milestones on `main`: **M00-M07**.
- Current `main` SHA: `b642813c92ee152805c16a0bd6902b4ce67e33df`.
- M07 post-closeout `main` CI: `33860207844` — all four permanent jobs passed.
- Current milestone: **M08 — Embeddings & Vector Stores — IN PROGRESS**.
- M08 branch: `feat/m08-embeddings-vector-stores`.
- M08 PR: **#11 — open draft**.
- M08 design/spec and implementation plan: **AUTO-APPROVED — SCHEDULED MODE**.
- M08 Tasks 1-8: **COMPLETE on feature branch**; Task 9 is next.

## M07 final state — COMPLETE

M07 is durably integrated on `main` at `b642813c92ee152805c16a0bd6902b4ce67e33df`. Fresh post-closeout CI `33860207844` passed `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.

## M08 — IN PROGRESS

### Architecture gate

- Design/spec: `docs/superpowers/specs/2026-09-04-m08-embeddings-vector-stores-design.md`, commit `b63add146280f5770939f52c9806ef928c0d39ca` — **AUTO-APPROVED — SCHEDULED MODE**.
- Implementation plan: `docs/superpowers/plans/2026-09-04-m08-embeddings-vector-stores.md`, commit `6b3ae63ed84c1e0e81884d32ae6f3c1577c4bf0a` — **AUTO-APPROVED — SCHEDULED MODE**.
- Architecture uses capability-aware vector-store contracts, keeps provider HTTP/auth inside `Providers`, keeps generic embedding orchestration in `Embeddings`, and excludes M09 queue/retries and M10 hybrid retrieval.

### Task 1 — COMPLETE

Embedding contracts, compatibility profiles/fingerprints, optional provider capability, ordered-list/finite-vector validation, and registry consistency are complete.

- Genuine RED: `17a77855d4634cb8f72d3327f442ef2cd0e76b3f` / CI `33861460302`.
- Review GREEN: `9e3b9c85351b383d246860c8f786a9e74ff1dda0` / CI `33863156655` — all four permanent jobs green; PHPUnit 324/324, 1,467 assertions; Critical 0 / Important 0 unresolved.

### Task 2 — COMPLETE

Deterministic bounded embedding batching, strict response reconstruction/validation, usage aggregation, direct fixed-endpoint OpenAI/OpenRouter embedding capabilities, and offline fake transports are complete.

- Genuine RED: `89767ff0b09915fb5fe1c7709fee565149d107c7` / CI `33866391356`.
- Review RED: `74cd3a8cd439922befbc27f1e6ceb70abf63d6dc` / CI `33869958370` — exactly one intended mixed-dimension failure.
- Review GREEN: `cb37685ad8955f578e38b5e851193d860cec1871` / CI `33870183605` — all four permanent jobs green; PHPUnit 336/336, 1,514 assertions; Critical 0 / Important 0 unresolved.

### Task 3 — COMPLETE

Vector-store base/operation contracts, truthful capability registry, bounded portable metadata/filter AST, compatibility-aware request/record/result contracts, normalized errors, and the test-only in-memory reusable contract adapter are complete.

- Genuine RED `fb14f05ffba4a322570d02a6eb7079dadb154c9d` / CI `33874688946`: PHPStan 0 errors; PHPUnit 341 tests / 1,515 assertions with 4 errors + 1 failure.
- Review REDs `980528cc98f4e09f98f470fc4effce65f47af3c8`, `85f93be9d92cb53c979a9f4a722b3da11a6ac009`, and `54762f61f2da98309e767359986b39cb76762467` captured metadata/result-ID trust-boundary defects.
- Final GREEN `d5fa24f1cbe29a1e163c791546fc0293774d0255` / CI `33880952765`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 347/347, 1,529 assertions; Composer audit clean; full WordPress smoke green.
- Independent review: **Critical 0 / Important 0 unresolved**.

### Task 4 — COMPLETE

The bounded local WordPress vector store is complete: versioned vector tables, collection/profile isolation, stable-ID replacement, idempotent delete, prepared portable filters, database narrowing before PHP cosine similarity, hard candidate bounding, deterministic ordering, explicit `LOCAL_SCALE_LIMIT`, and WordPress integration coverage.

- Initial RED `a0f49b3645a26889bca6c58b0d7f2349c89427c0` / CI `33885763320`: PHPStan 0 errors; PHPUnit 353 tests / 1,535 assertions / exactly 6 intended failures.
- Review RED `31a9f25d6492a7df3189184487cf75eb51b70a24` / CI `33897449155`: one intended missing `LOCAL_SCALE_LIMIT` error.
- Review GREEN `69dce20f2c7c58239d999cbb414e07c5dac100fb` / CI `33898085114`: all four permanent jobs green; PHPUnit 358/358, 1,576 assertions; Composer audit clean; full WordPress smoke green.
- Independent review submission PR #11 review `5115843007`: **Critical 0 / Important 0 unresolved**.

### Task 5 — Qdrant adapter — COMPLETE

Delivered an offline-testable Qdrant raw-vector adapter with validated administrator-owned HTTPS origins, server-side credentials, zero redirects/no retries, remote profile verification, compatibility-isolated collections, deterministic stable-ID mapping, portable filters, bounded result validation, sanitized errors, truthful health/capabilities, and a default-off credential-gated live health hook.

- Initial RED `8cb3d00aa13edf33ac4c41ae0aceee5f90391c20` / CI `33900244344`: PHPStan 0 errors; PHPUnit 366 tests / 1,584 assertions / exactly 8 intended failures.
- Runtime-boundary GREEN `b8f7b3772e0cb6888084b0ea899f641c55029320` / CI `33907850449`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 366/366, 1,634 assertions; Composer audit clean.
- Review RED `4786d836258bec795dd18a2256ef3789ae7724db`: returned compatibility fingerprint was not verified; fixed by `a6ab35993216549ccad4419c67272585e3e2bcd4`.
- Review RED `955605e5590e89b4fdd81b7db7ec8e20badf3668`: untrusted response cardinality could exceed bounded `top_k`; fixed on path ending `0fbbda7cf057a50cc01d3ffd08ab0e716ad359a5`.
- Live-hook RED `251fdf8fce62c2378e678c9465cf9cb3b0efd2bd` / CI `33909386169`.
- Final GREEN `974a9fb64a1bc66864b0cad82fe42a22225608c5` / CI `33909554302`: all four permanent jobs green; PHPUnit 368/368, 1,642 assertions; Composer audit clean; full WordPress smoke and offline live-Qdrant gating green.
- Package artifact `9950829684`, digest `sha256:e701359c6b955a54de73a0e9402db4d528f2e04049b2e6b584f4c0fbc2484958`.
- Independent Task 5 review PR #11 review `5116935253`: **Critical 0 / Important 0 unresolved**; no unresolved review threads.

### Task 6 — Pinecone adapter — COMPLETE

Delivered an offline-testable Pinecone raw-vector adapter with fixed validated HTTPS data/control-plane boundaries, server-side API-key handling, pinned Pinecone REST API version, redirects disabled/no retries, remote index compatibility verification, profile-isolated namespaces, deterministic stable-ID upsert/delete, portable filters, bounded/fail-closed result mapping, sanitized errors, truthful health/capabilities, and a default-off credential-gated live health hook.

- Genuine initial RED `e9334c8e0e15283988c6d1985426004c7e0c2956` / CI `33910441036`: PHPStan 0 errors; PHPUnit 378 tests / 1,652 assertions / exactly 10 intended failures.
- Initial GREEN `c8485e361d4cbc919b73f9d4f9fdb3049315a2c8` / CI `33911164667`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 378/378, 1,709 assertions; Composer audit clean.
- API-version contract was pinned through RED `21688ddc294f7536df54adf89acba9a65e1c83db` and fix `22f81c4f57596bba5f14d30c10d05548ff612b56`.
- Pinecone live smoke gating landed through `03da15615014557213bf4b0d052004656ee1ecd1` → `52fe11c275891256578651138ec48462ce6f4853`; CI `33911943423` passed all four permanent jobs.
- Fresh independent review found one Important adapter-capability mismatch: portable boolean membership values were sent to Pinecone even though Pinecone membership filtering does not support boolean `$in` values.
- Genuine review RED `d73f5bfc6309da8c435b4a9f2d31ae4e00e8133d` / CI `33913238466`: PHPStan 0 errors; PHPUnit 380 tests / 1,713 assertions / exactly one intended failure.
- Final implementation GREEN `29f1d94394827615b82fadb7129d755a5bce50a3` / CI `33913502927`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 380/380, 1,714 assertions; Composer audit clean; full WordPress smoke green.
- Package artifact `9953323840`, digest `sha256:24ef3bd3794db00222d5999736304161a089465c32a55b68ced237ec45ce0c86`.
- Independent Task 6 review PR #11 review `5117304228`: **Critical 0 / Important 0 unresolved**.

### Task 7 — Chroma adapter — COMPLETE

Delivered an offline-testable Chroma v2 raw-vector adapter with validated administrator-owned HTTPS origins, validated tenant/database scope, optional server-side token handling, redirects disabled/no automatic retries, deterministic compatibility-isolated physical collections, remote dimension/metric/fingerprint verification, explicit embeddings, stable-ID deletion, portable Eq/In/And filters, bounded top-K and response cardinality, deterministic ordering, sanitized errors, truthful health/capabilities, and a default-off credential-gated live health hook.

- Genuine initial RED `c331890c617aa5d3bc3dcf1036a3fc85b6fb8074` / CI `33918105636`: PHPStan 0 errors; PHPUnit 390 tests / 1,724 assertions / exactly 10 intended failures because Chroma production contracts were absent.
- Initial GREEN `ba47b547345799aa1dffc1067a488f95ccfaf5cb` / CI `33919128725`: all four permanent jobs green.
- Independent review found one Important remote trust-boundary defect: malformed 36-character collection IDs could pass the loose ID check and proceed to mutation.
- Review RED `ee19261237dd9fbf76363744d491172066ce247a` / CI `33922544502`: PHPStan 0 errors; PHPUnit 392 tests / 1,794 assertions / exactly one intended failure, proving a malformed remote ID caused a second network request.
- Final GREEN `121a930901ab1cf0f1eb9365a83a41d104b96b76` / CI `33922772281`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 392/392, 1,795 assertions; Composer audit clean; full WordPress smoke green.
- Package artifact `9955679919`, digest `sha256:c17aa5857594a453fec3ce433691a8847b641b72def4f2c5e5405d437a410db7`.
- Independent Task 7 review PR #11 review `5118131476`: **Critical 0 / Important 0 unresolved**; no unresolved review threads.

### Task 8 — OpenAI managed vector-store capability adapter — COMPLETE

Delivered truthful managed OpenAI vector-store capabilities based on the current public API: managed file attachment/deletion and text-query search are exposed through a dedicated managed contract, while raw-vector upsert/delete/search remain false. The adapter uses the fixed official OpenAI HTTPS origin, server-side authorization, zero redirects, no automatic retries, bounded validation and fail-closed result mapping, sanitized errors, managed registry gating, and `completed`-only readiness health.

- Genuine initial RED `4125950e5598082ba1c70039f98f20e7304dfc6c` / CI `33926896274`: PHPStan 0 errors; PHPUnit 399 tests / 1,804 assertions with exactly 6 intended failures caused by absent managed contracts.
- Initial implementation reached PHP GREEN at `cdb5b2a146c162c76885ef3f3ef9f7a4ca89974f` / CI `33927328641`: PHPStan 0 errors; PHPUnit 399/399, 1,849 assertions; Composer audit clean.
- Independent review found two Important issues: `VectorStoreRegistry` did not enforce/expose managed interface truth, and health treated non-ready OpenAI vector-store statuses as healthy.
- Genuine review RED `13810bf7f8ee516ecee4138ba585c6cf2eccb21f` / CI `33927625909`: PHPStan 0 errors; PHPUnit 402 tests / 1,853 assertions with exactly 1 error + 2 failures for the intended findings.
- Final implementation GREEN `dfbae21e1a4226a7ce285413a74b817e1089ce78` / CI `33927746937`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 402/402, 1,854 assertions; Composer audit clean; full WordPress smoke green.
- Package artifact `9957422625`, digest `sha256:dfef7a3064fdb8cc2bb90958481169d087f35767c46a28470bf8fcdb59808167`.
- Independent Task 8 review PR #11 review `5118469428`: **Critical 0 / Important 0 unresolved**; no unresolved review threads.

## Exact next unfinished action

Begin M08 Task 9 — M07 plan-to-embedding/vector integration and whole-M08 closeout. Establish a lint-clean test-only behavioral RED proving accepted M07 indexing plans are transformed into bounded embedding requests and deterministic vector records under the selected embedding/index profile without bypassing compatibility, collection, metadata, or stable-ID contracts. Implement the minimum orchestration glue only; do not introduce M09 queue/retry execution or M10 hybrid retrieval. Then complete the required security/performance review and benchmark, add regression REDs for every Critical/Important finding, update durable evidence, perform whole-M08 independent review, require exact-final-head permanent CI green, mark PR #11 ready only when all milestone criteria are satisfied, merge only with expected exact SHA, and verify post-merge `main` CI before declaring M08 complete.