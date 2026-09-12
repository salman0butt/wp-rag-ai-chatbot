# M13 Task 7 — Playground concurrency evidence

Status: **IN PROGRESS**

This checkpoint records Task 7 latest-request-wins behavior for overlapping Playground submissions. It is not Task 7 closeout evidence.

## Scope

The browser continues to submit only the bounded Task 6 DTO through the existing Playground API/runtime/controller path. Concurrency control belongs in the UI controller and must not create new retrieval, generation, provider, embedding, vector-store, or scoring authority.

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

## Scoped review

Correctness:
- out-of-order success cannot replace a newer successful result;
- the same generation check suppresses stale error completion;
- normal single-request loading/success and stable safe-error behavior remain covered by the existing controller tests.

Security:
- request/response schemas are unchanged;
- no credential, provider/model, embedding, vector-store, retrieval-limit, or arbitrary backend-message surface was added.

Performance:
- the guard adds only one integer increment and equality comparison per submission;
- no duplicate network, retrieval, or generation work is introduced.

Accessibility:
- each current submission still publishes the existing live loading state;
- stale completions can no longer cause obsolete result/error announcements.

Architecture:
- request-order authority remains centralized in `createPlaygroundController` rather than duplicated in bootstrap or the RAG pipeline.

Independent reviewer/subagent transport was unavailable and is not falsely claimed. Repository-approved scoped fallback review found 0 unresolved Critical and 0 unresolved Important findings for this subunit.

## Remaining Task 7 work

1. Verify navigation invalidates an in-flight Playground request so leaving and returning cannot surface a stale completion.
2. Harden long diagnostic content, keyboard access, responsive layout, and remaining accessibility behavior.
3. Perform final Task 7 correctness/security/performance/accessibility/architecture review, persist closeout evidence, and require exact-final-head permanent CI before marking Task 7 complete.
