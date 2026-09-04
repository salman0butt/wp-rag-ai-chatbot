# Global Status

- Completed milestones on `main`: **M00-M07**.
- Current `main` SHA: `b642813c92ee152805c16a0bd6902b4ce67e33df`.
- M07 post-closeout `main` CI: `33860207844` — all four permanent jobs passed.
- Current milestone: **M08 — Embeddings & Vector Stores — IN PROGRESS**.
- M08 branch: `feat/m08-embeddings-vector-stores`.
- M08 PR: **#11 — open draft**.
- M08 design/spec and implementation plan: **AUTO-APPROVED — SCHEDULED MODE**.
- M08 Tasks 1-4: **COMPLETE on feature branch**; Task 5 is next.

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
- Package artifact `9939828694`, digest `sha256:eb9067510c8bbf9f791c3c9448e8decf262ade7800805d53e54f9fe5ade98bb7`.
- Independent review: **Critical 0 / Important 0 unresolved**.

### Task 4 — COMPLETE

The bounded local WordPress vector store is complete on the feature branch:

- versioned V003/V004 per-site vector collection/vector tables, schema version 4, safe uninstall ordering;
- collection/fingerprint compatibility isolation;
- stable-ID replacement and collection-scoped idempotent deletion;
- prepared portable equality/membership/conjunction filter SQL without raw vendor/query escape hatches;
- database candidate narrowing before PHP cosine similarity;
- hard `candidate_limit + 1` fetch bound, bounded top-K, deterministic score-descending/stable-ID ordering;
- explicit `LOCAL_SCALE_LIMIT` failure when the configured local candidate ceiling is exceeded;
- real WordPress database smoke/integration coverage.

Genuine TDD / review evidence:

- Initial RED `a0f49b3645a26889bca6c58b0d7f2349c89427c0` / CI `33885763320`: PHPStan 0 errors; PHPUnit 353 tests / 1,535 assertions / exactly 6 intended failures for absent V003/V004 migrations, cosine scoring, and local-store bounds. JS/package/WordPress smoke green.
- Pre-review Task 4 GREEN `b58fce7c5c520a0df4dbf9cd17b6c71893934821` / CI `33893539465`: all four permanent jobs green.
- Fresh independent review found one Important issue: candidate-ceiling overflow used generic `operation_failed` instead of the design-required local scale-limit category.
- Review RED `31a9f25d6492a7df3189184487cf75eb51b70a24` / CI `33897449155`: PHPStan 0 errors; PHPUnit 358 tests / 1,575 assertions with exactly one error for absent `LOCAL_SCALE_LIMIT`.
- Review-fix GREEN `69dce20f2c7c58239d999cbb414e07c5dac100fb` / CI `33898085114`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 358/358, 1,576 assertions; Composer audit clean; full WordPress smoke green.
- Package artifact `9946564686`, digest `sha256:0753a05c42901db841a805e6c0f1305554ec56a19b4b64fb7ff7c7a9a8310740`.
- Independent review submission PR #11 review `5115843007`: **Critical 0 / Important 0 unresolved**.
- No unresolved PR review threads.

Local performance boundary: after collection/fingerprint/filter narrowing, SQL requests at most `candidate_limit + 1` rows. PHP scores at most `candidate_limit` rows; overflow fails as `LOCAL_SCALE_LIMIT`, so there is no unbounded fallback. Use the local adapter only when normal narrowing stays within the configured candidate ceiling; workloads that routinely exceed it should move to a purpose-built external vector store rather than simply raising the ceiling indefinitely.

## Exact next unfinished action

Begin M08 Task 5 — Qdrant adapter — with a test-only offline RED covering fixed/allowlisted endpoint construction, server-side credentials, collection/profile compatibility, deterministic payload mapping, stable-ID upsert/delete, portable filter translation, bounded top-K result mapping, health/capability truthfulness, normalized errors, and no automatic retries. Require genuine behavioral RED before production implementation; then GREEN, fresh independent review, full exact-SHA CI, and durable evidence before Task 6.
