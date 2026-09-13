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

## Task 1B — include/exclude URL/path matching and precedence

### TDD chronology

- `d72d71cb2b9086782d13018106269d63ae697fef` — **NOT RED**. CI `34753502543` stopped in Prettier before Jest.
- `adba64c9de226befe7d4801bc1a5797ac0968d33` — **NOT RED**. CI `34753562898` passed formatting but TypeScript rejected the intentionally absent `visibility` API and evaluator facts argument before Jest.
- `d05e50fad298d39231bc13449c19718a92273025` — **NOT RED**. CI `34753674938` still stopped on one Prettier rule after the test harness was made runtime-observable.
- `14d0f997e633257b18f19de72b68a161790d0593` — **RED**. CI `34753738082` passed lint and typecheck, reached Jest, ran all 50 suites, and failed only the four new Task 1B assertions while the other 49 suites passed.
- `c7f5272df38f14a7a24449e6dbbd1f8dd463a301` — **NOT GREEN**. CI `34753811876` stopped in Prettier on the new matcher before typecheck/Jest.
- `4d2c6e91b4ad9d4262dc0f76be9c8ceb8e26101e` — **GREEN**. CI `34753876478` passed `php-quality`, `js-quality`, `package`, and the complete `wordpress-smoke` suite on the exact implementation SHA.

### Implemented behavior

- URL include/exclude configuration normalizes to bounded path-pattern arrays.
- Include/exclude patterns are capped at 32 combined entries, 256 characters each, and at most four `*` wildcards per pattern.
- Matching uses one deterministic wildcard matcher; configured values are never compiled as arbitrary regular expressions.
- Global `enabled: false` remains highest precedence.
- URL exclusions override inclusions.
- A non-empty include list hides paths that match no include rule.
- Empty include lists impose no URL restriction.
- Reasons are stable for this slice: `disabled`, `url_excluded`, `url_not_included`, and `enabled`/`url_included`.
- The evaluator remains presentation-only and side-effect free.

### Review

Scoped fallback review completed after exact-head GREEN because independent reviewer/subagent transport was not available in this execution environment.

- Correctness: no Critical/Important findings. Exclude-over-include and empty-category semantics are deterministic.
- Security/privacy: no Critical/Important findings. The matcher does not execute arbitrary regex and uses conservative pattern-count/length/wildcard bounds.
- Performance: no Critical/Important findings for configured inputs; work is bounded by at most 32 short patterns and a deterministic matcher.
- Accessibility: no direct UI behavior changed in Task 1B.
- Architecture/duplication: no new runtime authority was added. The same pure evaluator remains the only display-rule domain authority. The earlier duplicate invocation seam in `widget.ts`/`widget-runtime.ts` remains scheduled for reconciliation in Task 4 rather than being expanded here.

## Exact next unfinished unit

Task 1C — add presentation-only audience/post/Woo/device facts and deterministic gates. Values within a configured category must use OR semantics while configured categories are ANDed. Prove authenticated/anonymous/selected-role, post type, finite Woo area, and desktop/tablet/mobile behavior with a fresh real RED before production changes.
