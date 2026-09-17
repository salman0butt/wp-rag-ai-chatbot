# Task 8 re-review

Base: `973097a`
HEAD: `9f29e20` (`Fix Task 8 review round 2 findings`)
Scope: delta `973097a..HEAD`, checked against the original Task 8 review and the approved plan.

## Result

SPEC: PASS

QUALITY: PASS

No Critical, Important, or Minor findings identified in this delta.

## Original findings rechecked

### 1. Retrieval/source invalidation and race handling: PASS

- Navigation away from Chatbots now increments both retrieval and source request generations, clears their projections/status/error state, and does so before the Overview early return (`src-js/index.ts:2677-2710`).
- Returning to Chatbots reloads when retrieval is not `ready` or sources are not `ready`, so an in-flight initial load, save, or disconnect cannot leave the controller stuck on stale state (`src-js/index.ts:2769-2800`).
- Retrieval and source loaders reject stale responses using generation, screen, and active-bot checks (`src-js/index.ts:1979-2045`). Save/disconnect use the same retrieval generation guard (`src-js/index.ts:2962-3036`).
- The presentational binding remains mounted during retrieval loading/failure and source loading/failure, with separate status/error projections. Loading uses a polite status region; safe request failures use an alert region; source-list loading is distinct from the empty-source prompt (`src-js/bot-knowledge-binding.ts:97-128`, `156-176`, `201-224`).

### 2. Publish/Test readiness and selected bot: PASS

- `BOT_ID` fallback was removed. No bot means no publish cards; a direct route resolves and verifies a persisted bot DTO, while the base Publish/Test route loads persisted bot choices and selects a persisted bot (`src-js/index.ts:576-588`, `2097-2184`).
- Publish readiness combines aggregate server readiness with selected-bot server-derived state: enabled bot, provider-specific generation model availability, retrieval configuration, and collection readiness (`src-js/index.ts:2147-2173`, `src-js/admin-ui.ts:18-30`).
- Snippets use the verified selected bot ID and copy buttons remain disabled unless every prerequisite is true (`src-js/admin-ui.ts:301-356`, `408-431`).
- Aggregate `publishable_bot_present` remains overview/header information; Publish/Test uses the selected bot boolean rather than treating aggregate readiness as proof for the copied bot (`src-js/admin-ui.ts:18-30`, `src-js/index.ts:1530-1542`).
- Existing `#/playground` routing and production chat-path behavior remain outside the changed publish UI contract; the full JS suite and build passed.

### 3. REST boundaries, safe data, and regressions: PASS

- REST remains in the bootstrap/controller/API helpers. Presentational components receive projections and callbacks only.
- Binding `PUT` sends exactly `{ source_id }`; `DELETE` has no body (`src-js/bot-knowledge-binding.ts:49-65`).
- Source choices are projected from the persisted knowledge-source DTO list and are disabled while the list is loading, failed, or empty (`src-js/index.ts:1979-2010`, `src-js/bot-knowledge-binding.ts:201-224`).
- The changed publish UI receives only safe bot option fields, selected ID, booleans, status, and safe error text. No credentials, server paths, raw provider payloads, or new public/runtime contracts are introduced by this delta.

### 4. Fixture/coverage quality: PASS

- The delta adds assertions for Overview → Chatbots retrieval reloads, in-flight save invalidation, stale response suppression, retrieval/source failures, safe save/disconnect failures, no fallback identifier, and selected-bot publish gating (`src-js/task-8-round-2.test.ts`, `src-js/bot-knowledge-binding.test.ts`).
- Existing publish assertions were updated only to provide the new explicit selected-bot readiness input; no assertions were removed or weakened.
- The delta contains no production PHP, public widget, shortcode, or block changes.

## Verification

- Focused JS: 3 suites, 17 tests passed.
- Full JS: 73 suites, 210 tests passed.
- JS lint: passed.
- TypeScript typecheck: passed.
- Build: passed; no generated tracked files changed.
- PHP coding standards: passed.
- PHPStan: passed with no errors.
- Full PHP: 927 tests, 3,829 assertions passed.
- `git diff --check 973097a..HEAD`: passed.

## Evidence limitations

- Watchman emitted a recrawl warning during Jest; all affected test runs completed successfully, so this is harness noise only.
- No live WordPress/wp-env smoke test was run. This limits live-environment evidence only and is not an application finding.
