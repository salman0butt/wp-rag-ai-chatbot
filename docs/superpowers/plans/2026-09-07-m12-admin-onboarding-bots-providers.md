# M12 Admin Onboarding, Bot Management & Provider Configuration — Implementation Plan

Status: AUTO-APPROVED — SCHEDULED MODE
Date: 2026-09-07
Design: `docs/superpowers/specs/2026-09-07-m12-admin-onboarding-bots-providers-design.md`

## Execution rules

Follow `AGENTS.md` and `docs/AUTONOMOUS-DEVELOPMENT.md`. Each meaningful behavior uses strict RED -> GREEN -> REFACTOR. A lint/style/documentation failure is never accepted as behavioral RED. Each checkpoint records exact commit/CI evidence. No milestone completion without independent review, security/performance/accessibility review where applicable, exact-final-SHA green CI, merge, and fresh post-merge `main` CI.

## Task 1 — Admin foundation and protected REST bootstrap

**Goal:** establish the WordPress admin control-plane boundary without adding product configuration behavior yet.

Expected files:
- `src/Admin/AdminBootstrap.php`
- `src/Admin/AdminCapability.php` or equivalent policy/value object
- `src/Admin/Rest/AdminRestBootstrap.php`
- `tests/Unit/Admin/*`
- `tests/Integration/Admin/*`
- `src/Core/Bootstrap.php`

RED:
1. Add behavioral tests proving the plugin registers an admin bootstrap from the core bootstrap.
2. Add tests proving the admin menu/page and REST registration are gated by the expected administration capability.
3. Add tests proving non-admin/public contexts do not enqueue M12 admin assets.
4. Execute the focused tests at the exact test commit and capture failure caused by absent M12 admin behavior.

GREEN:
1. Implement the minimum admin bootstrap, capability policy, page registration, scoped asset hook, and REST registration seam.
2. Keep bootstrap data non-secret.
3. Run focused and broader PHP verification.

Checkpoint: commit/push, exact-SHA CI, update M12 durable task evidence.

## Task 2 — Bot aggregate and persistence

**Goal:** create isolated, durable bot configuration records suitable for CRUD and later UI.

Expected files:
- `src/Bots/Bot.php`
- `src/Bots/BotId.php`
- `src/Bots/BotRepository.php`
- `src/Database/Repository/*Bot*`
- migration/schema files only if existing schema cannot represent bot records
- unit/integration tests

RED:
- prove create/read/update/delete-or-archive semantics, stable IDs, validation, isolation between two bot records, and paginated listing.

GREEN:
- add the smallest repository/migration implementation consistent with existing database abstractions.
- avoid adding knowledge, appearance, or analytics fields.

Checkpoint: focused tests, migration/install integration where applicable, exact-SHA CI, durable evidence.

## Task 3 — Bot CRUD REST resources

**Goal:** expose capability-protected bot list/create/read/update/delete operations.

RED:
- unauthorized requests are rejected;
- list pagination is deterministic;
- create/update validation errors are stable;
- mutating bot A cannot alter bot B;
- missing/stale IDs fail deterministically.

GREEN:
- implement controllers/request mapping/response serialization over Task 2 repository.
- keep response schema limited to M12 bot settings.

Checkpoint: REST integration tests + exact-SHA CI.

## Task 4 — Provider credential/configuration REST resource

**Goal:** allow administrators to configure credentials without ever reading secrets back into JavaScript.

RED:
- read response reports configured/source state but contains neither plaintext nor ciphertext;
- write/replace persists via M03 credential store;
- delete/reset follows the credential store's supported semantics;
- unauthorized requests fail;
- unsafe upstream errors are normalized.

GREEN:
- compose existing M03 credential abstractions; do not create a parallel encryption/storage path.

Security checkpoint: explicitly inspect localized data, REST JSON, exceptions, and logs for secret leakage.

## Task 5 — Model capability and onboarding-readiness resources

**Goal:** let the admin UI select only compatible models and derive onboarding state from server truth.

RED:
- provider unavailable/missing credential/unsupported capability are distinguishable;
- model list is normalized and filtered by purpose/capability;
- onboarding readiness advances only when persisted provider/model/bot requirements are satisfied.

GREEN:
- reuse M03 model catalog/provider capability contracts and Task 2 bot state.

Checkpoint: focused + integration tests, exact-SHA CI.

## Task 6 — React admin shell and typed API layer

**Goal:** mount an admin-only React application with robust transport/error handling.

Expected files follow current frontend conventions under the repository's existing JS source/build directories.

RED:
- component tests prove loading/error/empty rendering and REST nonce/header use;
- WordPress admin page boot payload contains only safe fields;
- public/non-plugin screens do not load admin bundle.

GREEN:
- create typed API adapters, app shell/navigation, screen routing/state, accessible status/error primitives.

Verification: typecheck, JS tests, build/package, public asset regression check.

## Task 7 — Onboarding flow

RED:
- first-run state guides provider -> model -> first bot;
- reload resumes from persisted server state;
- unavailable provider/capability produces actionable accessible error state;
- keyboard/focus flow works for validation errors.

GREEN:
- implement the state-driven onboarding screens; do not store completion solely in browser state.

## Task 8 — Bot management screens

RED:
- paginated bot list/empty state;
- create/edit validation;
- switching records cannot leak unsaved state across bots;
- delete/archive confirmation behavior;
- narrow/mobile WordPress admin usability.

GREEN:
- implement list and editor against Task 3 routes.

## Task 9 — Provider/model configuration screens

RED:
- existing credential renders as configured/masked state without secret value;
- replacing a credential never rehydrates the old secret;
- model selector excludes incompatible choices;
- provider/capability errors remain actionable.

GREEN:
- implement provider/settings screens with progressive disclosure and safe model selection.

## Task 10 — M12 integration and closeout

Run and record:
- focused PHP unit/integration suites;
- JS/component/typecheck/build;
- WordPress smoke/E2E for admin navigation, onboarding, provider errors, bot isolation and persistence;
- package validation;
- security review: capabilities, nonces, REST permissions, credential exposure, CSRF/XSS;
- accessibility review: keyboard, focus, labels, announcements, contrast/layout where visual tooling permits;
- performance review: admin-only asset loading, pagination, lazy/on-demand provider data, public widget regression;
- independent code review using the Superpowers review workflow;
- fix/re-review until 0 unresolved Critical/Important findings;
- exact-final-SHA CI green;
- update milestone/progress/test/security/known-issues/tech-debt records as applicable;
- mark PR ready, merge only with expected final head SHA, verify fresh `main` CI;
- mark M12 complete only after post-merge CI; then recover M13.

## Planned verification commands

Use repository scripts/configuration as present at execution time. Typical checks include Composer/PHPUnit/PHPCS or the repository's PHP quality command, npm/pnpm JS test/typecheck/build commands, package validation, and repository WordPress smoke workflow. Do not record a command/result until actually executed or observed through exact-SHA CI.

## Plan self-review

Sequencing preserves dependencies: admin boundary before resources, persistence before CRUD, server provider/readiness behavior before UI, and UI before E2E closeout. Each behavior has an observable RED path. Security, accessibility, performance, review, exact-SHA CI, durable documentation, merge, and post-merge verification are explicit gates. No M13/M14/M21 scope is included.

AUTO-APPROVED — SCHEDULED MODE
