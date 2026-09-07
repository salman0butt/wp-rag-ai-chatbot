# M12 — Admin Onboarding, Bot Management & Provider Configuration

Status: IN PROGRESS

## Goal
Build a professional WordPress-native admin shell, onboarding, multi-bot management, and provider/model configuration UI.

## Dependencies
M03, M11 backend contracts, M01 frontend tooling.

## In Scope
Admin navigation/shell; onboarding; bot CRUD/config; provider credential/model/capability UI; validation/errors; settings progressive disclosure; usage-safe model selection.

## Out of Scope
Knowledge/debugger UI M13; appearance editor M14; analytics M21.

## Architecture
React/TS where useful, backed by granular capability-protected REST endpoints; no secrets returned to JS.

Selected design: `docs/superpowers/specs/2026-09-07-m12-admin-onboarding-bots-providers-design.md`.
Implementation plan: `docs/superpowers/plans/2026-09-07-m12-admin-onboarding-bots-providers.md`.
Both are **AUTO-APPROVED — SCHEDULED MODE**.

## Acceptance Criteria
Admin capabilities enforced; onboarding handles unavailable provider/capability states; multiple bots are isolated; secrets remain write-only/masked; typecheck/build/component/E2E flows pass.

## Tasks
1. **COMPLETE** — Admin foundation and capability-protected REST bootstrap.
2. **ACTIVE** — Bot aggregate/repository and persistence.
3. Bot CRUD REST resources with pagination/isolation.
4. Provider credential/configuration REST resource with write-only secrets.
5. Provider model/capability and onboarding-readiness resources.
6. React admin shell and typed API layer.
7. Onboarding flow.
8. Bot management screens.
9. Provider/model configuration screens.
10. Integration/E2E, security, accessibility, performance, review, durable closeout.

## TDD Evidence
Task 1 preserved three genuine behavioral RED -> GREEN cycles:

- RED `3fd522c631c23c7b53643d9ae2a0fe596c4d2318`: core bootstrap test required the absent `AdminBootstrap`; exact CI reached PHPUnit and failed for the missing class after static analysis passed. GREEN `47212e9f14112bda74baccf7e3bbd083837e81a4`: minimum admin bootstrap seam and core wiring; exact CI `34088324050` fully GREEN.
- RED `a0239d5f6aee8049a61219677d5788f61aeb4042`: tests required the absent centralized admin capability and admin REST bootstrap; exact CI `34088573646` reached PHPUnit and failed for those missing behaviors after PHPCS/PHPStan passed. GREEN `84a957e41c423634954d34ad07f7d55a009b9667`: capability policy, REST seam, and admin hook registration; exact PHP verification GREEN.
- RED `37055df98840b7a8da29f3d16da021b2949cfe8e`: tests required the concrete protected menu, mount root, strict screen detection, versioned REST route, and safe non-secret bootstrap response; exact CI `34088758533` failed on exactly those missing behaviors. Implementation `d01ab1c445bd9743b61bb390616289f40d971a24` made all 633 behavioral tests pass, but CI correctly rejected two expectation-only tests as PHPUnit risky tests. That was a verification/test-metadata failure, not RED. Test-only metadata fix `0638dc14197054f48efc5fd51e480b74ca394073` produced the final Task 1 GREEN.

Backend and component tests remain required throughout M12.

## Integration Test Evidence
Task 1 exact-head WordPress smoke on `0638dc14197054f48efc5fd51e480b74ca394073`, run `34088991361`, passed activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and environment cleanup. Bot/provider REST persistence integration belongs to Tasks 2-5.

## E2E / Visual Verification
No user-facing React UI exists in Task 1, so visual/accessibility UI verification is not yet applicable. Desktop/mobile admin, loading/empty/error, keyboard navigation, and provider capability errors remain required for later M12 UI tasks.

## Security Review
Task 1 review verified one centralized `manage_options` policy for menu/REST access, a capability-protected read-only admin bootstrap route, and a response limited to stable plugin/API identifiers. No provider credentials, encrypted credential material, chat content, owner scope, mutable configuration, or raw diagnostics are returned. No mutating route exists yet, so mutation nonce/CSRF coverage remains required when Tasks 3-5 add writes.

## Accessibility Review where UI exists
Not applicable to Task 1 beyond the deterministic mount boundary; no interactive UI has been introduced yet.

## Performance Review where relevant
The admin asset hook is strictly scoped to `toplevel_page_wp-rag-ai-chatbot` and intentionally enqueues nothing until Task 6. Task 1 therefore adds no public or global-admin JavaScript/CSS payload.

## Code Review Findings
Scoped Task 1 correctness/security/performance review on exact head `0638dc14197054f48efc5fd51e480b74ca394073`, PR review `5128526998`: **0 Critical / 0 Important findings**. PR #17 has no inline review threads.

## Fixes
The only verification follow-up after functional GREEN was test metadata: two Brain Monkey expectation-only tests were marked `#[DoesNotPerformAssertions]`, matching existing repository convention. Production code was unchanged by that fix.

## Fresh Verification Commands
Observed through GitHub Actions on the exact Task 1 final implementation SHA: Composer/PHP verification and audit, JS verification and audits/gating tests, package build/assertion, and WordPress smoke suites.

## Fresh Verification Results
- Recovery `main` SHA `3bd73cb99efc1f051c884f8f522f08b0c9938a84`: all permanent jobs GREEN before M12 branch creation.
- Task 1 final implementation SHA `0638dc14197054f48efc5fd51e480b74ca394073`, CI `34088991361`: `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all GREEN.
- PHP verification at the failed intermediate `d01ab1c...` head proved 633/633 behavioral tests passed; the only rejection was two risky expectation-only tests, which were corrected without production changes.

## Commits
- `f0176ed61d1341dec0e134a300613f384d2cc8e3` — M12 design.
- `8439d6be7f622a3291c92a0c22cd8e1bdf9eb983` — M12 implementation plan.
- `3fd522c631c23c7b53643d9ae2a0fe596c4d2318` — Task 1 core-bootstrap RED.
- `47212e9f14112bda74baccf7e3bbd083837e81a4` — Task 1 first GREEN.
- `a0239d5f6aee8049a61219677d5788f61aeb4042` — Task 1 capability/REST RED.
- `84a957e41c423634954d34ad07f7d55a009b9667` — Task 1 capability/REST GREEN.
- `37055df98840b7a8da29f3d16da021b2949cfe8e` — Task 1 concrete admin-surface RED.
- `d01ab1c445bd9743b61bb390616289f40d971a24` — concrete admin-surface implementation.
- `0638dc14197054f48efc5fd51e480b74ca394073` — final Task 1 GREEN after test-metadata correction.

## Files Changed
Task 1 added `src/Admin/AdminBootstrap.php`, `src/Admin/AdminCapability.php`, `src/Admin/Rest/AdminRestBootstrap.php`, admin/core unit tests, and wired the admin bootstrap through `src/Core/Bootstrap.php`, alongside M12 planning/durable-state documents.

## Known Limitations
Task 1 intentionally provides only the protected administration bootstrap. No bot persistence/CRUD, provider credential/model resource, onboarding state, or React admin application exists yet. Those are Tasks 2-9, not Task 1 defects.

## Documentation Updated
M12 design, implementation plan, milestone ledger, and global status checkpoint.

## Completion Checklist
Task 1 is complete. M12 remains open until Tasks 2-10, independent final review, security/accessibility/performance gates, exact-final-SHA CI, merge, and fresh post-merge `main` CI are complete.

## Next Milestone
M13 — Knowledge Manager/Debugger, only after M12 is genuinely complete.
