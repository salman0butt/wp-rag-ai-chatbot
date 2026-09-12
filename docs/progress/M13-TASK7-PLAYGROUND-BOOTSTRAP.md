# M13 Task 7 — Playground bootstrap evidence

Status: **IN PROGRESS**

This checkpoint records the browser bootstrap/submission and live loading-state TDD cycles for the M13 Playground UI. Task 7 is not complete and this document must not be treated as a closeout record.

## Scope

The browser must submit only the bounded Task 6 request DTO (`bot_id`, positive `source_id`, `collection_id`, `question`) through the existing same-origin nonce-authenticated admin client to `POST /admin/debug/playground`, then render only the bounded Task 6 response DTO. No retrieval, scoring, provider selection, embedding selection, vector-store selection, prompt construction, or other RAG authority belongs in the browser.

## Submission TDD chronology

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

The original submission behavior is genuine GREEN: form submission reaches the protected Task 6 route through the existing admin client, and the bounded result is rendered.

## Review finding — runtime/panel authority bypass

Scoped correctness/security/performance/accessibility/architecture review found an unresolved **Important** issue after behavioral GREEN:

- Task 7 already contains the composed `createPlaygroundApi -> createPlaygroundController -> createPlaygroundRuntime` state authority and `PlaygroundPanel` rendering boundary.
- The bootstrap GREEN path duplicated that orchestration directly inside `src-js/index.ts` and rendered `PlaygroundScreen` itself.
- This bypassed the existing live `loading` status projection owned by `PlaygroundPanel`, leaving a Task 7 accessibility/async-status acceptance criterion incomplete.

The behavior was green, but the subunit was not review-complete.

## Loading-state review-resolution TDD chronology

### Genuine RED

- SHA: `cd7fc7cac50de78124f00730cf68c75b2e4ffef2`
- CI: `34678022604`
- `php-quality`, `package`, and `wordpress-smoke` all passed.
- `js-quality` passed dependency audit, JavaScript lint, and TypeScript typecheck before reaching Jest.
- Jest ran 34 suites / 79 tests: 33 suites / 78 tests passed and only `src-js/playground-bootstrap.test.ts` failed.
- Intended failure: while the Playground request promise remained unresolved, `[data-playground-status="loading"]` was absent from the live admin DOM.

This is a genuine behavioral RED.

### Implementation transport/tooling checkpoints — NOT GREEN

Several bounded one-shot GitHub Actions runner attempts were used because the local write/test transport was unavailable. Their failures were formatter/tooling failures rather than behavioral evidence. They are retained in history and are neither RED nor GREEN.

The final bounded runner used the repository's own `wp-scripts lint-js ... --fix` configuration, applied only the intended `src-js/index.ts` refactor, ran full `npm run verify:js` successfully, removed itself, and produced:

- implementation SHA: `0946678b6621971029a3fbb73e00e8b65e0c71ae`
- change: route bootstrap Playground state/submission through `createPlaygroundRuntime` and render through `PlaygroundPanel`; remove the duplicated direct request/result/error orchestration.
- dedicated runner: `34679259264` — all runner steps succeeded, including full `npm run verify:js`.
- permanent PR CI attempt: `34679296036` — GitHub concluded `action_required` with zero runnable jobs because the implementation commit was authored by `github-actions[bot]` through `GITHUB_TOKEN`.

Therefore `0946678b6621971029a3fbb73e00e8b65e0c71ae` is explicitly **NOT GREEN** under the repository exact-head permanent-CI policy despite the successful bounded runner verification. No GREEN claim is made for that SHA.

### Permanent-CI verification checkpoint

This documentation commit is intentionally the user-authored verification trigger for the implementation tree. It must not be called GREEN until all four permanent jobs complete successfully on its exact SHA.

## Scoped review of the runtime/panel refactor

Correctness:
- the bootstrap now delegates submission/state transitions to the existing `createPlaygroundRuntime` authority;
- loading, success, and error state are projected through the existing `PlaygroundPanel` instead of a second bootstrap-owned state machine;
- the request continues to use the existing same-origin nonce-authenticated `AdminApiClient` and bounded Task 6 DTO.

Security:
- no credentials, provider/model overrides, embedding overrides, vector-store options, retrieval-limit overrides, or new request fields were introduced;
- no server-side RAG authority moved into the browser.

Performance/architecture:
- duplicated direct Playground request/result/error orchestration was removed;
- the refactor reuses the existing runtime/controller/panel authority and does not add retrieval/generation work.

Accessibility:
- `PlaygroundPanel` owns the loading announcement with `role="status"`, `aria-live="polite"`, and `data-playground-status="loading"`.

Independent reviewer/subagent transport was unavailable for this checkpoint and is not falsely claimed. The repository-approved scoped fallback review found 0 unresolved Critical findings and, subject to exact-head permanent CI, resolves the prior Important runtime/panel-authority finding.

## Remaining Task 7 work

After this exact-head permanent-CI checkpoint is genuinely GREEN:

1. re-check reviews/concurrency and continue immediately rather than stopping;
2. add strict-TDD coverage for latest-request/concurrency correctness, including stale in-flight results across navigation/submission races;
3. harden long diagnostic content, keyboard access, responsive layout, and final accessibility/security/performance review;
4. close Task 7 only after all acceptance criteria, final review, durable documentation, and exact-final-head CI are complete.

## Current review gate

- Critical unresolved: 0
- Important unresolved: 0 for the runtime/panel authority refactor itself, contingent on exact-head permanent CI.

Task 7 remains **IN PROGRESS**.
