# M15 Task 4 — Shared runtime visibility integration

Status: COMPLETE.

## Scope

Wire the Task 1 deterministic display-rule authority and Task 3 trusted presentation facts into the existing M14 widget runtime exactly once.

The public entrypoint must not create a second visibility evaluator. Ineligible widgets remain unmounted/inert; eligible widgets preserve the existing M14 widget behavior.

## TDD evidence

- `66a21d5e598a20a5eba85d0cf7ba695870118b3a` — **RED**, CI `34779687454`. JavaScript package/lint/type checks reached Jest successfully. `src-js/widget-display-rules.test.ts` was the only failing suite: the eligible trusted-facts fixture mounted `0` widgets instead of `1`, and the public entrypoint produced no launcher. The other 51 suites / 130 tests passed. `php-quality`, `package`, and `wordpress-smoke` were GREEN.
- `4e97fda05e959192b3028b73e07901503c8c5e4d` — **NOT GREEN**, CI `34779867911`. The minimum implementation passed PHP, package, and WordPress smoke, but `js-quality` stopped at Prettier for a missing final newline in `src-js/widget-runtime.ts`; this checkpoint is not GREEN evidence.
- `1a973509a8931c94c5d872a7e43555c1ebaed201` — architecture refactor removing duplicate display-rule evaluation from `src-js/widget.ts`, leaving `mountWidgets()` as the single visibility authority.
- `1c9b556c323ac5faa5366208e52c30738d206ff5` — **GREEN**, CI `34780105305`. Exact-head `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed after formatting-only corrections.

## Implementation

`src-js/widget-runtime.ts` now:

- accepts the bounded `DisplayRuleFacts` projected by the existing public widget bootstrap;
- invokes `normalizeDisplayRules()` and `evaluateDisplayRules()` at the shared mount seam;
- passes trusted server facts to that evaluator;
- returns without mounting when the deterministic visibility decision is false;
- preserves the existing M14 launcher/panel/chat behavior for eligible configs.

`src-js/widget.ts` now delegates the bootstrap array directly to `mountWidgets()` and does not run a parallel visibility filter.

## Review

Independent reviewer transport is not exposed in this runtime, so the repository-approved fallback scoped review was performed and this limitation is recorded honestly.

- Correctness: no unresolved Critical/Important findings. The shared runtime consumes server facts and remains the single mount visibility authority.
- Security/privacy: no unresolved Critical/Important findings. The runtime consumes bounded presentation facts only and does not gain credentials, provider/model authority, embedding/vector-store authority, retrieval authority, or arbitrary request overrides.
- Performance: no unresolved Critical/Important findings. Visibility is one bounded deterministic evaluation per candidate mount; no network call is introduced.
- Accessibility: no unresolved Critical/Important findings. Ineligible widgets create no inaccessible/inert controls; eligible widgets preserve the existing accessible M14 widget controls.
- Architecture/duplication: no unresolved Critical/Important findings. Duplicate entrypoint evaluation was explicitly removed; the shared runtime is the single visibility seam.

## Verification

Final implementation SHA: `1c9b556c323ac5faa5366208e52c30738d206ff5`.

Final implementation CI: `34780105305` — GREEN across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.

## Next unfinished unit

Task 5A — proactive delay and once-only lifecycle. Establish test-only RED for bounded configured delay, one automatic open at most, no chat request on proactive open, manual open cancelling pending proactive behavior, and disposal clearing pending timers. Then implement one coordinator integrated through one existing widget-runtime seam.
