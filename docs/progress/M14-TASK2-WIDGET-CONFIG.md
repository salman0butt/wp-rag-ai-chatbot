# M14 Task 2 — Bot Appearance Persistence and Public Widget Configuration

Status: COMPLETE

## Scope

Task 2 establishes bot-scoped appearance persistence and an explicit public-safe widget configuration projection. It does not expose a public endpoint and does not implement chat execution.

The implementation reuses the existing bot repository and the bounded `AppearanceConfig` authority introduced by M14 Task 1.

## Security and architecture boundary

The public widget projection is allow-listed. `WidgetConfig::to_array()` exposes only:

- `bot_id`;
- `name`;
- normalized `appearance`.

It does not serialize provider IDs, model IDs, credentials, embedding configuration, vector-store configuration, retrieval limits, raw bot records, raw HTML, or arbitrary CSS.

`WidgetConfigResolver` fails closed for missing or disabled bots and does not load appearance state for those cases.

The persistence layer stores only normalized `AppearanceConfig` JSON. Missing or legacy blank persisted appearance resolves to `AppearanceConfig::defaults()`; malformed persisted data fails closed.

## TDD evidence

### Task 2A — persistence / legacy blank compatibility

Earlier Task 2A chronology includes migration/repository RED and implementation checkpoints beginning with:

- `2fe6f257b660fef14b733c4724ee6d9fcb1b2ee0` — migration specification.
- `4186890d9ba3d1293692af2fdae0ff3e8f3bb568` — appearance repository specification.

The final compatibility cycle was:

- Genuine RED: `832b6c71adaf91ba9880a113c6001560e92eae10` / CI `34686373915`.
  - PHPStan passed.
  - PHPUnit reached `test_find_projects_defaults_for_legacy_blank_appearance` and failed because whitespace-only legacy appearance was rejected.
- NOT GREEN: `f8a26c7deb2fb58f06859c019f615471ea19a15d` / CI `34686658910`.
  - The behavior repair was present, but PHPCS stopped verification on the missing final newline introduced by the remote write.
- Genuine GREEN: `52b493e50631b9f5e5428f815357d1b9d7b9966e` / CI `34686726046`.
  - `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed.

### Task 2B — explicit public widget projection

- NOT RED: `a8eaf757fb1ab4f3a7f9fd846fa7a22f7a9dc218` / CI `34686898893`.
  - PHPCS stopped before PHPUnit on test formatting/docblock issues.
- Genuine RED: `c70c02a7c6222a36c4b144c782b9c6d4a8562cb2` / CI `34686937908`.
  - PHPStan passed.
  - PHPUnit ran 762 tests / 3,199 assertions and failed only on the intended missing `WidgetConfig` / `WidgetConfigResolver` behavior.
- Partial implementation checkpoint: `788881d299c5f3b83488f4f77158cb4e20fda389`.
  - Added the allow-listed `WidgetConfig` DTO; not claimed GREEN because the resolver was still absent.
- NOT GREEN: `a2a4f94dd6fe7674e8876101b263da6bc5de70a7` / CI `34687009454`.
  - Production behavior was implemented, but PHPCS stopped verification on one parameter-doc alignment error.
- Genuine GREEN: `d29d2b947a4c6c6ba3dfff67cc4ec4d203cb1bfa` / CI `34687055933`.
  - `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed.

## Review

A scoped fallback correctness/security/performance/architecture review found:

- Critical findings: 0 unresolved.
- Important findings: 0 unresolved.
- Correctness: enabled bots resolve one public-safe projection; missing/disabled bots return `null` before appearance access.
- Security: serialization is an explicit allow-list and does not expose provider/model/runtime authority.
- Performance: at most one bot lookup and one appearance lookup for an enabled bot.
- Architecture/duplication: projection composes existing repositories and `AppearanceConfig`; no retrieval/chat/provider pipeline is duplicated.
- Accessibility: not applicable to this backend persistence/projection unit; UI accessibility remains a later M14 concern.

Independent reviewer/subagent transport was not available in this execution environment, so this is not represented as an independent final milestone review.

## Result

Task 2 is complete and exact implementation head `d29d2b947a4c6c6ba3dfff67cc4ec4d203cb1bfa` is verified GREEN.

The next M14 unit must be recovered from the durable M14 design/roadmap before implementation rather than inferred from chat history.
