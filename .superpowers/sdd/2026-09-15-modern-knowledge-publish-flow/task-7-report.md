# Task 7 report — Knowledge wizard and REST wiring

## Implementation

- Added `src-js/knowledge-wizard.ts` with source-type cards and validation for WordPress defaults, manual text, FAQ rows, WooCommerce catalog/selection, and multipart files.
- Added safe JSON/FormData request shaping without provider, embedding, collection, path, or raw payload metadata.
- Added accessible validation/error/status regions, submit progress, duplicate-submit protection, safe server errors, source/job cards, retry/cancel actions, and bounded inspection links.
- Wired source creation through the existing admin client and knowledge page/job request-generation flow. Source and job status is refreshed from the server after creation; indexing completion is never optimistic.
- Added scoped wizard CSS and preserved legacy knowledge routes and behavior.
- Removed the legacy browser-controlled `index.document` enqueue form from the Knowledge screen, including collection/configuration/generation inputs; server-created source-sync jobs remain visible with safe retry/cancel controls.
- Added nested `{ error: { code, message } }` envelope handling to both JSON and multipart admin requests so successful HTTP responses cannot hide lifecycle failures.
- Added current-hash/request-generation/latest-request-wins guards for job refreshes, invalidated on navigation and ignored on stale success/rejection.
- Added lifecycle-action generations so stale retry/cancel responses cannot start a later job refresh or replace the current safe error state.
- Wired server-derived WooCommerce availability into the admin boot config with a safe false default; unavailable sites disable the WooCommerce card and explain why.
- Cleared corrected validation alerts before a successful request and removed stale field-invalid state.

## TDD evidence

- RED: `npx wp-scripts test-unit-js src-js/knowledge-wizard.test.ts --runInBand` failed because `src-js/knowledge-wizard.ts` did not exist.
- Round 1 RED: the focused regression suite initially reported 4 failing suites, 6 failing tests, and 11 passing tests for the legacy enqueue form, stale job success/rejection, nested lifecycle envelopes, WooCommerce default availability, and validation-alert clearing.
- Lifecycle RED: `npx wp-scripts test-unit-js src-js/knowledge-job-actions.test.ts --runInBand` failed the new stale-action regression before the lifecycle-action generation guard was added.
- GREEN: the final focused regression suite passes.

## Verification

- `npx wp-scripts test-unit-js src-js/knowledge-job-actions.test.ts src-js/knowledge-job-enqueue.test.ts src-js/knowledge-job-errors.test.ts src-js/knowledge-navigation-race.test.ts src-js/knowledge-wizard.test.ts --runInBand` — 5 suites, 19 tests passed.
- `npm run test:js -- --runInBand` — 71 suites, 198 tests passed.
- `vendor/bin/phpunit tests/Unit/Admin/KnowledgeSourceCreateResourceTest.php tests/Unit/Admin/KnowledgeSourceRoutesTest.php tests/Unit/Admin/KnowledgeJobRoutesTest.php tests/Unit/Admin/KnowledgeJobRestResourceTest.php tests/Unit/Admin/KnowledgeSourceRestResourceTest.php tests/Unit/Admin/KnowledgeDetailRoutesTest.php tests/Unit/Admin/KnowledgeDetailRestResourceTest.php tests/Unit/Admin/AdminSurfaceTest.php` — 53 tests, 187 assertions passed.
- `vendor/bin/phpcs src/Admin/AdminBootstrap.php tests/Unit/Admin/AdminSurfaceTest.php` — passed.
- `vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --no-progress --memory-limit=512M src/Admin/AdminBootstrap.php tests/Unit/Admin/AdminSurfaceTest.php` — passed with no errors.
- `npm run lint:js` — passed.
- `npm run typecheck` — passed.
- `npm run build` — passed; administrator, widget, and block bundles compiled.
- `git diff --check` — passed.

Jest emitted a non-failing Watchman recrawl warning during the JavaScript runs. PHPStan with its default 128 MB limit crashed before analysis; the same changed-file analysis passed with the explicit 512 MB limit.

## Limitations

- No live WordPress/wp-env/browser smoke test was run for this review round. Live queue/provider/WooCommerce behavior remains unverified.

## Round 2 fix — server-owned indexing boundary

- Replaced the protected `POST /admin/knowledge/jobs` browser-controlled payload path with an identifier-only boundary. The REST resource now resolves the persisted document and source, requires source/document lineage, requires a current non-empty source generation, validates the persisted fixed semantic profile through `WordPressDocumentIndexDependencies`, and constructs the `DocumentIndexJobPayload` server-side.
- Browser-supplied `collection_id`, `configuration_id`, `generation`, and any other unexpected fields are rejected with the existing safe `invalid_request` envelope before the queue repository is called.
- Retry re-derives the current server-owned payload from persisted lineage as well; cancel and the existing lifecycle routes remain unchanged.
- Updated the REST resource factory and the persisted-job integration fixture to provide the existing source/document repository seams. No provider or network work is performed by the admin request path.

## Round 2 TDD evidence

- RED: `vendor/bin/phpunit tests/Unit/Admin/KnowledgeJobRestResourceTest.php --filter test_enqueue_rejects_browser_supplied_indexing_metadata` failed before the production change because the legacy resource called `JobRepository::enqueue()` with the browser payload; the test expected that mutation seam never to be called.
- GREEN: `vendor/bin/phpunit tests/Unit/Admin/KnowledgeJobRestResourceTest.php` — 7 tests, 51 assertions passed.

## Round 2 verification

- `npx wp-scripts test-unit-js src-js/knowledge-job-actions.test.ts src-js/knowledge-job-enqueue.test.ts src-js/knowledge-job-errors.test.ts src-js/knowledge-navigation-race.test.ts src-js/knowledge-wizard.test.ts --runInBand` — 5 suites, 19 tests passed.
- `npm run test:js -- --runInBand` — 71 suites, 198 tests passed.
- `vendor/bin/phpunit tests/Unit/Admin/KnowledgeSourceCreateResourceTest.php tests/Unit/Admin/KnowledgeSourceRoutesTest.php tests/Unit/Admin/KnowledgeJobRoutesTest.php tests/Unit/Admin/KnowledgeJobRestResourceTest.php tests/Unit/Admin/KnowledgeSourceRestResourceTest.php tests/Unit/Admin/KnowledgeDetailRoutesTest.php tests/Unit/Admin/KnowledgeDetailRestResourceTest.php tests/Unit/Admin/AdminSurfaceTest.php` — 54 tests, 195 assertions passed.
- `vendor/bin/phpunit` — 927 tests, 3,829 assertions passed.
- `vendor/bin/phpcs` — passed with no errors or warnings.
- `vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --no-progress --memory-limit=512M` — passed with no errors.
- `npm run lint:js` — passed.
- `npm run typecheck` — passed.
- `npm run build` — passed; administrator, widget, and block bundles compiled.
- `git diff --check` — passed.

The first post-change full PHP run caught one stale three-argument `KnowledgeJobRestResource` construction in `tests/Integration/KnowledgeJobAdminTest.php`; the fixture was updated to pass the existing source/document repository seams and the final full run above passed. The default PHPStan memory limit remains insufficient in this environment; the final analysis used the explicit 512 MB limit. JavaScript runs emitted only the existing non-failing Watchman recrawl warning.

Live WordPress/wp-env/browser smoke, live queue execution, provider calls, and WooCommerce runtime behavior remain unverified.

Commit: implementation committed in the final VCS commit for this worktree.
