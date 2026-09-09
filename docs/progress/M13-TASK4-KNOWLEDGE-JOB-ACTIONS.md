# M13 Task 4 — Knowledge job lifecycle UI evidence

Status: **bounded cancel/retry slice complete; Task 4 remains ACTIVE**.

## Scope

This slice integrates the existing Task 3 lifecycle contracts into the Knowledge admin UI without creating a new API or browser-side job model.

- queued/running jobs expose a keyboard-focusable `Cancel` button;
- failed jobs expose a keyboard-focusable `Retry` button;
- mutations use the existing nonce-authenticated same-origin admin client;
- job keys are URI-encoded in mutation paths;
- a successful mutation is followed by a fresh bounded `GET /admin/knowledge/jobs?page=1&per_page=20` request before rerendering;
- the browser continues to hold/render only the existing allow-listed Task 3 job DTO. Payload, idempotency, lease/lock and raw provider internals are not added to client state.

Enqueue, explicit `invalid_transition` presentation, and final loading/error/responsive closeout remain separate Task 4 work.

## TDD evidence

### Genuine RED

Commit `8ebfd5379e93de778f16ac78800c05b641e91d8c` formatted only the lifecycle test fixture so verification could reach Jest.

JavaScript verification then passed lint and TypeScript typecheck and executed 21 suites / 55 tests. Exactly two tests failed (53 passed):

1. queued job `job-queued` had no `button[data-knowledge-job-action="cancel"]`;
2. failed job `job-failed` had no `button[data-knowledge-job-action="retry"]`.

No production lifecycle UI code existed at this RED checkpoint.

### Non-GREEN implementation checkpoints

Temporary branch-scoped patch-runner checkpoints were used only because the normal local patch transport was transiently unavailable. They never committed production source when verification failed.

- `157f73db8a296d8504ffa29b876e35caead6cf52` / run `34321582894`: production patch applied in the runner, but repository verification stopped on Prettier findings before typecheck/Jest. Not GREEN.
- `bc6e5407e0c155e3933d3de68c8bb4d9a2eb3956` / run `34321684507`: raw Prettier used incompatible defaults and caused repository style failures before behavioral verification. Not GREEN.

The transient workflow was removed by the verified production commit and is not part of the durable product surface.

### GREEN

Implementation commit `9eb9a5344c68a8b84b2fd43d7a7fddc2aefade63` was produced only after runner `34321869920`, job `102370108021`, passed the repository's own formatter and full `npm run verify:js`:

- engine/package lint: PASS;
- JavaScript lint: PASS;
- TypeScript typecheck: PASS;
- Jest: 21/21 suites, 55/55 tests PASS;
- build: PASS;
- Pinecone live-gating check: PASS;
- Chroma live-gating check: PASS.

The verified commit deletes the transient patch workflow in the same commit.

## Scoped review

Coordinator correctness/security/accessibility/performance review: **0 Critical / 0 Important**.

### Correctness

- Controls are state-constrained: cancel for queued/running; retry for failed.
- Mutation routes reuse the established Task 3 endpoints and `POST` semantics.
- Successful mutations refetch bounded inventory server-authoritatively before rerendering instead of relying on optimistic state.
- Mutation failures do not fabricate a new job state; the existing generic admin error state is used for this slice. Stable `invalid_transition` rendering remains explicit follow-up work.

### Security

- Existing same-origin nonce-authenticated admin client is reused.
- Job keys are URI-encoded before inclusion in mutation paths.
- No new credential, source config/hash, raw document, raw provider, queue payload, idempotency, lease or lock data is exposed to the browser.
- The existing allow-listed Task 3 job DTO remains the only inventory/mutation response shape consumed by the UI.

### Accessibility

- Lifecycle actions are native `button` elements with `type="button"` and visible `Cancel` / `Retry` labels.
- Job context remains visible in the surrounding row, so keyboard users can reach the action without a custom interaction primitive.

### Performance

- Rendering remains linear over the already bounded job page.
- Each lifecycle action performs one mutation plus one bounded server-authoritative inventory refresh; no polling or unbounded cache is introduced.

Independent subagent review transport was unavailable during this slice, so no independent-review result is claimed. Final Task 4 independent closeout review remains required.

## Exact continuation

Start a fresh genuine Jest RED for the existing Task 3 enqueue contract (`POST /admin/knowledge/jobs`). After enqueue GREEN, add a separate RED/GREEN for stable `invalid_transition` and sanitized mutation errors, then explicit loading/empty/error behavior, constrained-width/long-content responsive coverage, keyboard/accessibility coverage, final Task 4 review, and exact-final-SHA CI before Task 5.
