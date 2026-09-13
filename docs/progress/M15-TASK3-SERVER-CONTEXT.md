# M15 Task 3 — WordPress server context projection

Status: IN PROGRESS — Task 3A COMPLETE; Task 3B next.

## Task 3A scope

Project only the minimum public-safe WordPress facts needed by the existing TypeScript display-rule authority:

- normalized request path, without query/fragment data;
- normalized post type or `null`;
- authenticated boolean;
- bounded normalized role-match tokens.

Do not serialize the WordPress user object, user ID, email address, capability map, credentials, provider/model authority, retrieval authority, or other private runtime state.

## TDD evidence

- `9e6cf8eb98f3d286586ec75983c83a7bbf8d5d7d` — **NOT RED**. Initial test-only checkpoint failed PHPCS before PHPUnit reached the intended behavior.
- `4e2800a2e0e563dd41be8cc5fb66d421fa5a314b` — **RED**, CI `34772600180`. PHPCS and PHPStan passed; PHPUnit ran and the two new Task 3A tests failed only because `WordPressDisplayContextResolver` did not exist.
- `f05169914eb9590ff9aa48c147571abb346242b8` — **NOT GREEN**, CI `34775803474`. First implementation failed PHPCS because `REQUEST_URI` was not unslashed and native `parse_url()` was used.
- `b41f22ab80e8b73178749a017f857071e43810dc` — **NOT GREEN**. Production switched to `wp_unslash()` / `wp_parse_url()`, but the isolated test double still used native `parse_url()` and failed PHPCS.
- `0da0a6446722fdfa88930c82e824663a31af074f` — **NOT GREEN**, CI `34776025291`. PHPCS passed; PHPStan correctly rejected redundant guards around WordPress's guaranteed `WP_User::$roles` contract.
- `4a298531f26d3c0dac1cdd662068ea44c8c20946` — **GREEN**, CI `34776112499`. `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed, including PHPUnit and the full WordPress smoke matrix.

## Implementation

`src/Frontend/WordPressDisplayContextResolver.php` now:

- uses WordPress APIs as the authority for auth, current user roles, post type, URL unslashing and URL parsing;
- strips query/fragment state by projecting only `PHP_URL_PATH`;
- normalizes post type and role tokens to bounded lowercase `[a-z0-9_-]` values;
- deduplicates and caps projected role tokens at 16;
- returns only `path`, `isAuthenticated`, `postType`, and `roleMatches`.

## Review

Independent reviewer transport is not exposed in this runtime, so the repository-approved fallback scoped review was performed honestly.

- Correctness: no Critical/Important findings. Authenticated and anonymous fixtures cover normalized output and absence of user lookup for anonymous requests.
- Security/privacy: no Critical/Important findings. Query/fragment data, user ID, email and capabilities are excluded; role values are bounded and normalized.
- Performance: no Critical/Important findings. Work is constant/bounded apart from at most 16 role tokens.
- Architecture/duplication: no Critical/Important findings. This class only projects server facts and does not duplicate the TypeScript rule evaluator or RAG/runtime authority.
- Accessibility: no direct UI behavior is introduced by Task 3A; accessible presentation remains later M15 tasks.

## Verification

Final implementation SHA: `4a298531f26d3c0dac1cdd662068ea44c8c20946`

Final CI: `34776112499` — GREEN across all permanent jobs.

## Next unfinished unit

Task 3B — extend the same server-context authority with optional WooCommerce finite-area facts plus public-safe site locale/timezone/direction fallback. Start with a focused PHPUnit RED; do not hard-depend on WooCommerce and do not duplicate display-rule evaluation in PHP.
