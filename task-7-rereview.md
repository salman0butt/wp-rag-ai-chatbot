# Task 7 re-review — Knowledge wizard and REST wiring

Scope: delta `a50822d..HEAD` only, with the original findings in `task-7-review.md` as the baseline.

Reviewed:

- plan: `docs/superpowers/plans/2026-09-15-modern-knowledge-publish-flow.md`
- original review: `.superpowers/sdd/2026-09-15-modern-knowledge-publish-flow/task-7-review.md`
- updated report: `.superpowers/sdd/2026-09-15-modern-knowledge-publish-flow/task-7-report.md`
- fix diff: `.superpowers/sdd/2026-09-15-modern-knowledge-publish-flow/task-7-rereview.diff`
- HEAD: `5caaed6` (`fix task 7 knowledge publish review findings`)

## Verdict

SPEC: FAIL

QUALITY: FAIL

The delta fixes the Knowledge-screen UI exposure, refresh races, nested error handling, WooCommerce default, and validation-alert clearing. One original Important contract remains open: the legacy `POST /admin/knowledge/jobs` REST endpoint still accepts browser-controlled `collection_id`, `configuration_id`, and `generation` values. The removed form no longer exposes that path in the shipped screen, but a capable authenticated browser can still call the endpoint directly.

## Findings

### Important — legacy REST enqueue still accepts browser-controlled indexing metadata

Status: OPEN / partially addressed.

The UI portion is fixed. `src-js/index.ts:932-963` now exposes only Cancel and Retry lifecycle actions, and the new regression test confirms that no `data-knowledge-job-enqueue` form or `collection_id`, `configuration_id`, or `generation` inputs render (`src-js/knowledge-job-enqueue.test.ts:116-151`).

The underlying legacy REST path remains permissive, however:

- `src/Admin/AdminRestBootstrap.php:241-255` still registers `POST /admin/knowledge/jobs`.
- `src/Admin/AdminRestBootstrap.php:642-644` forwards the request JSON directly to the job resource.
- `src/Admin/Rest/KnowledgeJobRestResource.php:68-76` only hydrates `DocumentIndexJobPayload` and enqueues it.
- `src/Jobs/Sync/DocumentIndexJobPayload.php:38-46` validates identifier grammar only; it does not resolve the source or derive/verify the persisted collection, configuration, or generation.

Therefore a user with the existing admin capability can still submit a syntactically valid payload with arbitrary server-owned indexing metadata through browser tooling. This preserves the original server-owned profile/security contract gap even though the normal Knowledge screen no longer provides the form. Cancel/Retry remains available at `src-js/index.ts:932-963` and through the separate lifecycle route at `src/Admin/AdminRestBootstrap.php:258-265`.

### Important — job refresh latest-request-wins and stale action behavior

Status: ADDRESSED.

Evidence:

- `src-js/index.ts:2007-2030` assigns each jobs request a generation and captures the current hash; stale success and rejection return `false` without mutating current jobs or throwing into the caller.
- `src-js/index.ts:2266-2271` invalidates jobs and lifecycle generations on every hash change.
- `src-js/index.ts:2293-2306` and `2365-2377` clear job state when leaving/reloading Knowledge.
- `src-js/index.ts:2524-2559` guards lifecycle success, refresh, readiness, and rejection handling with a mutation generation plus current-hash checks.
- `src-js/knowledge-navigation-race.test.ts:290-430` covers stale job success and rejection after navigation.
- `src-js/knowledge-job-actions.test.ts:238-312` covers an older Cancel response not overwriting the latest job inventory.

### Important — nested PHP error envelopes for JSON/FormData and lifecycle actions

Status: ADDRESSED.

Evidence:

- `src-js/index.ts:70-92` recognizes both top-level and nested `{ error: { code } }` responses.
- `src-js/index.ts:100-133` applies the check to JSON requests.
- `src-js/index.ts:135-162` applies the same check to FormData requests while preserving the multipart headers/body behavior.
- `src-js/index.ts:2534-2559` routes lifecycle failures through the safe mutation-error mapping instead of refreshing as if the action succeeded.
- `src-js/knowledge-job-errors.test.ts:219-268` verifies a successful-HTTP nested envelope is treated as `invalid_transition` and does not render backend detail.

The FormData path is covered by source symmetry and the existing request-shaping test; no live multipart REST smoke was available.

### Minor — WooCommerce availability is server-derived with safe false behavior

Status: ADDRESSED.

Evidence:

- `src/Admin/AdminBootstrap.php:78-83` derives the flag from the WooCommerce functions and exposes only the boolean admin value.
- `src-js/index.ts:1721-1729` uses `config.woocommerceAvailable === true`, so missing/malformed config is false.
- `src-js/knowledge-wizard.ts:336` defaults the component to false; `:390-400` and `:597-600` disable the WooCommerce card when unavailable; `:511-514` validates against the same value.
- `tests/Unit/Admin/AdminSurfaceTest.php:95-138` verifies the safe false bootstrap value.
- `src-js/knowledge-wizard.test.ts:259-298` verifies the default-disabled card and unavailable validation.

### Minor — corrected validation alert and field-invalid state

Status: ADDRESSED at source/unit-test level.

Evidence:

- `src-js/knowledge-wizard.ts:538-544` clears the alert text, hides it, and removes stale `aria-invalid` state before a valid request starts.
- `src-js/knowledge-wizard.test.ts:259-298` verifies the alert is visible for invalid input and empty/hidden after correction before `onCreate` runs.

No live WordPress/browser smoke was available, so this remains source/test evidence rather than browser evidence.

## Requirement checks

| Requirement | Result | Evidence |
|---|---|---|
| 1. Legacy enqueue no longer permits browser-controlled collection/configuration/generation while retry/cancel remains | FAIL | The form and inputs are removed and retry/cancel remain, but the legacy protected POST still forwards arbitrary payload metadata (`AdminRestBootstrap.php:241-255,642-644`; `KnowledgeJobRestResource.php:68-76`). |
| 2. Job refresh latest-request-wins/current-hash invalidation and stale success/rejection/action behavior | PASS | `index.ts:2007-2030,2266-2271,2524-2559`; navigation/action regression tests pass. |
| 3. Nested PHP error envelope fails JSON/FormData and lifecycle actions | PASS | `index.ts:70-162,2534-2559`; nested lifecycle regression passes. |
| 4. Woo availability server-derived and safe false; validation alert clears | PASS | `AdminBootstrap.php:78-83`; `index.ts:1721-1729`; `knowledge-wizard.ts:336,538-544`; focused tests pass. |
| 5. No new public/runtime/capability/profile/security regressions; authoritative wizard/no optimistic completion | PASS in scoped source checks; live unverified | Delta changes admin wizard/client/bootstrap only; no widget, shortcode, block, or public runtime files changed. Source creation refreshes server source/jobs at `index.ts:2131-2187`; no optimistic indexing completion is assigned. Build and automated compatibility checks pass. |

## Addressed/open summary

### Critical

None.

### Important

- Addressed: job refresh latest-request-wins and stale lifecycle handling.
- Addressed: nested PHP error envelopes for JSON/FormData and lifecycle actions.
- Open: legacy REST enqueue still accepts browser-controlled server-owned indexing metadata; the UI-only removal is insufficient for the full contract.

### Minor

- Addressed: server-derived WooCommerce availability with safe false default.
- Addressed: corrected validation clears the alert and stale field-invalid state at source/unit-test level.

## Verification evidence

Fresh checks run in the reviewed worktree:

- `npx wp-scripts test-unit-js src-js/knowledge-job-actions.test.ts src-js/knowledge-job-enqueue.test.ts src-js/knowledge-job-errors.test.ts src-js/knowledge-navigation-race.test.ts src-js/knowledge-wizard.test.ts --runInBand` — 5 suites, 19 tests passed.
- `npm run test:js -- --runInBand` — 71 suites, 198 tests passed.
- `vendor/bin/phpunit tests/Unit/Admin/KnowledgeSourceCreateResourceTest.php tests/Unit/Admin/KnowledgeSourceRoutesTest.php tests/Unit/Admin/KnowledgeJobRoutesTest.php tests/Unit/Admin/KnowledgeJobRestResourceTest.php tests/Unit/Admin/KnowledgeSourceRestResourceTest.php tests/Unit/Admin/KnowledgeDetailRoutesTest.php tests/Unit/Admin/KnowledgeDetailRestResourceTest.php tests/Unit/Admin/AdminSurfaceTest.php` — 53 tests, 187 assertions passed.
- `vendor/bin/phpcs src/Admin/AdminBootstrap.php tests/Unit/Admin/AdminSurfaceTest.php` — passed.
- `vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --no-progress --memory-limit=512M src/Admin/AdminBootstrap.php tests/Unit/Admin/AdminSurfaceTest.php` — no errors.
- `npm run lint:js` — passed.
- `npm run typecheck` — passed.
- `npm run build` — passed; administrator, widget, and block bundles compiled.
- `git diff --check` — passed.

Watchman emitted a non-failing recrawl warning during Jest. This is an evidence limitation only, not a test failure.

No live WordPress/wp-env/browser smoke test was run. Live queue execution, real REST status/envelope behavior, provider behavior, WooCommerce runtime availability, public bootstrap secrecy, shortcodes, the dynamic block, and frontend chat remain unverified and are evidence limitations only.

No production code was edited during this re-review; only this report was written.
