# Task 5 report — fix round 1/5

## Outcome

Closed every finding in `task-5-review.md` while preserving the existing
`GET /wp-rag-ai-chatbot/v1/admin/onboarding/readiness` route, namespace,
capability callback, `ready`/`next_step` keys, and safe onboarding issue codes.

- I1: readiness preserves only `missing_credential`, `provider_unavailable`,
  and `unsupported_capability`; issue recovery tests cover all three states.
- I2: readiness reads the existing cached model catalog only and reuses the
  model-readiness compatibility authority. Empty, stale, mismatched, and valid
  model cases are covered. No provider discovery or network work occurs.
- I3/M2: fixed collection/profile comparison is centralized in
  `GuidedRetrievalReadiness` and used by both `BotRetrievalResource` and setup
  readiness. Completion requires the `wp-rag-default` collection row, the
  exact fixed fingerprint, dimensions 3072, and non-empty lexical/vector
  projections.
- I4: enabled counts use a repository aggregate, binding presence uses one
  scalar existence query, and candidate bindings use one batch query. A
  documented 100-bot scan ceiling fails closed for larger inventories.
- M1: negative coverage includes empty/stale models, malformed bindings,
  missing/wrong/partial collection state, and the bounded-inventory ceiling.

The response contains only bounded booleans, counts, and safe issue values;
credentials, paths, raw errors, payloads, and provider responses are not
exposed.

## TDD evidence

RED was recorded before the production changes:

```text
vendor/bin/phpunit tests/Unit/Admin/SetupReadinessRestResourceTest.php \
  tests/Unit/Providers/Cache/CachedModelCatalogProviderTest.php \
  tests/Unit/Database/Repository/WpdbBotRepositoryTest.php \
  tests/Unit/Database/Repository/WpdbBotRetrievalBindingRepositoryTest.php \
  --no-coverage

35 tests, 77 assertions, 19 errors.
The intended failures were the absent cache-only catalog method, aggregate
bot-count method, aggregate/batch binding methods, and the new readiness
repository contract; the original readiness implementation also failed the
new bounded/profile/issue coverage.
```

GREEN focused verification after implementation:

```text
55 tests, 221 assertions, OK
```

## Final verification

```text
vendor/bin/phpunit --testsuite unit --no-coverage
892 tests, 3645 assertions, OK

vendor/bin/phpunit --no-coverage
926 tests, 3821 assertions, OK

vendor/bin/phpcs
No errors

php -d memory_limit=2G vendor/bin/phpstan analyse --configuration=phpstan.neon.dist
[OK] No errors

git diff --check
No output
```

WordPress/live smoke was not run for this fix round and remains unverified;
no smoke pass is claimed.
