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
2. **COMPLETE** — Bot aggregate/repository and persistence.
3. **ACTIVE** — Bot CRUD REST resources with pagination/isolation.
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
- RED `37055df98840b7a8da29f3d16da021b2949cfe8e`: tests required the concrete protected menu, mount root, strict screen detection, versioned REST route, and safe non-secret bootstrap response; exact CI `34088758533` failed on exactly those missing behaviors. Implementation `d01ab1c445bd9743b61bb390616289f40d971a24` made all 633 behavioral tests pass, but CI correctly rejected two expectation-only tests as PHPUnit risky tests. Test-only metadata fix `0638dc14197054f48efc5fd51e480b74ca394073` produced the final Task 1 GREEN.

Task 2 preserved a genuine behavioral RED before production implementation:

- Initial test-only heads `46c2eae821ed5f3b636af3ae680895e521778cd5` / CI `34089876929` and `36c16eb2fb387df739bbff2ed4930a4e0ebcf78e` / CI `34089965680` were rejected as TDD evidence because PHPCS stopped before PHPUnit.
- Genuine RED `2f9adb8daf5b8cfad1607a5462c779ec31f4abaf`, CI `34090057465`: PHPCS and PHPStan passed; PHPUnit executed 645 tests and failed exactly the 12 new Task 2 cases because `Bot`, `BotId`, `V010CreateBotsTable`, and `WpdbBotRepository` did not yet exist.
- Minimum implementation added the bounded bot aggregate/repository contract, V010 schema, and wpdb repository. Intermediate verification failures were treated as quality/static/test-fixture defects rather than fabricated RED: PHPCS metadata, a literal-query PHPStan finding, a unit-test closure capture bug, and stale schema-9 assertions were corrected without broadening Task 2.
- Final Task 2 GREEN implementation/integration head `1bbfe79994f7fa850429e7f5aa968f44e4c54c31`, CI `34091206697`: PHPStan 279/279 with 0 errors, PHPUnit 645/645 with 2,700 assertions, Composer audit clean, plus JS/package/WordPress smoke GREEN.

Backend and component tests remain required throughout M12.

## Integration Test Evidence
Task 1 exact-head WordPress smoke on `0638dc14197054f48efc5fd51e480b74ca394073`, run `34088991361`, passed activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and environment cleanup.

Task 2 exact-head WordPress smoke on `1bbfe79994f7fa850429e7f5aa968f44e4c54c31`, run `34091206697`, passed the full lifecycle. `test:wp:database` now validates schema V010, the bots table/indexes, clean install, V1 -> V10 upgrade, retain-by-default uninstall, opt-in deletion, and clean reinstall. The new real MySQL bot smoke creates two bots, proves distinct stable IDs, bounded deterministic pagination, updates bot A without mutating bot B, rejects a stale optimistic write, deletes A without deleting B, and reruns after upgrade/reinstall.

Bot REST integration belongs to Task 3; provider REST integration belongs to Tasks 4-5.

## E2E / Visual Verification
No user-facing React UI exists in Tasks 1-2, so visual/accessibility UI verification is not yet applicable. Desktop/mobile admin, loading/empty/error, keyboard navigation, and provider capability errors remain required for later M12 UI tasks.

## Security Review
Task 1 review verified one centralized `manage_options` policy for menu/REST access, a capability-protected read-only admin bootstrap route, and a response limited to stable plugin/API identifiers. No provider credentials, encrypted credential material, chat content, owner scope, mutable configuration, or raw diagnostics are returned.

Task 2 review verified bot IDs are opaque 128-bit lowercase-hex identifiers; persisted human/provider/model text is bounded; exact-ID CRUD and optimistic `bot_id + version` updates fail closed on stale targets; listing is deterministic and page-size bounded to 100; user-controlled values do not enter raw SQL; reads bind identifiers/values through the prepared connection boundary and writes use structured wpdb APIs. Task 2 stores provider/model identifiers only and introduces no credential, transcript, retrieval, owner-scope, knowledge, appearance, or analytics material.

Mutation capability/nonces/CSRF coverage remains mandatory when Task 3 adds REST writes.

## Accessibility Review where UI exists
Not applicable to Tasks 1-2; no interactive UI has been introduced yet.

## Performance Review where relevant
Task 1 admin asset registration remains strictly scoped and enqueues nothing until Task 6.

Task 2 bounds list pages to 100 records, uses a unique `bot_id` index and a `(created_at, bot_id)` deterministic-list index, and performs one count plus one bounded page read. No provider/network call or public request path is added.

## Code Review Findings
- Task 1 scoped correctness/security/performance review on exact head `0638dc14197054f48efc5fd51e480b74ca394073`, PR review `5128526998`: **0 Critical / 0 Important findings**.
- Task 2 scoped correctness/security/performance review on exact head `1bbfe79994f7fa850429e7f5aa968f44e4c54c31`, PR review `5128744956`: **0 Critical / 0 Important findings**.
- PR #17 has no inline review threads at the Task 2 checkpoint.

## Fixes
Task 1's only post-behavioral-GREEN follow-up was test metadata for Brain Monkey expectation-only tests.

Task 2 verification fixes were limited to repository coding-standard metadata, prepared `%i` table binding for the list query, a unit-test closure-capture defect, and stale schema-9 test/smoke assertions. The real WordPress bot persistence smoke was added before Task 2 closeout.

## Fresh Verification Commands
Observed through GitHub Actions on exact implementation heads: Composer validation/PHP verification/audit, JS verification/audits/gating tests, package build/assertion, and WordPress smoke suites including database lifecycle integration.

## Fresh Verification Results
- Recovery `main` SHA `3bd73cb99efc1f051c884f8f522f08b0c9938a84`: permanent jobs GREEN before this M12 continuation.
- Task 1 final implementation SHA `0638dc14197054f48efc5fd51e480b74ca394073`, CI `34088991361`: `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all GREEN.
- Task 2 genuine RED SHA `2f9adb8daf5b8cfad1607a5462c779ec31f4abaf`, CI `34090057465`: static gates passed and PHPUnit failed exactly 12 absent-Task-2 behavior tests.
- Task 2 final implementation/integration SHA `1bbfe79994f7fa850429e7f5aa968f44e4c54c31`, CI `34091206697`: `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all GREEN; PHPUnit 645/645 / 2,700 assertions; Composer audit clean; real WordPress/MySQL bot lifecycle GREEN.

## Commits
Task 1 key commits:
- `3fd522c631c23c7b53643d9ae2a0fe596c4d2318` — core-bootstrap RED.
- `47212e9f14112bda74baccf7e3bbd083837e81a4` — first GREEN.
- `a0239d5f6aee8049a61219677d5788f61aeb4042` — capability/REST RED.
- `84a957e41c423634954d34ad07f7d55a009b9667` — capability/REST GREEN.
- `37055df98840b7a8da29f3d16da021b2949cfe8e` — concrete admin-surface RED.
- `0638dc14197054f48efc5fd51e480b74ca394073` — final Task 1 GREEN.

Task 2 key commits:
- `2f9adb8daf5b8cfad1607a5462c779ec31f4abaf` — genuine bot-domain/persistence behavioral RED.
- `c195b577233df83c944808363181f0513f5b124f` — stable bot identifier.
- `4878d8578d8ee5f7b207929fe17b8c9f6b9568df` — bot aggregate.
- `dd36f57475f0d496976b0c30d2a69eac1e55246c` — bot repository contract.
- `e4df979813cc1eb0b015668b15c9a05857ec48dc` — V010 bots migration.
- `05c3a2205104fa505d130f2f4375b0f27da79680` — wpdb bot repository.
- `f248d218a1726be0e3593a1b2f4003903576e124` — schema/migration composition through V010.
- `b40f6ffdaeffdb9a0f7f2708f7f0aaced69117a3` — prepared identifier binding fix.
- `113e9101b9264cdaf144836317a4b71cc6342183` — real WordPress bot persistence smoke.
- `668637262859e1a51d2423cb7f02896c9c29aac1` — database lifecycle includes bot persistence/schema.
- `4c91dfcdcad25169f917898d781358acfa74b36f` / `44af03d694efba4f14b0d8450736ec07d4861f10` — stale schema/unit-test fixture corrections.
- `1bbfe79994f7fa850429e7f5aa968f44e4c54c31` — final Task 2 implementation/integration checkpoint.

## Files Changed
Task 1 added the protected admin bootstrap/capability/REST seam and core wiring.

Task 2 added `src/Bots/Bot.php`, `BotId.php`, `BotRepository.php`, `src/Database/Migrations/V010CreateBotsTable.php`, `src/Database/Repository/WpdbBotRepository.php`, schema/table/bootstrap composition, focused unit contracts, and `scripts/test-wp-bots.php` plus schema-10 lifecycle smoke updates.

## Known Limitations
Tasks 1-2 intentionally provide only the protected admin foundation and persisted bot configuration layer. No bot REST CRUD, provider credential/model resource, onboarding state, or React admin application exists yet. Provider/model capability compatibility is deliberately deferred to Task 5. These are planned later M12 tasks, not Task 2 defects.

## Documentation Updated
M12 design, implementation plan, milestone ledger, and global status checkpoint are the durable recovery sources. Task 2 exact RED/GREEN, integration, security/performance, review, and CI evidence is recorded here and in PR #17.

## Completion Checklist
Tasks 1-2 are complete. M12 remains open until Tasks 3-10, final independent review/security/accessibility/performance gates, exact-final-SHA CI, merge, and fresh post-merge `main` CI are complete.

## Next Milestone
M13 — Knowledge Manager/Debugger, only after M12 is genuinely complete.
