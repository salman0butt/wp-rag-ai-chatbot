# Global Status

- Completed milestones on `main`: **M00-M07**.
- Current `main` SHA: `b642813c92ee152805c16a0bd6902b4ce67e33df`.
- M07 post-closeout `main` CI: `33860207844` — all four permanent jobs passed.
- Current milestone: **M08 — Embeddings & Vector Stores — IN PROGRESS**.
- M08 branch: `feat/m08-embeddings-vector-stores`.
- M08 PR: **#11 — open draft**.
- M08 design/spec and implementation plan: **AUTO-APPROVED — SCHEDULED MODE**.
- M08 Tasks 1-5: **COMPLETE on feature branch**; Task 6 is next.

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

The bounded local WordPress vector store is complete on the feature branch: versioned vector tables, collection/profile isolation, stable-ID replacement, idempotent delete, prepared portable filters, database narrowing before PHP cosine similarity, hard candidate bounding, deterministic ordering, explicit `LOCAL_SCALE_LIMIT`, and WordPress integration coverage.

- Initial RED `a0f49b3645a26889bca6c58b0d7f2349c89427c0` / CI `33885763320`: PHPStan 0 errors; PHPUnit 353 tests / 1,535 assertions / exactly 6 intended failures.
- Review RED `31a9f25d6492a7df3189184487cf75eb51b70a24` / CI `33897449155`: one intended missing `LOCAL_SCALE_LIMIT` error.
- Review GREEN `69dce20f2c7c58239d999cbb414e07c5dac100fb` / CI `33898085114`: all four permanent jobs green; PHPUnit 358/358, 1,576 assertions; Composer audit clean; full WordPress smoke green.
- Independent review submission PR #11 review `5115843007`: **Critical 0 / Important 0 unresolved**.

### Task 5 — Qdrant adapter — COMPLETE

Delivered an offline-testable Qdrant raw-vector adapter with:

- validated administrator-owned HTTPS origins and server-side API keys;
- zero redirects and exactly one transport send per adapter request, with no automatic retries;
- compatibility-isolated physical collections plus remote dimensions/distance verification before operations;
- deterministic plugin stable-ID → Qdrant UUID mapping for upsert/delete;
- portable equality/membership/conjunction filter translation and compatibility-fingerprint filtering;
- bounded `top_k` search mapping with deterministic score-descending/stable-ID ordering;
- fail-closed validation of untrusted result fingerprints, metadata, stable IDs, and response cardinality;
- sanitized errors that do not expose remote response bodies or credentials;
- truthful health/capabilities behavior;
- an explicit default-off live Qdrant health contract hook gated by `WP_RAG_AI_LIVE_QDRANT_TESTS=1`, `QDRANT_URL`, and `QDRANT_API_KEY`; normal CI performs no Qdrant network call.

Genuine TDD / review evidence:

- Initial RED `8cb3d00aa13edf33ac4c41ae0aceee5f90391c20` / CI `33900244344`: PHPStan 0 errors; PHPUnit 366 tests / 1,584 assertions / exactly 8 intended Qdrant failures because Task 5 production contracts were absent.
- Runtime-boundary fix GREEN `b8f7b3772e0cb6888084b0ea899f641c55029320` / CI `33907850449`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 366/366, 1,634 assertions; Composer audit clean.
- Independent review Important #1: Qdrant search response compatibility fingerprints were not verified. Review RED `4786d836258bec795dd18a2256ef3789ae7724db` / CI `33908272642`: PHPStan 0 errors; PHPUnit 367 tests / 1,637 assertions / exactly one intended failure. Fix `a6ab35993216549ccad4419c67272585e3e2bcd4` validates the returned fingerprint fail-closed.
- Independent review Important #2: an untrusted Qdrant response could exceed the caller's bounded `top_k`. Review RED `955605e5590e89b4fdd81b7db7ec8e20badf3668` / CI `33908789909`: PHPStan 0 errors; PHPUnit 368 tests / 1,641 assertions / exactly one intended failure. Fix path ending at `0fbbda7cf057a50cc01d3ffd08ab0e716ad359a5` enforces the response cardinality bound.
- Live-hook RED `251fdf8fce62c2378e678c9465cf9cb3b0efd2bd` / CI `33909386169`: normal JS verification passed, then `test:qdrant:live-gating` failed exactly because the planned live Qdrant wrapper did not yet exist.
- Final Task 5 GREEN `974a9fb64a1bc66864b0cad82fe42a22225608c5` / CI `33909554302`: `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all green; PHPStan 0 errors; PHPUnit 368/368, 1,642 assertions; Composer audit clean; full WordPress smoke green; offline live-Qdrant gating passed.
- Package artifact `9950829684`, digest `sha256:e701359c6b955a54de73a0e9402db4d528f2e04049b2e6b584f4c0fbc2484958`.
- Independent Task 5 review submission PR #11 review `5116935253`: **Critical 0 / Important 0 unresolved**.
- No unresolved PR review threads.

## Exact next unfinished action

Begin M08 Task 6 — Pinecone adapter — with a test-only offline RED against the common vector-store contracts and a fake transport. Cover fixed/validated endpoint construction, server-side credentials, namespace/collection and compatibility isolation, deterministic stable-ID upsert/delete, portable filter mapping, bounded top-K search/result validation, health/capability truthfulness, sanitized errors, and no automatic retries. Require genuine behavioral RED before production implementation, then GREEN, fresh independent review, exact-SHA permanent CI, and durable Task 6 evidence before Chroma Task 7.
