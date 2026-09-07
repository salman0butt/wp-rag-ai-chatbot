# M12 — Admin Onboarding, Bot Management & Provider Configuration

Status: **IN PROGRESS — Tasks 1-7 COMPLETE; Task 8 ACTIVE**

## Goal
Build a professional WordPress-native admin shell, onboarding, multi-bot management, and provider/model configuration UI.

## Dependencies
M03, M11 backend contracts, M01 frontend tooling.

## In Scope
Admin navigation/shell; onboarding; bot CRUD/config; provider credential/model/capability UI; validation/errors; settings progressive disclosure; usage-safe model selection.

## Out of Scope
Knowledge/debugger UI M13; appearance editor M14; analytics M21.

## Architecture
React/TypeScript where useful, backed by granular capability-protected REST endpoints. Provider secrets remain write-only and are never returned to JavaScript.

Selected design: `docs/superpowers/specs/2026-09-07-m12-admin-onboarding-bots-providers-design.md`.
Implementation plan: `docs/superpowers/plans/2026-09-07-m12-admin-onboarding-bots-providers.md`.
Both are **AUTO-APPROVED — SCHEDULED MODE**.

## Acceptance Criteria
Admin capabilities are enforced; onboarding handles unavailable provider/capability states; multiple bots remain isolated; secrets remain write-only; admin transport fails safely; typecheck/build/component/WordPress integration flows pass; admin assets do not load on public or unrelated admin screens; accessibility and performance gates are reviewed before milestone closeout.

## Tasks
1. **COMPLETE** — Admin foundation and capability-protected REST bootstrap.
2. **COMPLETE** — Bot aggregate/repository and persistence.
3. **COMPLETE** — Bot CRUD REST resources with pagination/isolation.
4. **COMPLETE** — Provider credential/configuration REST resource with write-only secrets.
5. **COMPLETE** — Provider model/capability and onboarding-readiness resources.
6. **COMPLETE** — React admin shell and typed API layer.
7. **COMPLETE** — Onboarding flow.
8. **ACTIVE** — Bot management screens.
9. Provider/model configuration screens.
10. Integration/E2E, security, accessibility, performance, review, durable closeout.

## Completed Task Evidence

### Task 1 — Admin foundation
Final implementation `0638dc14197054f48efc5fd51e480b74ca394073`; CI `34088991361` passed `php-quality`, `js-quality`, `package`, and `wordpress-smoke`. Scoped review `5128526998`: **0 Critical / 0 Important**.

### Task 2 — Bot persistence
Genuine behavioral RED `2f9adb8daf5b8cfad1607a5462c779ec31f4abaf` reached PHPUnit after static gates and failed the absent bot-domain/persistence behaviors. Final implementation/integration `1bbfe79994f7fa850429e7f5aa968f44e4c54c31`; CI `34091206697` passed full quality/package/WordPress integration. Scoped review `5128744956`: **0 Critical / 0 Important**.

### Task 3 — Bot CRUD REST
Genuine route RED `b7950a33c51171532c1d270076f8b847b70d2d21` / CI `34097504341` passed PHPCS/PHPStan and failed exactly the missing `/admin/bots` route. Final integration `047de805f6481ad36a334e63e2e44efb62989553` / CI `34098207281` passed full CI and the real WordPress REST authorization/CRUD/pagination/isolation lifecycle. Scoped review `5129481296`: **0 Critical / 0 Important**.

### Task 4 — Provider credentials/configuration
Core RED `26ce3eefef6dd7d5198b77a669268bc07cbcafbf` / CI `34102834048`; protected-route RED `4ff5916c809cf104d7e753a2d5531837ef093a49` / CI `34103192408`. Final implementation `759db1cc1c20ccebb906fc03cc51d1434dc6110a` / CI `34103975996` passed full CI. The REST surface reuses M03 credential storage/resolution and never serializes plaintext or ciphertext. Scoped review `5130113628`: **0 Critical / 0 Important**.

### Task 5 — Model capability/readiness
Resource RED `471f7fd753f571a99a716d8bf8381bb4dd3d1987` / CI `34113946121`; REST-route RED `6878e36f6073b4fc69f1f5dc2f49a9a86290c009` / CI `34114690187`; integration `6a16a932275254ba97ae642258feaa9ebd727b49` / CI `34115367056` passed full CI and real WordPress REST readiness smoke. Review found one Important page-1-only readiness defect. Genuine regression RED `c19cb4395680a9c2c13b8a70f391763aebda9b17` / CI `34119267966` ran 664 tests / 2,762 assertions and failed exactly because a compatible bot on page 2 returned `first_bot`; fix `080c973dbd011192c88c3f56941f15d3495d504a` / CI `34119414309` passed full CI. Post-fix review `5131750259`: **0 Critical / 0 Important unresolved**.

### Task 6 — React admin shell and typed API
Task 6 provides a typed same-origin REST client with WordPress nonce headers, normalized safe REST failures, loading/empty/error/ready primitives, accessible navigation/status/error semantics, deterministic hash routing, safe WordPress boot configuration, automatic admin-only mount bootstrap, server-derived readiness state, fail-closed missing-config/transport behavior, and plugin-screen-only asset enqueue.

Key closeout evidence: final transport regression RED `95d813045c3b530aa4208e155a64588c069b20c7` / CI `34149025827` passed lint/typecheck and ran 16 Jest tests with exactly the new missing-fetch case failing. Minimum fix `759b77acb45a9556ce907f9eaf424cb11521f9c4`; alignment checkpoint `4ee313e957f0d7ef8c67fa776df07ff647c8212e` / CI `34149262866` passed `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`. Closeout review `5134434515`: **0 Critical / 0 Important unresolved**.

### Task 7 — Onboarding flow
Task 7 is driven by persisted server readiness rather than browser-only completion. Existing tests prove provider -> model -> first bot -> complete progression, model/first-bot reload resume, actionable `provider_unavailable`, `missing_credential`, and `unsupported_capability` states, `role="alert"` announcements, and focus on the deterministic provider-settings recovery action.

Final live issue-integration TDD evidence:
- Server-contract RED `ed656605c927c69761cc23a4ba1fc540ee63a4d9` / CI `34167918116` reached PHP verification and failed because onboarding readiness did not expose an `issue` field.
- Minimum server GREEN `ec94a190c13ce9bd89ac637b1df3ab39e8457014` / CI `34167991217` passed all permanent jobs. Readiness now serializes only one stable issue code and does not expose provider/upstream error text or credentials.
- Mounted-app RED `1da2935c4066aa18b2e41fca511d3ce38fead75d` / CI `34168105909` passed lint and TypeScript, then ran 24 Jest tests with exactly the new server-derived issue case failing (23 passed / 1 failed) because the alert did not reach the mounted onboarding UI.
- Minimum propagation GREEN `0cdd18dca7cf226be1957e49826b797bafa169cd` / CI `34168188486` passed `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`. The existing single readiness request now carries optional issue state through typed readiness -> bootstrap -> AdminShell -> OnboardingFlow.
- Real WordPress integration checkpoint `144f674c113fd355d74f1b60ff4dbbc85dd9acab` / CI `34168358072` passed all permanent jobs. The WordPress database lifecycle executes `test-wp-model-readiness.php`, which now validates that provider-step readiness exposes only one of the normalized actionable issue codes.
- Closeout review `5135644263`: **0 Critical / 0 Important unresolved** across correctness, security, accessibility, and performance.

## Security / Accessibility / Performance State
- Admin REST resources use the centralized WordPress admin capability boundary.
- Credentials are write-only; provider secret plaintext/ciphertext is not returned to JavaScript.
- Browser REST nonce transport uses the header path expected by WordPress cookie authentication.
- UI error messages and onboarding issue state are normalized and do not expose arbitrary upstream/provider details.
- Loading uses a polite status region; failures/actionable onboarding issues use alerts; selected navigation exposes `aria-current`; onboarding recovery focus is deterministic.
- Admin JavaScript/config are enqueued only on the plugin admin screen.
- Readiness uses bounded bot pages and exits when a compatible persisted bot is found.
- The shell performs one readiness request at bootstrap; hash navigation and onboarding issue rendering add no duplicate provider/network fetch.

## Current Task — Task 8 Bot Management Screens
Begin under strict TDD against the existing Task 3 bot REST contract. First prove paginated bot-list rendering and the explicit empty state. Then cover create/edit validation, switching records without unsaved-state leakage, delete/archive confirmation behavior, and narrow/mobile WordPress-admin usability.

Task 8 must not advance to Task 9 until focused/full verification, exact-head CI, and correctness/security/accessibility/performance review have no unresolved Critical or Important findings.

## Durable Recovery Sources
- `docs/progress/STATUS.md` — authoritative global/current-task status and detailed recent evidence.
- This milestone ledger — current M12 acceptance/task state.
- `docs/progress/M12-task4-provider-credentials.md` — additional Task 4 evidence.
- PR #17 — durable branch/review/CI integration record.
- Auto-approved M12 design and implementation plan referenced above.

## Completion Checklist
Tasks **1-7 are complete**. M12 remains open until Tasks 8-10, final integration/E2E/security/accessibility/performance gates, exact-final-SHA CI, merge, and fresh post-merge `main` CI are complete.

## Next Milestone
M13 — Knowledge Manager/Debugger, only after M12 is genuinely complete.
