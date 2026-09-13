# M15 Task 1 — Deterministic Display-Rule Domain

Status: COMPLETE

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
- Explicit `enabled: false` remains the highest-precedence hidden decision.
- The evaluator remains pure and side-effect free; it does not authorize data access or bypass backend security.

### Review

Scoped fallback review completed after exact-head GREEN because an independent reviewer/subagent transport was not available in this execution environment.

- Correctness: no Critical/Important findings for Task 1A.
- Security/privacy: no Critical/Important findings; display policy remains presentation-only.
- Performance: no Critical/Important findings; evaluation is constant-time for this slice.
- Accessibility: no new UI behavior in Task 1A.
- Architecture/duplication: no parallel evaluator implementation was introduced.

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

### Review

Scoped fallback review completed after exact-head GREEN because independent reviewer/subagent transport was not available in this execution environment.

- Correctness: no Critical/Important findings.
- Security/privacy: no Critical/Important findings; no arbitrary regex execution channel exists.
- Performance: no Critical/Important findings; work is bounded by normalized pattern limits.
- Accessibility: no direct UI behavior changed.
- Architecture/duplication: the same pure evaluator remains the display-rule authority.

## Task 1C — audience, post type, WooCommerce, and device gates

### TDD chronology

- `4a9745978c9b570d105a54d718c85b0007818d6f` — **NOT RED**. CI `34754124246` stopped in Prettier before typecheck/Jest.
- `462ec073dd2daeaa1ac7c5babd24340b0425e7e8` — **RED**. CI `34754189113` passed lint and typecheck, reached Jest, ran all 50 suites, and failed only the new audience/context assertions while the other 49 suites passed.
- `9c50cf8a7d202bb056af86f5a15902d212b592ad` — **RED refinement**. CI `34754293425` reached Jest and failed only the intentional display-rule fixture assertions; 49/50 suites passed and WordPress smoke independently passed.
- `8df72314743341881f82f03e4538212e25458c38` — **NOT GREEN**. CI `34754328967` stopped in Prettier on the implementation before typecheck/Jest.
- `6968c89c0d2eae681323eb5d28448b56f9a26169` — **GREEN**. CI `34754417924` passed `php-quality`, `js-quality`, `package`, and the complete `wordpress-smoke` suite on the exact implementation SHA.

### Implemented behavior

- Visibility normalization produces bounded `post_types`, `audience`, `roles`, `woo_areas`, and `devices` fields.
- Audience strategies are finite: `all`, `authenticated`, `anonymous`, and `selected_roles`.
- Roles/post types normalize to lowercase bounded slugs with deduplication and a 16-value cap per category.
- WooCommerce areas are restricted to `shop`, `product`, `cart`, `checkout`, and `account`.
- Device buckets are restricted to `desktop`, `tablet`, and `mobile`.
- Values within configured categories use OR semantics; configured categories are ANDed.
- `selected_roles` consumes only projected presentation match tokens/facts, never user objects or authorization authority.

### Review

- Correctness: no Critical/Important findings.
- Security/privacy: no Critical/Important findings; only bounded presentation facts are consumed.
- Performance: no Critical/Important findings.
- Accessibility: no direct UI behavior changed.
- Architecture/duplication: no parallel rule engine introduced.

## Task 1D — site-time schedule/day/time rules

### TDD chronology

- `cb9c566aedea073fc8694002effeaa8e36b81d80` — **RED**. CI `34754730429` passed formatting and typecheck, reached Jest, ran 51 suites, and failed only the three new schedule assertions; the other 50 suites passed.
- `db9b9fb1103c2632bfc85b62263401fc0599b7e6` — **NOT GREEN**. CI `34754852869` stopped in Prettier on the schedule implementation before typecheck/Jest.
- `753973f17da8692a8a35fd6035833a9be49f41d7` — intermediate implementation candidate. Review found an **Important** correctness defect: explicitly empty schedule configuration incorrectly required time facts and hid the widget.
- `0233ea8ec3482914058fd4d98fbeabf469fd46b2` — **RED** regression checkpoint. CI `34755052770` reached Jest and failed only `treats an explicitly empty schedule as no restriction`; 50 suites and 126 tests passed.
- `32d50bd996d314b3513766ad4ff1e855c068f978` — **GREEN**. CI `34755164651` passed all four permanent jobs on the exact fix SHA.

### Implemented behavior

- Schedule evaluation consumes explicit projected site weekday/minute facts; it never reads ambient clocks/timezones.
- Weekdays normalize to unique `0..6`; times normalize strict `HH:MM`.
- Same-day boundaries are inclusive; overnight windows are attributed to the configured start day.
- Absent and explicitly empty schedules impose no restriction.
- Missing/invalid facts fail closed only when a non-empty schedule requires them.

### Review

- Correctness: the Important empty-schedule finding was resolved through a RED→GREEN regression cycle; no Critical/Important findings remain.
- Security/privacy: no Critical/Important findings.
- Performance: no Critical/Important findings.
- Accessibility: no direct UI behavior changed.
- Architecture/duplication: no ambient clock/timezone side effects or second evaluator introduced.

## Task 1E — page starters and locale/direction projection

### TDD chronology

- `5eb6ab5c608a0f8385a18f4011340a707dc0552c` — **NOT RED**. CI `34755391857` stopped in Prettier before typecheck/Jest.
- `4ad372d37511b36c1ee211b2c4f337f995a9871d` — **RED**. CI `34755448999` passed lint/typecheck, reached Jest, ran 52 suites, and failed only the three new starter/localization tests; 51 suites passed.
- `fbdde518e194898a7ad2ff6d567094cd91bcc98e` — **NOT GREEN**. CI `34755562589` stopped in Prettier before typecheck/Jest.
- `ac4667b65ce3d97fc7b39cfbc849cf22a8f4b8bc` — **NOT GREEN**. CI `34755659410` stopped on an ASI/lint issue in the locale normalizer before typecheck/Jest.
- `e9d4adbd076333142e000a38332cfdc8fd9e8853` — **GREEN**. CI `34755743017` passed `php-quality`, `js-quality`, `package`, and the complete `wordpress-smoke` suite on the exact implementation SHA.

### Implemented behavior

- Starter mappings are bounded to 8 rules, with at most 4 non-empty plain-text prompts per set and 160 Unicode code points per prompt.
- Starter path matching reuses the existing deterministic matcher; exact path wins before glob, and the first normalized rule wins among equal specificity.
- Default starters are used when no page rule matches.
- Locale settings normalize to `site`, `auto`, or a bounded normalized locale identifier.
- `auto` locale prefers projected document locale, then projected site locale; explicit normalized locale remains authoritative.
- Explicit `ltr`/`rtl` direction overrides auto. Auto maps supported RTL language fixtures (`ar`, `fa`, `he`, `ur`) to RTL and otherwise falls back to LTR/projected site direction where applicable.
- The evaluator remains pure; it does not mutate host-page language/direction and does not read DOM/browser globals.

### Review

Scoped fallback review completed after exact-head GREEN because independent reviewer/subagent transport was unavailable.

- Correctness: no Critical/Important findings; exact/glob precedence, tie-breaking, bounds, locale normalization, and direction precedence match the approved design.
- Security/privacy: no Critical/Important findings; starter text and locale/path facts remain bounded presentation data with no executable HTML/CSS/JS channel.
- Performance: no Critical/Important findings; starter selection is bounded by at most 8 mappings and reuses the bounded path matcher.
- Accessibility: no Critical/Important findings at the pure-domain layer; runtime `lang`/`dir`, message catalog, focus and reading-order behavior remain later milestone tasks.
- Architecture/duplication: no second matching/localization engine was introduced.

## Task 1 completion

Task 1 is COMPLETE. Its pure TypeScript authority now covers deterministic visibility precedence, URL rules, audience/context gates, schedule windows, page starters, and locale/direction decisions with real RED/GREEN evidence and resolved review findings.

## Exact next unfinished unit

Task 2A — implement immutable normalized PHP `DisplayRulesConfig` following the existing M14 appearance/config conventions. Begin with PHPUnit RED covering M14-compatible defaults, strict unknown-key rejection, finite enums, collection/string/timer/scroll bounds, glob/selector validation, prompt bounds, and localization normalization. Do not duplicate the TypeScript evaluator in PHP; PHP owns validation/normalization/persistence projection, while TypeScript remains the browser evaluation authority.
