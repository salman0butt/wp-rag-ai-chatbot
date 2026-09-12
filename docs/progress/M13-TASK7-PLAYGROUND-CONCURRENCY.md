# M13 Task 7 — Playground concurrency evidence

Status: **IN PROGRESS**

This checkpoint records Task 7 latest-request-wins behavior plus route-lifecycle invalidation for in-flight Playground submissions. It is not Task 7 closeout evidence.

## Scope

The browser continues to submit only the bounded Task 6 DTO through the existing Playground API/runtime/controller path. Concurrency control belongs in the UI controller/runtime and must not create new retrieval, generation, provider, embedding, vector-store, or scoring authority.

## Prerequisite loading-state checkpoint

- Integrated runtime/panel verification head: `8504cd38e9914524191267afeaab5557e718042d`
- CI: `34679441476`
- Permanent gates: `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all GREEN.

This confirms the bootstrap delegates loading/success/error state through the existing runtime/controller/panel authority before the concurrency cycle began.

## Latest-submission TDD chronology

### Genuine RED

- SHA: `ff0a7e958a77ae90a12eaa69a4694df38348cef3`
- CI: `34679600807`
- JavaScript lint and TypeScript completed successfully before Jest.
- Jest ran 34 suites / 80 tests: 33 suites / 79 tests passed.
- Only `src-js/playground-controller.test.ts` failed.
- Intended failure: after the second request completed successfully, the delayed first request completed and overwrote the newer result with `Stale first answer.`

This is a genuine behavioral RED.

### Genuine GREEN

- SHA: `b8d6265d8ba87d86624a8985c9be4539713514c7`
- CI: `34679662984`
- Exact-head permanent gates all passed:
  - `php-quality`
  - `js-quality`
  - `package`
  - `wordpress-smoke`

The controller now owns a monotonically increasing submission generation. Each submit announces loading, but only the latest generation may publish success or error. Older completions return without changing visible state.

## Request invalidation TDD chronology

The next unit required an explicit way to advance the controller generation without starting another request so a route lifecycle can invalidate an in-flight completion.

### Invalid RED checkpoints

The following test-only checkpoints are preserved honestly and are **NOT RED** because the composite JavaScript quality gate stopped before reliable evidence that Jest reached only the intended missing behavior:

- `51368b7d1febd958ae1537fea25da112e08ee9b4` / CI `34680278860` — the test referenced the missing method through the current production type, allowing TypeScript to block before the behavioral assertion;
- `80fb407db5e9b55ac9dcfd6f8d84424e2d3f8915` / CI `34680350176` — the cast-based repair still failed too early inside `verify:js` to establish intended behavioral RED;
- `79e0a780ffce286df892e235430b5a7d066680a4` / CI `34680425090` — the reflection variant remained ambiguous and therefore is not promoted to RED.

No production implementation was added at any of these checkpoints.

### Genuine RED

- SHA: `b4969c09486b887c838583535b1809f9f88b0878`
- CI: `34680531954`
- The test adopted the repository-proven `jest.requireActual` loading pattern.
- Lint and TypeScript passed before Jest.
- Jest ran 35 suites / 81 tests: 34 suites / 80 tests passed.
- Only `src-js/playground-invalidation.test.ts` failed because `controller.invalidate` was absent (`typeof ...` was `undefined`, expected `function`).

This is the genuine behavioral RED for explicit invalidation.

### Genuine GREEN

- SHA: `1ec642894735a0bfed7a427545a38c0ba2abfcc7`
- CI: `34680601250`
- Exact-head permanent gates all passed: `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.

`PlaygroundController.invalidate()` now performs only an O(1) generation increment. It does not cancel or duplicate network work, does not own UI state, and does not change request or backend authority. Any earlier resolve/reject completion becomes stale and cannot publish success/error.

## Navigation invalidation TDD chronology

A browser-level test then specified the route-lifecycle behavior: submit on Playground, navigate away while the request is pending, resolve that old request, return to Playground, and ensure the old answer is never surfaced.

### Genuine RED

- SHA: `94537f2af133b1c1429de2e38d8125999cb5fa98`
- CI: `34680762825`
- Lint and TypeScript passed before Jest.
- Jest ran 36 suites / 82 tests: 35 suites / 81 tests passed.
- Only `src-js/playground-navigation-race.test.ts` failed because `STALE_PLAYGROUND_ANSWER` remained visible after leaving and returning.

This is a genuine behavioral RED.

### Implementation checkpoint — NOT GREEN

- SHA: `19bff6a555d994f1e202002ef2e1952ad9043b0c`
- CI: `34680978745`
- Runtime composition installed one hash-change invalidation handler and removes the prior runtime handler before installing another.
- The JavaScript gate stopped on Prettier formatting before full verification.

This checkpoint is explicitly **NOT GREEN**.

### Genuine GREEN

- SHA: `bca845488eab2bcaae71a64880f088e5871c6c5a`
- CI: `34681069621`
- Exact-head permanent gates all passed:
  - `php-quality`
  - `js-quality`
  - `package`
  - `wordpress-smoke`
- WordPress smoke also passed the protected Playground REST smoke path.

The runtime now invalidates the active controller generation on hash navigation. Because the app currently uses top-level hash routes for this screen and has no Playground internal hash subroutes, leaving the route makes any outstanding Playground completion stale before it can publish visible success/error state. Repeated runtime creation removes the previously installed invalidation listener first, preventing listener accumulation in bootstrap/test lifecycles.

## Scoped review

Correctness:
- out-of-order success cannot replace a newer successful result;
- stale success and stale error completions are both suppressed;
- explicit invalidation advances the same centralized generation authority;
- leaving Playground while a request is pending prevents that old completion from resurfacing after return;
- normal single-request loading/success and stable safe-error behavior remain covered by existing controller/runtime tests.

Security:
- request/response schemas are unchanged;
- no credential, provider/model, embedding, vector-store, retrieval-limit, or arbitrary backend-message surface was added;
- no backend/RAG authority moved into the browser.

Performance:
- guards are O(1) integer generation operations;
- one navigation listener is installed by the active runtime and the prior runtime listener is removed before replacement;
- no duplicate network, retrieval, or generation work is introduced.

Accessibility:
- each current submission still publishes the existing live loading state;
- stale completions cannot cause obsolete result/error announcements after a newer request or route change.

Architecture:
- request-order authority remains centralized in `createPlaygroundController`;
- route-lifecycle invalidation is attached by the Playground runtime rather than duplicated in retrieval/generation code;
- no parallel Playground RAG implementation exists.

Independent reviewer/subagent transport was unavailable and is not falsely claimed. Repository-approved scoped fallback review found 0 unresolved Critical and 0 unresolved Important findings for these concurrency/navigation subunits.

## Remaining Task 7 work

1. Harden long diagnostic content, keyboard access, responsive layout, and remaining accessibility behavior.
2. Perform final Task 7 correctness/security/performance/accessibility/architecture review, persist closeout evidence, and require exact-final-head permanent CI before marking Task 7 complete.
