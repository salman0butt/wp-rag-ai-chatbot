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

## Task 1C — audience, post type, WooCommerce, and device gates

### TDD chronology

- `4a9745978c9b570d105a54d718c85b0007818d6f` — **NOT RED**. CI `34754124246` stopped in Prettier before typecheck/Jest.
- `462ec073dd2daeaa1ac7c5babd24340b0425e7e8` — **RED**. CI `34754189113` passed lint and typecheck, reached Jest, ran all 50 suites, and failed only the new audience/context assertions while the other 49 suites passed.
- `9c50cf8a7d202bb056af86f5a15902d212b592ad` — **RED refinement**. CI `34754293425` again passed lint and typecheck, reached Jest, and failed only `display-rules.test.ts` for the intentionally missing normalized default/category fields and audience decisions; 49/50 suites passed and `wordpress-smoke` independently passed.
- `8df72314743341881f82f03e4538212e25458c38` — **NOT GREEN**. CI `34754328967` stopped in Prettier on the implementation before typecheck/Jest.
- `6968c89c0d2eae681323eb5d28448b56f9a26169` — **GREEN**. CI `34754417924` passed `php-quality`, `js-quality`, `package`, and the complete `wordpress-smoke` suite on the exact implementation SHA.

### Implemented behavior

- Visibility normalization now always produces bounded `post_types`, `audience`, `roles`, `woo_areas`, and `devices` fields in addition to URL rules.
- Audience strategies are finite: `all`, `authenticated`, `anonymous`, and `selected_roles`; invalid values normalize to `all`.
- Roles/post types normalize to lowercase bounded slugs with deduplication and a 16-value cap per category.
- WooCommerce areas are restricted to `shop`, `product`, `cart`, `checkout`, and `account`.
- Device buckets are restricted to `desktop`, `tablet`, and `mobile`.
- Values within configured categories use OR semantics, while configured categories are applied with AND semantics.
- `selected_roles` requires authenticated presentation facts and at least one projected `roleMatches` token matching a configured role.
- The evaluator consumes projected presentation facts (`isAuthenticated`, `roleMatches`, `postType`, `wooArea`, `device`) rather than user objects; it remains presentation-only and is not an authorization/security authority.
- Mismatch reasons are stable for this slice: `audience_mismatch`, `post_type_mismatch`, `woo_area_mismatch`, and `device_mismatch`.

### Review

Scoped fallback review completed after exact-head GREEN because independent reviewer/subagent transport was not available in this execution environment.

- Correctness: no Critical/Important findings. OR-within/AND-across behavior and audience strategies are deterministic.
- Security/privacy: no Critical/Important findings. Only bounded projected presentation facts are consumed; no user identity object, credential, or backend authorization state is exposed as evaluator authority.
- Performance: no Critical/Important findings. Role/post-type categories are capped at 16 values and Woo/device categories are naturally finite.
- Accessibility: no direct UI behavior changed in Task 1C.
- Architecture/duplication: no parallel rule engine was introduced; the same pure evaluator remains the display-policy authority.

## Task 1D — site-time schedule/day/time rules

### TDD chronology

- `cb9c566aedea073fc8694002effeaa8e36b81d80` — **RED**. CI `34754730429` passed formatting and typecheck, reached Jest, ran 51 suites, and failed only the three new schedule assertions; the other 50 suites passed.
- `db9b9fb1103c2632bfc85b62263401fc0599b7e6` — **NOT GREEN**. CI `34754852869` stopped in Prettier on the schedule implementation before typecheck/Jest.
- `753973f17da8692a8a35fd6035833a9be49f41d7` — intermediate implementation candidate. JavaScript/PHP/package verification passed, but scoped review found an **Important** correctness defect before Task 1D closeout: an explicitly empty schedule incorrectly required time facts and hid the widget, violating the milestone's empty-category semantics.
- `0233ea8ec3482914058fd4d98fbeabf469fd46b2` — **RED** regression checkpoint. CI `34755052770` passed lint and typecheck, reached Jest, ran 51 suites, and failed only `treats an explicitly empty schedule as no restriction`; 50 suites and 126 tests passed.
- `32d50bd996d314b3513766ad4ff1e855c068f978` — **GREEN**. CI `34755164651` passed `php-quality`, `js-quality`, `package`, and the complete `wordpress-smoke` suite on the exact fix SHA.

### Implemented behavior

- Schedule configuration is site-time only; evaluator facts are explicit `siteWeekday` and `siteMinuteOfDay`, so the pure evaluator never reads an ambient clock or browser timezone.
- Weekdays normalize to unique integer values `0..6`; invalid entries are discarded.
- Start/end times normalize only strict `HH:MM` values.
- Same-day windows use inclusive start/end boundaries.
- Overnight windows are attributed to the configured start day; early-next-day minutes match only when the previous weekday is configured.
- Day-only, start-only, and end-only restrictions remain deterministic.
- An absent schedule and an explicitly empty schedule impose no restriction.
- Invalid/missing projected site-time facts fail closed only when a non-empty schedule actually needs them.
- Schedule mismatch reason is stable as `schedule_mismatch`.

### Review

Scoped fallback review completed after the regression fix and exact-head GREEN because independent reviewer/subagent transport was unavailable.

- Correctness: the Important empty-schedule finding was resolved through a dedicated RED→GREEN regression cycle; no Critical/Important findings remain for Task 1D.
- Security/privacy: no Critical/Important findings. Time facts remain presentation-only; no authorization decision or user identity is derived from schedule policy.
- Performance: no Critical/Important findings; schedule evaluation is constant-time over bounded normalized values.
- Accessibility: no direct UI behavior changed in Task 1D.
- Architecture/duplication: no clock/timezone side effects or second evaluator were introduced.

## Exact next unfinished unit

Task 1E — add deterministic page-specific starter selection plus locale/direction projection. Exact starter matches must beat globs; equal-specificity matches use the first normalized rule. Prompt lists must remain bounded plain text. Explicit `rtl`/`ltr` direction overrides auto; auto must infer an RTL direction for at least one supported RTL locale fixture and LTR for fallback locale. Prove all behavior with a fresh genuine RED before production changes.
