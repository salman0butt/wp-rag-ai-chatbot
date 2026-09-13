# M14 Task 3 — Protected Admin Appearance Contracts

Status: COMPLETE on `feat/m14-frontend-chatbot-customizer` / PR #19.

## Scope

Task 3 adds administrator-only bot-scoped appearance read/save contracts over the existing `BotAppearanceRepository` and `AppearanceConfig` authorities.

The implementation intentionally does **not** expose or accept provider credentials, provider/model authority, embedding/vector-store configuration, retrieval limits, raw CSS/HTML, or unrestricted bot records.

## Architecture

- `AppearanceRestResource` parses the existing `BotId`, reads/writes through `BotAppearanceRepository`, normalizes writes through `AppearanceConfig::from_array()`, and projects only normalized `appearance` data or stable non-sensitive errors.
- `AdminRestBootstrap` binds protected `GET`/`PUT` `/admin/bots/(?P<id>[^/]+)/appearance` callbacks using the existing `AdminCapability::can_manage` permission authority.
- WordPress binding reuses `WpdbBotAppearanceRepository`; there is no parallel appearance persistence or validation schema.

## TDD evidence

### Resource subunit

**Genuine RED**

- SHA: `f2cd956482caa82cd5186ccbe6ad92c271475ec8`
- CI: `34687383105`
- PHP static analysis passed and PHPUnit reached the intended missing-resource behavior: four `AppearanceRestResource` tests failed because the class did not exist.
- `js-quality`, `package`, and `wordpress-smoke` were green.

**Genuine GREEN**

- SHA: `f67d97a1c433bf7c52e9c6d9aa99f902d5853e90`
- CI: `34687459910`
- Exact-head CI conclusion: success.

### Protected route/callback subunit

**NOT RED**

- SHA: `9215b4e7601a0057a42ef039f78181ef57e4ddec`
- CI: `34687646158`
- PHPCS stopped `php-quality` on a test warning before PHPUnit could reach the intended absent-route behavior. This checkpoint is preserved as **NOT RED**.

**Genuine RED after evidence repair**

- SHA: `06895ac2505eaf846069f3a4f921de65f2e09b0e`
- CI: `34689869102`
- PHP coding standards and PHP static analysis both passed.
- PHPUnit then reached the intended behavior: the protected appearance route was absent, so the expected `register_rest_route()` call occurred zero times.
- `js-quality`, `package`, and `wordpress-smoke` were green.

**NOT GREEN — static analysis**

- SHA: `eb95d55b4d81a2b9dbf9f3f7f8fc6135d049f763`
- CI: `34689935637`
- The route implementation existed, but PHP static analysis failed before PHPUnit; this is explicitly **NOT GREEN**.

**NOT GREEN — regression test counts**

- SHA: `6fbbbb03d1f5e315d3496ac6bf1cf23d9be633c7`
- CI: `34690115346`
- PHP coding standards and static analysis passed, but PHPUnit still failed because existing route-registration count contracts had not yet been updated for the new protected route. This is explicitly **NOT GREEN**.

**Genuine GREEN**

- SHA: `078e74d8d373cd0ec0c1881dc6cb0b1122d87a1e`
- CI: `34690345814`
- `php-quality`: GREEN, including coding standards, static analysis, PHPUnit and Composer audit.
- `js-quality`: GREEN.
- `package`: GREEN.
- `wordpress-smoke`: GREEN.

## Security and correctness review

Fallback scoped review completed because an independent reviewer transport was not available in this execution environment.

- Correctness: valid reads call the existing repository once and return normalized appearance only; valid writes normalize through the shared domain authority and persist once.
- Fail-closed behavior: invalid bot identifiers, unknown/unsafe appearance keys, and persistence failures map to stable non-sensitive errors.
- Authorization: both HTTP methods use `AdminCapability::can_manage`.
- Security: no provider credentials, arbitrary model/provider selection, embedding/vector-store settings, retrieval limits, unrestricted bot records, raw HTML, or arbitrary CSS are accepted or returned.
- Performance: the resource adds only bounded validation/repository orchestration; it does not add network or model calls.
- Architecture/duplication: persistence and validation reuse the existing Task 1/2 authorities; no second appearance schema or repository is introduced.
- Accessibility: not applicable to this backend-only subunit; UI accessibility remains a later M14 responsibility.

Unresolved Critical findings: **0**.

Unresolved Important findings: **0**.

## Continuation

Task 3 is complete. Continue M14 Task 4: add the public chat request/runtime composition with explicit abuse controls while reusing the existing M11 chat/retrieval authorities exactly once. Public input must remain bounded and must not expose request-level provider/model/embedding/vector-store/retrieval authority.
