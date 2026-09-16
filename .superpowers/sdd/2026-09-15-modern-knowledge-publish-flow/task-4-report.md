# Task 4 report — bot knowledge binding

## Result

Implemented capability-protected `GET|PUT|DELETE /wp-rag-ai-chatbot/v1/admin/bots/{id}/retrieval`.

- `GET` returns only `configured`, `source_id`, `source_title`, `collection_id`, and `collection_ready` under the `retrieval` DTO.
- `PUT` accepts only an integer `source_id`; the server validates `BotId`, persisted source/profile, local collection tables, fixed fingerprint, and dimensions before saving.
- `DELETE` clears only `retrieval_source_id` and `retrieval_collection_id` through the binding repository.
- Provider, model, dimension, vector-store, collection, path, credentials, and raw exception details are not request-controlled or returned.

## Exact files

Created:

- `src/Admin/Rest/BotRetrievalResource.php`
- `tests/Unit/Admin/BotRetrievalResourceTest.php`
- `tests/Unit/Admin/BotRetrievalRoutesTest.php`
- `.superpowers/sdd/2026-09-15-modern-knowledge-publish-flow/task-4-report.md`

Modified:

- `src/Admin/Rest/AdminRestBootstrap.php`
- `src/Frontend/BotRetrievalBindingRepository.php`
- `src/Database/Repository/WpdbBotRetrievalBindingRepository.php`
- `tests/Unit/Database/Repository/WpdbBotRetrievalBindingRepositoryTest.php`
- `tests/Unit/Admin/AdminSurfaceTest.php`
- `tests/Unit/Admin/AppearanceRoutesTest.php`
- `tests/Unit/Admin/DisplayRulesRoutesTest.php`
- `tests/Unit/Admin/KnowledgeDetailRoutesTest.php`
- `tests/Unit/Admin/KnowledgeJobRoutesTest.php`
- `tests/Unit/Admin/KnowledgeSourceRoutesTest.php`
- `tests/Unit/Admin/ModelReadinessRoutesTest.php`
- `tests/Unit/Admin/PlaygroundRoutesTest.php`

## TDD evidence

- RED: `vendor/bin/phpunit --filter BotRetrievalResourceTest --testdox` failed with 10/10 tests because `BotRetrievalResource` did not exist. The initial run also exposed and corrected a readonly fixture mutation and the not-yet-existing repository clear seam.
- GREEN: focused resource/route/repository run passed 16 tests and 47 assertions.

## Verification

- `vendor/bin/phpunit --testsuite unit --no-coverage`: 872 tests, 3565 assertions, green.
- `vendor/bin/phpunit --no-coverage`: 906 tests, 3741 assertions, green.
- `vendor/bin/phpcs`: green.
- `php -d memory_limit=2G vendor/bin/phpstan analyse --configuration=phpstan.neon.dist`: no errors.
- `git diff --check`: clean.

## Limitations

The Task 9 `scripts/test-wp-modern-setup.sh` smoke script is not present in this worktree, so no live WordPress smoke was claimed or run for Task 4. Existing ledger evidence records the earlier knowledge smoke as blocked before application assertions because `wp-env` was not initialized.

Implementation commit: `2c6e7e2` (`Add bot retrieval binding admin resource`).
