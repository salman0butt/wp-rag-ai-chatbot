# M15 Task 8 — Localization, RTL & accessible runtime hardening

Status: COMPLETE.

## Scope

Harden the shared M14 widget runtime with one bounded message catalog, locale/direction semantics from the existing Task 1 display-rule decision, logical RTL layout, reduced-motion behavior, and focus/accessibility regressions. No parallel locale authority or alternate widget runtime is permitted.

## Task 8A — bounded widget message catalog + localized runtime labels

### Catalog TDD evidence

- `b8dd2ef007bd3f94d38fbc6eb6e444a5a808d5a9` — **NOT RED**, CI `34793497746`. Prettier failed before Jest.
- `303b907ff06e6a7f9864716f3bd90a03399bf522` — **RED**, CI `34793561768`. Lint/typecheck passed; all 62 pre-existing suites / 159 pre-existing tests passed and only the three new catalog tests failed because `./widget-messages` did not exist.
- `2359c2b6b70727b46e46875de447b65c90549f0c` — **NOT GREEN**, CI `34793635180`. Prettier stopped before typecheck/Jest.
- `e3758e1a7480c328f8b68564ace514527eb1391c` — **GREEN**, CI `34793694606`. Exact-head permanent CI fully passed.

### Runtime localization TDD evidence

- `eec4d289aa85fd23ae7fa2fb48bfd3603c5b84d2` — **NOT RED**, CI `34793845287`. Prettier failed before Jest.
- `f30bee1549aafb04bf2c6597997fa33c08ff6a49` — **NOT RED**, CI `34793912040`. Remaining Prettier findings stopped before Jest.
- `53ba9f530e87f30fbb6d81a819eac286a198b6ee` — **RED**, CI `34793995420`. Lint/typecheck passed; all existing tests passed and only the Urdu runtime assertion failed because the runtime still rendered hard-coded English.
- `1ad50ee33c6d977b950b28fea465a47ae1e95b67` — **NOT GREEN**, CI `34794184146`. Prettier stopped before Jest.
- `a875299414e709a182d7e361e328b07c9c80bc7e` — **GREEN**, CI `34794272117`. Exact-head permanent CI fully passed.

`widget-messages.ts` owns a bounded English/Urdu runtime catalog. Unsupported locales fall back to English. The runtime resolves labels through `resolveWidgetMessage(displayDecision.locale, ...)`, so locale comes from the shared Task 1 evaluator rather than browser/theme state.

## Task 8B — widget-local language/direction + logical RTL layout

### Widget-local language/direction

- `1b3e366848ffb84ab6db1a9f6976a0c55408de21` — **NOT RED**, CI `34794485758`. Prettier failed before Jest.
- `d6b0b215f6515448fc6d49fd0dbdef2ab90b976b` — **RED**, CI `34794562895`. Lint/typecheck passed; existing tests passed and only the new widget-local `lang`/`dir` assertions failed because the mount had no local language/direction attributes.
- `29bbe3b34ba7616d08b23c2aabfc276c61073944` — **GREEN**, CI `34794699995`. Exact-head permanent CI fully passed.

The widget root now sets `lang` and `dir` from `displayDecision.locale` / `displayDecision.direction`, including inferred RTL and explicit direction overrides, independent of the surrounding document/theme direction.

### Logical RTL CSS

- `434fbe96325df8af5e9095a554b3199bf373dfda` — **NOT RED**, CI `34794782114`. The first CSS test fixture relied on Node globals/types unavailable to the repository TypeScript config.
- `e238de24b877746cef9e1f7790469d9e54b4f671` — **RED**, CI `34794888200`. Lint/typecheck passed; existing tests passed and only the logical-CSS regression failed because `assets/widget.css` still used physical `left` / `right` offsets.
- `b05ec97078c790b50a7cdbdfa647234bb174fa9f` — **GREEN**, CI `34794973838`. Exact-head permanent CI fully passed.

The existing stylesheet now uses logical `inset-inline-start` / `inset-inline-end` positioning for widget and panel placement. No duplicate RTL stylesheet was introduced.

## Task 8C — reduced motion + focus lifecycle

### Reduced motion

- `70fa981044c7a72c297849181f0f54924be99ee9` — **RED**, CI `34795030692`. Lint/typecheck passed; all existing tests passed and only the reduced-motion test failed because the simulated typing path exposed a partial `Th` answer.
- `fe69aacaf5aee32341e682d2daf1c55bb3bcc888` — **GREEN**, CI `34795234377`. Exact-head `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed.

The existing assistant presentation path now checks widget-local `matchMedia('(prefers-reduced-motion: reduce)')`. Reduced-motion users receive the full completed answer, polite completion semantics, and completion controls synchronously without timer-driven typing. Normal-motion behavior remains unchanged.

### Focus/proactive lifecycle coverage

- `f2cdfcbcc3bc2f2abad3ff6a1073ba165a1c4b9d` — **NOT RED**, CI `34795326015`. ESLint/Prettier stopped before Jest because the fixture used global `activeElement` and needed formatting.
- `4431d09f1cc3d3ca3fac5c639b50b1054b4c0424` — **GREEN COVERAGE OF EXISTING BEHAVIOR**, CI `34795395647`. Exact-head permanent CI fully passed. Proactive opening leaves page focus untouched; manual open moves focus to close; Escape closes, restores launcher focus, resets `aria-expanded`, and the cancelled proactive timer cannot reopen the widget.

No production change was required for focus lifecycle, so no RED→GREEN implementation chronology is fabricated.

## Review

Independent reviewer/subagent transport was unavailable in this runtime, so the repository-approved scoped fallback review was used.

- Correctness: 0 unresolved Critical/Important findings. Localization/direction reuse the shared display decision; reduced-motion and focus paths preserve existing chat semantics.
- Security/privacy: 0 unresolved Critical/Important findings. Catalog values are allow-listed text; bot-name interpolation is plain text; no provider/model/retrieval/credential/user facts enter localization.
- Performance: 0 unresolved Critical/Important findings. Catalog lookup and direction assignment are bounded; reduced motion removes timers rather than adding work.
- Accessibility: 0 unresolved Critical/Important findings. Visible labels/accessibility names are localized together, widget-local `lang`/`dir` are explicit, reduced motion disables simulated typing, proactive open does not steal focus, and Escape restores launcher focus.
- Architecture/duplication: 0 unresolved Critical/Important findings. One evaluator, one widget runtime, one catalog, and one stylesheet remain authoritative.

## Verification

- Task 8A final GREEN: `a875299414e709a182d7e361e328b07c9c80bc7e`, CI `34794272117`.
- Task 8B direction final GREEN: `29bbe3b34ba7616d08b23c2aabfc276c61073944`, CI `34794699995`.
- Task 8B CSS final GREEN: `b05ec97078c790b50a7cdbdfa647234bb174fa9f`, CI `34794973838`.
- Task 8C reduced-motion final GREEN: `fe69aacaf5aee32341e682d2daf1c55bb3bcc888`, CI `34795234377`.
- Task 8C focus coverage final GREEN: `4431d09f1cc3d3ca3fac5c639b50b1054b4c0424`, CI `34795395647`.

All listed final checkpoints passed `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.

## Next unfinished unit

Task 9 — extend permanent real WordPress smoke for M15 URL/server-fact visibility, public projection boundaries, proactive-trigger no-chat side effect, RTL root semantics, and no runtime/provider/config leakage; then run final milestone review/CI/PR/merge gates.
