# M13 Task 7 — Playground bootstrap evidence

Status: **IN PROGRESS**

This checkpoint records the browser bootstrap/submission TDD cycle for the M13 Playground UI. Task 7 is not complete and this document must not be treated as a closeout record.

## Scope

The browser must submit only the bounded Task 6 request DTO (`bot_id`, positive `source_id`, `collection_id`, `question`) through the existing same-origin nonce-authenticated admin client to `POST /admin/debug/playground`, then render only the bounded Task 6 response DTO. No retrieval, scoring, provider selection, embedding selection, vector-store selection, prompt construction, or other RAG authority belongs in the browser.

## TDD chronology

### Genuine RED

- SHA: `2e45efd7cc761ac1d20a778cf662672989846ffc`
- CI: `34676253728`
- `js-quality` reached Jest after lint and TypeScript passed.
- 33 suites passed and only `src-js/playground-bootstrap.test.ts` failed.
- Intended failure: the submitted Playground form produced no `POST /admin/debug/playground`; only the onboarding-readiness GET occurred.

This is a genuine behavioral RED.

### NOT GREEN — formatting gate

- SHA: `02b910d1722e052fb40700cfc15f6bf74002a286`
- CI: `34676864533`
- PHP and package verification passed, but `js-quality` stopped at Prettier before TypeScript/Jest could establish behavior.

This checkpoint is **NOT GREEN**.

### NOT GREEN — contaminated formatting repair

- SHA: `5279ad13c871f62d396f010f07f53cc0b3d235b5`
- Diff inspection found accidental unrelated edits in bot-management code while repairing formatting.
- The collateral edits were immediately repaired rather than hidden or rewritten.

This checkpoint is **NOT GREEN** and is retained as honest chronology.

### Behavioral GREEN

- SHA: `cc38cdc6f3d67080bca590e270857ae0b8723a71`
- CI: `34677188367`
- Exact-head permanent gates all passed:
  - `php-quality`
  - `js-quality`
  - `package`
  - `wordpress-smoke`

The original bootstrap behavior is therefore genuine GREEN: form submission reaches the protected Task 6 route through the existing admin client, and the bounded result is rendered.

## Review

Scoped correctness/security/performance/accessibility/architecture review found an unresolved **Important** issue after behavioral GREEN:

- Task 7 already contains the composed `createPlaygroundApi -> createPlaygroundController -> createPlaygroundRuntime` state authority and `PlaygroundPanel` rendering boundary.
- The current bootstrap GREEN path duplicates that orchestration directly inside `src-js/index.ts` and renders `PlaygroundScreen` itself.
- This bypasses the existing live `loading` status projection owned by `PlaygroundPanel`, so it also leaves a Task 7 accessibility/async-status acceptance criterion incomplete.

The behavior is green, but the subunit is **not review-complete**. Do not proceed past this Important finding as though Task 7 were closed.

## Required resolution

Refactor the admin bootstrap to delegate Playground submission/state through the existing `createPlaygroundRuntime` authority and render it through `PlaygroundPanel`, preserving the already-green POST/body/nonce/result behavior. The refactor must not move any server-side RAG authority into the browser.

After that refactor:

1. obtain exact-head GREEN across all permanent jobs;
2. perform scoped correctness/security/performance/accessibility/architecture review again;
3. record the review-resolution SHA and CI here;
4. continue strict TDD with the next Task 7 acceptance gap, especially latest-request/concurrency correctness and the remaining long-content/mobile/keyboard coverage.

## Current review gate

- Critical unresolved: 0
- Important unresolved: 1 — bootstrap bypasses the existing Task 7 runtime/controller/panel authority and therefore does not use its live async-status behavior.

Task 7 remains **IN PROGRESS**.