# Task 5 report — bounded setup readiness

## Scope

Added the server-owned setup readiness resource and wired the existing
`GET /wp-rag-ai-chatbot/v1/admin/onboarding/readiness` route to it. The route
and capability callback remain unchanged, and `ready`/`next_step` remain in
the response.

The response now includes only bounded setup state:

- configured generation provider;
- configured Gemini embedding capability;
- local model capability availability;
- persisted source count;
- completed local lexical/vector index presence;
- enabled bot count;
- bound bot presence;
- publishable bot presence.

Provider readiness uses local registry/configuration state only. Index
readiness uses the fixed server-owned Gemini profile and local WordPress
projection tables. No credentials, paths, payloads, provider calls, or
optimistic browser state cross the REST boundary.

## TDD evidence

RED was observed before implementation:

```text
vendor/bin/phpunit --filter SetupReadinessRestResourceTest --testdox
3 tests, 1 assertion, 1 intended failure and 2 class-not-found errors
because SetupReadinessRestResource did not exist.
```

GREEN after implementation:

```text
SetupReadinessRestResourceTest + existing model readiness tests:
7 tests, 45 assertions, OK
```

## Verification

```text
vendor/bin/phpunit --testsuite unit --no-coverage
875 tests, 3592 assertions, OK

vendor/bin/phpunit --no-coverage
909 tests, 3768 assertions, OK

vendor/bin/phpcs
No errors

php -d memory_limit=2G vendor/bin/phpstan analyse --configuration=phpstan.neon.dist
[OK] No errors

git diff --check
No output
```

WordPress/live smoke was not run for this task. It remains unverified; no
smoke pass is claimed.
