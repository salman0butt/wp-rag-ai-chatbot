# M15 Task 1 — Deterministic Display-Rule Domain

Status: IN PROGRESS

## Task 1A — M14-compatible defaults and disabled behavior

### TDD chronology

- `a703c050f0c8901f6611a723676d9bc2b433e819` — **NOT RED**. CI `34750680305` stopped in Prettier before Jest reached the intended disabled-widget regression.
- `a8ae612338c4446bb5b67430a03bcba4b9e7d6cb` — **NOT GREEN**. CI `34751103140` stopped in Prettier before the JavaScript test suite.
- `29554829bca52bba6f8a017f6059d6b09f2ade80` — **NOT GREEN**. Formatting repair still left a widget bootstrap Prettier failure.
- `6b75b8bbe24e89b7aa73e32a12c2099d13b0229b` — **NOT RED**. CI `34753135015` stopped in Prettier on the new Task 1A test before Jest.
- `40b7ce186c7123d5855958a00bca8dcf826eae39` — **RED**. CI `34753211573` passed lint and typecheck, reached Jest, and failed only the two new `display-rules.test.ts` assertions because normalized defaults and bounded decision fields were not implemented; the remaining 49 suites passed.
- `6b6a12752a2b8d8e828061f0b42e7feaa3ca521e` — **GREEN**. CI `34753278019` passed `php-quality`, `js-quality`, `package`, and `wordpress-smoke` on the exact implementation SHA.

### Implemented behavior

- Missing/invalid display configuration defaults to enabled visibility.
- Proactive behavior defaults disabled.
- Default and page-scoped starter collections default empty.
- Localization defaults to site locale with automatic direction; the current default decision projects `ltr` until locale/direction logic is added in Task 1E.
- Explicit `enabled: false` remains the highest-precedence hidden decision.
- The evaluator remains pure and side-effect free; it does not authorize data access or bypass backend security.

### Review

Scoped fallback review completed after exact-head GREEN because an independent reviewer/subagent transport was not available in this execution environment.

- Correctness: no Critical/Important findings for Task 1A.
- Security/privacy: no Critical/Important findings; display policy remains presentation-only.
- Performance: no Critical/Important findings; evaluation is constant-time for this slice.
- Accessibility: no new UI behavior in Task 1A.
- Architecture/duplication: no parallel evaluator implementation was introduced. Existing early runtime integration invokes the same authority and will be reconciled during the planned runtime-integration slice rather than forked.

## Exact next unfinished unit

Task 1B — add deterministic URL include/exclude normalization and matching with precedence: global disable first, then exclude overrides include, with empty include meaning no URL restriction. Prove behavior with a fresh real RED before changing production code.
