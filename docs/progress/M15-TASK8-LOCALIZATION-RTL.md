# M15 Task 8 — Localization, RTL & accessible runtime hardening

Status: IN PROGRESS.

## Scope

Harden the shared M14 widget runtime with one bounded message catalog, locale/direction semantics from the existing Task 1 display-rule decision, logical RTL layout, and accessibility regressions. No parallel locale authority or alternate widget runtime is permitted.

## Task 8A — bounded widget message catalog + localized runtime labels

### Catalog TDD evidence

- `b8dd2ef007bd3f94d38fbc6eb6e444a5a808d5a9` — **NOT RED**, CI `34793497746`. Prettier failed before Jest.
- `303b907ff06e6a7f9864716f3bd90a03399bf522` — **RED**, CI `34793561768`. JavaScript package lint, ESLint/Prettier and TypeScript passed; all 62 pre-existing suites / 159 pre-existing tests passed and only the three new catalog tests failed because `./widget-messages` did not exist.
- `2359c2b6b70727b46e46875de447b65c90549f0c` — **NOT GREEN**, CI `34793635180`. Implementation reached CI but Prettier stopped before typecheck/Jest.
- `e3758e1a7480c328f8b68564ace514527eb1391c` — **GREEN**, CI `34793694606`. Exact-head `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed.

`widget-messages.ts` owns the bounded runtime labels for English and Urdu. Unsupported/empty locales fall back deterministically to English. Dynamic bot-name interpolation is reduced to plain text before substitution; the runtime continues to render through `textContent`/attributes rather than raw HTML.

### Runtime localization TDD evidence

- `eec4d289aa85fd23ae7fa2fb48bfd3603c5b84d2` — **NOT RED**, CI `34793845287`. Prettier failed before Jest.
- `f30bee1549aafb04bf2c6597997fa33c08ff6a49` — **NOT RED**, CI `34793912040`. Two remaining Prettier findings stopped before Jest.
- `53ba9f530e87f30fbb6d81a819eac286a198b6ee` — **RED**, CI `34793995420`. Lint/typecheck passed; 63 existing suites / 163 existing tests passed and only the Urdu runtime assertion failed because the runtime still rendered hard-coded English `Chat` instead of `چیٹ`.
- `1ad50ee33c6d977b950b28fea465a47ae1e95b67` — **NOT GREEN**, CI `34794184146`. Production wiring was behaviorally implemented but Prettier stopped before typecheck/Jest.
- `a875299414e709a182d7e361e328b07c9c80bc7e` — **GREEN**, CI `34794272117`. Exact-head `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed.

The runtime resolves launcher, panel, close, message, send, retry, copy, sources, typing, sending, rate-limit, unavailable, and send-failure labels through `resolveWidgetMessage(displayDecision.locale, ...)`. Locale therefore comes from the same Task 1 evaluator decision already used for widget visibility/starters; no browser/theme locale side channel was introduced.

## Task 8A review

Independent reviewer/subagent transport was unavailable in this runtime, so the repository-approved scoped fallback review was used.

- Correctness: 0 unresolved Critical/Important findings. Existing English strings remain equivalent; Urdu and unsupported-locale fixtures prove bounded locale selection/fallback.
- Security/privacy: 0 unresolved Critical/Important findings. Catalog values are static allow-listed text, bot-name interpolation strips angle brackets, and rendering remains text-only. No credentials/provider/model/retrieval/user facts enter localization.
- Performance: 0 unresolved Critical/Important findings. Locale lookup is a bounded in-memory map with constant-size key/catalog sets.
- Accessibility: 0 unresolved Critical/Important findings. Visible controls and accessible names are localized from the same resolved catalog; existing `aria-label`/status semantics are preserved.
- Architecture/duplication: 0 unresolved Critical/Important findings. The runtime consumes `displayDecision.locale` and one catalog; no duplicate evaluator or locale resolver was added.

## Verification

Task 8A exact final implementation GREEN: `a875299414e709a182d7e361e328b07c9c80bc7e`, CI `34794272117` — `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all GREEN.

## Next unfinished unit

Task 8B — widget-local `lang`/`dir` + logical RTL layout. Establish Jest/CSS RED proving `displayDecision.locale`/`direction` are applied locally, page/theme direction cannot override them, and physical `left/right` layout is replaced with logical inline positioning without a duplicated RTL stylesheet.
