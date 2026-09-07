# M12 — Admin Onboarding, Bot Management & Provider Configuration

Status: **IN PROGRESS — Tasks 1-6 COMPLETE; Task 7 ACTIVE**

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
7. **ACTIVE** — Onboarding flow.
8. Bot management screens.
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
Task 6 now provides:
- a typed same-origin REST client with WordPress nonce in `X-WP-Nonce`, never in URLs;
- normalized safe REST failures that do not surface arbitrary upstream messages;
- loading, empty, error, and ready UI primitives;
- accessible status/error semantics and labelled navigation with `aria-current`;
- deterministic hash routing and hash-change rerender without readiness refetch;
- safe WordPress boot configuration;
- a deterministic admin mount boundary with automatic bundle bootstrap;
- server-derived shell state from `/admin/onboarding/readiness`;
- fail-closed behavior for missing boot configuration or missing browser fetch transport;
- strict plugin-screen-only bundle/config enqueue, preserving public and unrelated-admin asset isolation.

Key TDD checkpoints:
- Mount RED `f057d77c2f62e3b6fc6140fb28cd7c8d1a9819e9` / CI `34134906858`: lint/typecheck passed and Jest failed exactly the absent mount behavior. GREEN `979be604c1b428d06e6453272aec04692eaa8122` / CI `34135031641` passed all permanent jobs.
- Automatic-bootstrap RED `64755339aa8a20f822d91ba396e90c5e0d2c354f` / CI `34135499494`: existing tests passed and exactly the new bundle-mount assertion failed. Fix `ccdec102cb363f272ac70656bd447be31b6afef1` / CI `34135632271` passed full CI.
- Final closeout review found one Important defect: valid boot configuration plus unavailable `window.fetch` rendered hard-coded `ready` without server truth. Genuine regression RED `95d813045c3b530aa4208e155a64588c069b20c7` / CI `34149025827` passed lint/typecheck and ran 16 Jest tests with exactly the new transport test failing (1 failed / 15 passed). Minimum fix `759b77acb45a9556ce907f9eaf424cb11521f9c4` renders the existing safe error state. Test-alignment checkpoint `4ee313e957f0d7ef8c67fa776df07ff647c8212e` verifies the normal path as loading -> server-derived ready; CI `34149262866` passed `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.
- Task 6 closeout review `5134434515`: **0 Critical / 0 Important unresolved**.

## Security / Accessibility / Performance State
- Admin REST resources use the centralized WordPress admin capability boundary.
- Credentials are write-only; provider secret plaintext/ciphertext is not returned to JavaScript.
- Browser REST nonce transport uses the header path expected by WordPress cookie authentication.
- UI error messages are normalized and do not expose arbitrary upstream/provider details.
- Loading uses a polite status region; failures use an alert; selected navigation exposes `aria-current`.
- Admin JavaScript/config are enqueued only on the plugin admin screen.
- Readiness uses bounded bot pages and exits when a compatible persisted bot is found.
- The Task 6 shell performs one readiness request at bootstrap; hash navigation does not refetch it.

## Current Task — Task 7 Onboarding Flow
Start with strict TDD against persisted server truth. The first behavioral RED must prove first-run onboarding follows `next_step` through provider -> model -> first bot and that reload resumes from server state rather than browser-only completion. Continue with actionable unavailable-provider/capability errors, accessible validation, keyboard/focus behavior, and successful persistence before considering Task 7 complete.

Task 7 must not advance to Task 8 until focused/full verification, exact-head CI, and correctness/security/accessibility/performance review have no unresolved Critical or Important findings.

## Durable Recovery Sources
- `docs/progress/STATUS.md` — authoritative global/current-task status and detailed recent evidence.
- This milestone ledger — current M12 acceptance/task state.
- `docs/progress/M12-task4-provider-credentials.md` — additional Task 4 evidence.
- PR #17 — durable branch/review/CI integration record.
- Auto-approved M12 design and implementation plan referenced above.

## Completion Checklist
Tasks **1-6 are complete**. M12 remains open until Tasks 7-10, final integration/E2E/security/accessibility/performance gates, exact-final-SHA CI, merge, and fresh post-merge `main` CI are complete.

## Next Milestone
M13 — Knowledge Manager/Debugger, only after M12 is genuinely complete.
