# M15 Task 3 — WordPress server context projection

Status: COMPLETE.

## Scope

Project only the bounded public-safe WordPress presentation facts needed by the existing TypeScript display-rule authority, then expose those facts through the existing public widget bootstrap.

The server-context authority may project:

- normalized request path without query or fragment state;
- normalized post type or `null`;
- authenticated boolean;
- bounded normalized role-match tokens;
- a finite optional WooCommerce area token;
- normalized site locale and direction;
- site-local weekday and minute-of-day facts used by schedule presentation rules.

It must not serialize the WordPress user object, user ID, email address, capability map, credentials, provider/model authority, embedding/vector-store authority, retrieval authority, or other private/runtime state.

## Task 3A — path/post/auth/role facts

### TDD evidence

- `9e6cf8eb98f3d286586ec75983c83a7bbf8d5d7d` — **NOT RED**. Initial test-only checkpoint failed PHPCS before PHPUnit reached the intended behavior.
- `4e2800a2e0e563dd41be8cc5fb66d421fa5a314b` — **RED**, CI `34772600180`. PHPCS and PHPStan passed; PHPUnit ran and the two new Task 3A tests failed only because `WordPressDisplayContextResolver` did not exist.
- `f05169914eb9590ff9aa48c147571abb346242b8` — **NOT GREEN**, CI `34775803474`. First implementation failed PHPCS because `REQUEST_URI` was not unslashed and native `parse_url()` was used.
- `b41f22ab80e8b73178749a017f857071e43810dc` — **NOT GREEN**. Production switched to `wp_unslash()` / `wp_parse_url()`, but the isolated test double still used native `parse_url()` and failed PHPCS.
- `0da0a6446722fdfa88930c82e824663a31af074f` — **NOT GREEN**, CI `34776025291`. PHPCS passed; PHPStan correctly rejected redundant guards around WordPress's guaranteed `WP_User::$roles` contract.
- `4a298531f26d3c0dac1cdd662068ea44c8c20946` — **GREEN**, CI `34776112499`. `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed.

### Implementation

`src/Frontend/WordPressDisplayContextResolver.php`:

- uses WordPress APIs for auth, current-user roles, post type, URL unslashing and URL parsing;
- strips query/fragment state by projecting only `PHP_URL_PATH`;
- normalizes post type and role tokens to bounded lowercase `[a-z0-9_-]` values;
- deduplicates and caps projected role tokens at 16;
- exposes no user identifier, email address or capability map.

## Task 3B — Woo/site locale/timezone/direction facts

### TDD evidence

- `fb077751b8fcfb8f0b39fb758830c15d59a59cc9` — **RED**, CI `34776351341`. PHPCS and PHPStan passed; PHPUnit reached the new site/Woo context assertions and failed for the intended missing behavior. JS, package and WordPress smoke remained green.
- `c66a2ebee3777684e36542e5ae35c853a696a340` — **NOT GREEN**, CI `34776442662`. The implementation checkpoint did not pass `php-quality`; it therefore cannot be used as GREEN evidence even though the independent JS/package/WordPress-smoke jobs passed.
- `be9041f46df9c5dd0f13dedabdbf8cfa2e7a8f0f` — **GREEN**, CI `34776507268`. Documentation/normalizer corrections brought the exact head green across all permanent jobs.

### Woo initialization regression

- `9d2960d666ea743aa6b583d8d77a01ef4ad299b6` — **RED**, CI `34776701310`. PHPCS and PHPStan passed; PHPUnit failed after reaching the new Woo-initialization behavior while JS, package and WordPress smoke passed.
- `f5d554ec3d1776ea96b9560973cfc39c3376fb76` — **GREEN**, CI `34776777735`. Woo presentation facts are now emitted only after WooCommerce initialization; all permanent jobs passed.

### Implementation

The same `WordPressDisplayContextResolver` now also projects:

- finite WooCommerce area facts only after `woocommerce_init`, without creating a hard WooCommerce dependency;
- normalized site locale with a safe fallback;
- explicit `ltr` / `rtl` site direction;
- WordPress site-local weekday and minute-of-day facts for deterministic schedule evaluation.

The projection remains bounded and presentation-only.

## Public widget bootstrap integration

### TDD evidence

- `b77efa451146ea470f60bbcf1f1e255994484a35` — **RED**, CI `34776982106`. PHPCS and PHPStan passed; PHPUnit was the only failing PHP gate because the existing public widget bootstrap did not yet serialize the trusted presentation facts. JS, package and the complete WordPress smoke matrix passed.
- `ac00f9852e6a86539e3808feb230c8ebc0abce2a` — **GREEN**, CI `34779010622`. The existing `PublicWidgetBootstrap` now adds `facts` from `WordPressDisplayContextResolver` to the browser bootstrap object; all four permanent jobs passed, including PHPUnit and the full WordPress smoke matrix.

### Architectural boundary

The integration extends the existing public widget bootstrap exactly once. It does not create a parallel chat/RAG path, does not duplicate display-rule evaluation in PHP, and does not accept request-level provider/model/embedding/vector-store/retrieval overrides.

## Review

Independent reviewer transport is not exposed in this runtime, so the repository-approved fallback scoped review was performed and the limitation is recorded honestly.

- Correctness: no unresolved Critical or Important findings. Server facts are normalized, bounded and emitted through the real public widget bootstrap; Woo facts remain conditional on WooCommerce initialization.
- Security/privacy: no unresolved Critical or Important findings. User IDs, emails, capability maps, credentials, provider/model authority, embedding/vector-store authority, retrieval configuration and query/fragment URL state are not projected.
- Performance: no unresolved Critical or Important findings. Resolution is bounded and uses standard WordPress helpers; there are no new remote calls or unbounded loops.
- Accessibility: no unresolved Critical or Important findings. This task does not alter interaction behavior; explicit site direction is projected for the later RTL/accessibility runtime work.
- Architecture/duplication: no unresolved Critical or Important findings. The existing `WordPressDisplayContextResolver` and `PublicWidgetBootstrap` authorities are reused; display-rule evaluation remains in the shared TypeScript authority.

## Verification

Final implementation SHA: `ac00f9852e6a86539e3808feb230c8ebc0abce2a`

Final implementation CI: `34779010622` — GREEN across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.

## Next unfinished unit

Task 4 — wire deterministic display-rule visibility into the existing widget runtime exactly once. Start with test-only RED evidence for ineligible contexts staying unmounted/inert, eligible contexts preserving the M14 widget behavior, and malformed optional context/config failing conservatively without proactive behavior. Do not create a parallel widget/chat/RAG runtime.