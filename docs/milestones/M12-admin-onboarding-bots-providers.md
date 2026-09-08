# M12 — Admin Onboarding, Bot Management & Provider Configuration

Status: **IN PROGRESS — Tasks 1-8 COMPLETE; Task 9 ACTIVE**

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
8. **COMPLETE** — Bot management screens.
9. **ACTIVE** — Provider/model configuration screens.
10. Integration/E2E, security, accessibility, performance, review, durable closeout.

## Completed Task Evidence

### Task 1 — Admin foundation
Final implementation `0638dc14197054f48efc5fd51e480b74ca394073`; CI `34088991361` passed `php-quality`, `js-quality`, `package`, and `wordpress-smoke`. Scoped review `5128526998`: **0 Critical / 0 Important**.

### Task 2 — Bot persistence
Final implementation/integration `1bbfe79994f7fa850429e7f5aa968f44e4c54c31`; CI `34091206697` passed full quality/package/WordPress integration. Scoped review `5128744956`: **0 Critical / 0 Important**.

### Task 3 — Bot CRUD REST
Genuine route RED `b7950a33c51171532c1d270076f8b847b70d2d21` / CI `34097504341`; final integration `047de805f6481ad36a334e63e2e44efb62989553` / CI `34098207281` passed full CI and real WordPress REST authorization/CRUD/pagination/isolation lifecycle. Scoped review `5129481296`: **0 Critical / 0 Important**.

### Task 4 — Provider credentials/configuration
Core RED `26ce3eefef6dd7d5198b77a669268bc07cbcafbf` / CI `34102834048`; protected-route RED `4ff5916c809cf104d7e753a2d5531837ef093a49` / CI `34103192408`. Final implementation `759db1cc1c20ccebb906fc03cc51d1434dc6110a` / CI `34103975996` passed full CI. The REST surface reuses M03 credential storage/resolution and never serializes plaintext or ciphertext. Scoped review `5130113628`: **0 Critical / 0 Important**.

### Task 5 — Model capability/readiness
Resource RED `471f7fd753f571a99a716d8bf8381bb4dd3d1987` / CI `34113946121`; REST-route RED `6878e36f6073b4fc69f1f5dc2f49a9a86290c009` / CI `34114690187`. Review found and fixed the page-1-only onboarding readiness defect under genuine regression RED `c19cb4395680a9c2c13b8a70f391763aebda9b17` / CI `34119267966`. Final implementation `080c973dbd011192c88c3f56941f15d3495d504a` / CI `34119414309` passed full CI. Post-fix review `5131750259`: **0 Critical / 0 Important unresolved**.

### Task 6 — React admin shell and typed API
Task 6 established the typed same-origin REST client, nonce headers, normalized safe errors, loading/empty/error/ready states, accessible navigation, hash routing, safe admin boot config, automatic plugin-screen-only mount, server-derived readiness, and fail-closed missing-config/transport behavior. Final regression RED `95d813045c3b530aa4208e155a64588c069b20c7` / CI `34149025827`; verified implementation `4ee313e957f0d7ef8c67fa776df07ff647c8212e` / CI `34149262866` passed full CI. Closeout review `5134434515`: **0 Critical / 0 Important unresolved**.

### Task 7 — Onboarding flow
Persisted server readiness drives provider -> model -> first bot -> complete and reload resume. Server issue normalization RED `ed656605c927c69761cc23a4ba1fc540ee63a4d9` / CI `34167918116`; mounted issue propagation RED `1da2935c4066aa18b2e41fca511d3ce38fead75d` / CI `34168105909`; final WordPress integration `144f674c113fd355d74f1b60ff4dbbc85dd9acab` / CI `34168358072` passed all permanent jobs. Closeout review `5135644263`: **0 Critical / 0 Important unresolved**.

### Task 8 — Bot management screens
Task 8 now covers the complete planned surface: bounded paginated list and empty state; accessible create editor; persisted create with authoritative refresh; local required-field validation and first-invalid focus; persisted optimistic-version edit; selected-record editor isolation; explicit destructive delete confirmation; hash/page navigation and selected-record `aria-current`; and plugin-scoped narrow/mobile WordPress-admin styling.

Representative final RED/GREEN evidence:
- deletion RED `158c9f17c4c1020d06919b58782d8fe727589835` / CI `34192114972`, followed by verified deletion implementation `e0d36cc634ff27cdf2e9892d348276e76e7121bc`;
- pagination/selection RED `85e6181ab2dad4df0a85b3aa3549952c6231a411` / CI `34196934058`, verified implementation `3d1557dafd0576f444c989ca19c58f3c7d33a5f9` / CI `34197337518`;
- responsive-admin RED `819d0703efcfd7dc0013e63bd34959ef0077022a` / CI `34201262961`, responsive implementation `0abdcae1a71677819590e4d49d3dac0e02b89dfc` / CI `34201450083`.

Final implementation/durable head before closeout documentation: `7b6242230c5e88a34e728b0b830feee881622b08`. Exact-head CI `34201866816` passed the repository workflow (`php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`). Fresh final correctness/security/accessibility/performance review `5139437799`: **0 Critical / 0 Important unresolved**. Detailed closeout: `docs/progress/M12-TASK8-CLOSEOUT.md`.

## Security / Accessibility / Performance State
- Admin REST resources use the centralized WordPress admin capability boundary.
- Credentials are write-only; provider secret plaintext/ciphertext is not returned to JavaScript.
- Browser REST nonce transport uses the header path expected by WordPress cookie authentication.
- UI errors and onboarding issue state are normalized and do not expose arbitrary upstream/provider details.
- Loading uses a polite status region; failures/actionable onboarding issues use alerts; selected navigation/records expose `aria-current`; validation focus is deterministic.
- Admin JavaScript/config/CSS are plugin-screen-only.
- Bot list rendering is bounded and paginated; record switching does not refetch; provider/readiness work remains server-derived.

## Current Task — Task 9 Provider/Model Configuration Screens
Begin under strict TDD against the existing Task 4 credential resource and Task 5 model capability/readiness resources.

Task 9 RED must prove at minimum:
- an existing credential renders only configured/masked/source state and never secret value;
- replacing a credential never rehydrates the old secret into browser state;
- model choices exclude incompatible provider/capability options;
- provider/capability failures remain actionable and accessible.

GREEN must implement the smallest provider/settings screens with progressive disclosure and safe model selection. Do not create a parallel credential store, do not expose plaintext/ciphertext, and do not move provider authority into browser state.

Task 9 must not advance to Task 10 until focused/full verification, exact-head CI, and correctness/security/accessibility/performance review have no unresolved Critical or Important findings.

## Durable Recovery Sources
- `docs/progress/STATUS.md` — authoritative global/current-task status.
- `docs/progress/M12-TASK8-CLOSEOUT.md` — final Task 8 reconciliation/review evidence.
- `docs/progress/M12-task4-provider-credentials.md` — Task 4 credential evidence reused by Task 9.
- PR #17 — durable branch/review/CI integration record.
- Auto-approved M12 design and implementation plan referenced above.

## Completion Checklist
Tasks **1-8 are complete**. M12 remains open until Tasks 9-10, final integration/E2E/security/accessibility/performance gates, exact-final-SHA CI, merge, and fresh post-merge `main` CI are complete.

## Next Milestone
M13 — Knowledge Manager/Debugger, only after M12 is genuinely complete.
