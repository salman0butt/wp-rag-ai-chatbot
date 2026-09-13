# M14 Task 3 Admin Appearance Contracts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add administrator-only REST read/save contracts for normalized bot appearance without exposing unrestricted bot/provider/runtime configuration.

**Architecture:** Introduce one focused `AppearanceRestResource` over the existing `BotAppearanceRepository` and `AppearanceConfig` authorities, then bind it through `AdminRestBootstrap` at a bot-scoped administrator route protected by the existing `AdminCapability::can_manage` permission callback. REST callbacks remain thin: route parsing/closed payload validation occurs at the HTTP boundary, while normalization/persistence/error projection stays in the focused resource/domain layer.

**Tech Stack:** PHP 8+, WordPress REST API, PHPUnit, PHPStan, PHPCS, existing `BotId`, `AppearanceConfig`, `BotAppearanceRepository`, `WpdbBotAppearanceRepository`, `AdminCapability`, `AdminRestBootstrap`.

**Spec:** `docs/superpowers/specs/2026-09-12-m14-frontend-chatbot-customizer-design.md`

## Global Constraints

- Administrator appearance routes must use the existing `AdminCapability::can_manage` permission callback.
- Accept only normalized appearance fields already defined by `AppearanceConfig`; unknown/unsafe fields fail closed.
- Do not accept or return provider credentials, provider/model authority, embedding/vector-store settings, retrieval limits, raw HTML, arbitrary CSS, or unrestricted bot records.
- Missing/invalid bot identifiers and persistence failures use stable non-sensitive error codes.
- Reuse the existing `BotAppearanceRepository`; do not add a second appearance persistence implementation.
- Behavior changes require genuine RED -> GREEN chronology and exact-head permanent-gate verification.

---

### Task 1: Administrator appearance resource

**Files:**
- Create: `src/Admin/Rest/AppearanceRestResource.php`
- Create: `tests/Unit/Admin/AppearanceRestResourceTest.php`

**Interfaces:**
- Consumes: `BotAppearanceRepository::find(BotId): AppearanceConfig`, `BotAppearanceRepository::save(BotId, AppearanceConfig): void`, `AppearanceConfig::from_array(array<string,mixed>): AppearanceConfig`, `AppearanceConfig::to_array(): array`.
- Produces: `AppearanceRestResource::read(string): array<string,mixed>` and `AppearanceRestResource::write(string,array<string,mixed>): array<string,mixed>`.

- [ ] **Step 1: Write the failing resource test**

Cover these behaviors:

```php
$repository->expects( self::once() )->method( 'find' )->with( $bot_id )->willReturn( $appearance );
self::assertSame(
    array( 'appearance' => $appearance->to_array() ),
    ( new AppearanceRestResource( $repository ) )->read( $bot_id->value )
);
```

Also require:

```php
self::assertSame( 'invalid_bot_id', $resource->read( 'bad-id' )['error']['code'] );
self::assertSame( 'invalid_appearance', $resource->write( $bot_id->value, array( 'unknown' => true ) )['error']['code'] );
```

For a valid save, assert `AppearanceConfig::from_array()` normalization is persisted once and the response echoes only `appearance`.

- [ ] **Step 2: Push the test-only checkpoint and verify genuine RED**

Expected PHPUnit failure: missing `AppearanceRestResource`; PHPCS/PHPStan must not be the reason for failure.

- [ ] **Step 3: Implement the minimal resource**

`read()` parses `BotId`, calls the existing repository exactly once, and returns only normalized appearance. `write()` parses `BotId`, constructs `AppearanceConfig::from_array($payload)`, persists it once, and returns only normalized appearance. Convert invalid identifiers/appearance and repository `RuntimeException` failures to stable non-sensitive error codes.

- [ ] **Step 4: Verify exact-head GREEN**

Require `php-quality`, `js-quality`, `package`, and `wordpress-smoke` GREEN on the exact implementation SHA before treating Task 1 as verified.

- [ ] **Step 5: Review**

Perform/request correctness, security, performance, and architecture/duplication review. Resolve all Critical/Important findings.

### Task 2: Protected bot-scoped REST binding

**Files:**
- Modify: `src/Admin/Rest/AdminRestBootstrap.php`
- Create: `tests/Unit/Admin/AppearanceRoutesTest.php`

**Interfaces:**
- Consumes: `AppearanceRestResource::read()` / `write()`, existing `AdminCapability::can_manage`, `WpdbConnection`, `TableNames`, `WpdbBotAppearanceRepository`.
- Produces: protected `GET`/`PUT` `/wp-rag-ai-chatbot/v1/admin/bots/(?P<id>[^/]+)/appearance` callbacks.

- [ ] **Step 1: Write the failing route/callback contract test**

Require route registration to expose both `GET` and `PUT`, each with `AdminCapability::can_manage`. Require invalid PUT payloads to fail closed through the shared appearance validation path rather than accepting unknown runtime fields.

- [ ] **Step 2: Push and verify genuine RED**

Expected failure: appearance route/callback is absent. Route-test lint/static analysis must pass first.

- [ ] **Step 3: Implement minimal WordPress binding**

Add the bot-scoped route to `register_routes()`, plus thin `get_bot_appearance()` / `put_bot_appearance()` callbacks. Build `AppearanceRestResource` from the existing WordPress connection/table authorities and `WpdbBotAppearanceRepository`. Pass PUT JSON directly to the resource/domain closed schema; do not create another validation schema.

- [ ] **Step 4: Verify exact-head GREEN**

Run/observe focused PHPUnit plus all permanent CI gates. No GREEN claim until exact implementation SHA passes all four permanent jobs.

- [ ] **Step 5: Review and persist durable evidence**

Review correctness/security/performance/architecture/accessibility applicability, update `docs/progress/STATUS.md`, create the Task 3 progress evidence, update PR #19 state, then recover and begin Task 4 automatically.
