# M14 Task 2 Widget Configuration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Persist normalized M14 appearance per bot and expose only a public-safe widget configuration projection for enabled bots.

**Architecture:** Keep the M12 `Bot` aggregate/provider authority unchanged. Add appearance persistence as a separate bot-scoped repository over an M14 schema migration so appearance changes do not broaden the existing bot runtime aggregate. Build a separate public projection service that joins `BotRepository` with normalized appearance and returns an explicit allow-list; provider/model/retrieval internals never enter that DTO.

**Tech Stack:** PHP 8.2+, WordPress `$wpdb` abstraction through the existing `Connection` contract, repository interfaces, PHPUnit 10, PHPStan, PHPCS/WPCS, GitHub Actions permanent gates.

**Spec:** `docs/superpowers/specs/2026-09-12-m14-frontend-chatbot-customizer-design.md`

## Global Constraints

- Reuse existing bot persistence and `AppearanceConfig`; do not add a parallel bot aggregate.
- Public widget configuration must not expose credentials, provider/model identifiers, embedding/vector-store configuration, retrieval limits, arbitrary CSS, raw HTML, or unrestricted bot records.
- Unknown/disabled bots fail closed.
- Behavior changes require genuine RED -> GREEN evidence before production code is added.
- Permanent gates are `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.

---

### Task 2A: Bot-scoped appearance persistence

**Files:**
- Create: `src/Frontend/BotAppearanceRepository.php`
- Create: `src/Database/Migrations/V011AddBotAppearance.php`
- Create: `src/Database/Repository/WpdbBotAppearanceRepository.php`
- Modify: `src/Database/DatabaseSchema.php`
- Modify: `src/Database/DatabaseBootstrap.php`
- Test: `tests/Unit/Database/BotAppearanceMigrationContractTest.php`
- Test: `tests/Unit/Database/Repository/WpdbBotAppearanceRepositoryTest.php`

**Interfaces:**
- Consumes: `Bots\BotId`, `Frontend\AppearanceConfig`, existing `Database\Connection`, `Database\TableNames`.
- Produces: `BotAppearanceRepository::find(BotId): AppearanceConfig` and `BotAppearanceRepository::save(BotId, AppearanceConfig): void`.

- [ ] **Step 1: Write the migration contract RED**

Create `BotAppearanceMigrationContractTest` asserting `DatabaseSchema::VERSION === 11`, `V011AddBotAppearance` exists/version 11, and its DDL adds one bounded `appearance_json` text field to the existing bots table rather than creating a second bot table.

- [ ] **Step 2: Push and verify the intended RED**

Run through GitHub Actions. A genuine RED must reach PHPUnit and fail because version 11 / `V011AddBotAppearance` is absent. PHPCS, PHPStan, fixture, or infrastructure failures are `NOT RED` and must be repaired before production code.

- [ ] **Step 3: Implement the minimal migration**

Add `V011AddBotAppearance`, increment `DatabaseSchema::VERSION` to 11, and register the migration after V010 in `DatabaseBootstrap`. The new column stores JSON text only; defaults/revalidation remain owned by `AppearanceConfig`.

- [ ] **Step 4: Verify migration GREEN**

Require exact-head GREEN on all permanent jobs before repository behavior is started.

- [ ] **Step 5: Write the repository RED**

Specify that missing/blank stored appearance projects `AppearanceConfig::defaults()`, valid stored JSON rehydrates only through `AppearanceConfig::from_array()`, `save()` serializes only `AppearanceConfig::to_array()`, missing bot/update failure fails closed, malformed persisted JSON never passes raw data through.

- [ ] **Step 6: Push and verify the intended repository RED**

Require PHPUnit to reach missing `BotAppearanceRepository`/`WpdbBotAppearanceRepository` behavior with unrelated quality gates satisfied.

- [ ] **Step 7: Implement the minimal repository**

Implement `BotAppearanceRepository` and `WpdbBotAppearanceRepository`. Read exactly `appearance_json` by `bot_id`; write exactly the normalized `to_array()` JSON projection. Do not read/write provider/model fields as part of appearance persistence.

- [ ] **Step 8: Verify repository GREEN and review**

Run permanent gates and scoped correctness/security/performance/architecture review. Resolve all Critical/Important findings before continuing.

---

### Task 2B: Public-safe widget configuration projection

**Files:**
- Create: `src/Frontend/WidgetConfig.php`
- Create: `src/Frontend/WidgetConfigResolver.php`
- Test: `tests/Unit/Frontend/WidgetConfigResolverTest.php`

**Interfaces:**
- Consumes: `BotRepository::find(BotId): ?Bot`, `BotAppearanceRepository::find(BotId): AppearanceConfig`.
- Produces: `WidgetConfigResolver::resolve(BotId): ?WidgetConfig` and `WidgetConfig::to_array(): array` with only public allow-listed fields.

- [ ] **Step 1: Write the public projection RED**

Specify an enabled bot returns a DTO containing only stable public bot identity/name plus normalized `appearance`; unknown or disabled bots return `null`. Assert serialized output does not contain `provider_id`, `model_id`, credentials, embedding/vector-store settings, retrieval limits, arbitrary CSS, or raw bot records.

- [ ] **Step 2: Push and verify genuine RED**

Require PHPUnit to reach the intended missing resolver/DTO behavior after PHPCS/PHPStan prerequisites pass.

- [ ] **Step 3: Implement the minimal projection**

Resolve the bot once; fail closed unless enabled; resolve appearance once; return an immutable explicit DTO. Keep endpoint/capability fields deferred until the public transport/mount tasks establish their concrete authorities rather than inventing URLs here.

- [ ] **Step 4: Verify GREEN and review**

Require exact-head permanent CI GREEN. Review correctness, public-data security boundary, performance, and architecture; resolve all Critical/Important findings.

- [ ] **Step 5: Persist Task 2 evidence**

Update `docs/progress/STATUS.md`, add `docs/progress/M14-TASK2-WIDGET-CONFIG.md`, and update PR #19 with exact RED/NOT RED/GREEN SHAs, CI runs, review results, and exact next unfinished M14 Task 3 unit.
