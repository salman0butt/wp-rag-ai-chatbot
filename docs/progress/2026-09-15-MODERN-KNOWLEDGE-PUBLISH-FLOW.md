# Modern Knowledge Publish Flow

## Task 9 scope

Added the real WordPress end-to-end smoke for the approved modern setup flow. It covers manual source creation, persisted `sync.source` observation, queued child `index.document` processing, document/chunk/local-vector persistence, Gemini bot creation and source binding, production Playground and public chat, all public shortcodes, the dynamic Gutenberg block, disabled/unbound fail-closed behavior, and public-bootstrap secret/path checks.

The smoke uses the existing WordPress job hook and production runtime composition. Its provider HTTP calls are locally intercepted with a test-only fake response so the check is deterministic and does not require a live Gemini account.

## Verification status

Status: package verified and uploaded to the requested WordPress site; the local Docker/wp-env application smoke is blocked by the environment.

The wrapper reports `ENVIRONMENT_STARTUP_FAILURE` with exit code 2 when Docker/wp-env is unavailable, and `APPLICATION_ASSERTION_FAILURE` with exit code 1 when the application assertions fail.

## Commands

```text
bash scripts/test-wp-modern-setup.sh
composer test
find src scripts -type f -name '*.php' -print0 | xargs -0 -n1 php -l
vendor/bin/phpstan analyse --memory-limit=2G
npm run verify:js
npm run plugin-zip
bash scripts/assert-package.sh
unzip -t wp-rag-ai-chatbot.zip
```

Final verification from commit `a3c3eb5e001e12641234713e5e022f3e8df6d565`:

- `composer test` — 927 tests, 3,829 assertions passed.
- `composer lint:php` — passed.
- `vendor/bin/phpstan analyse --memory-limit=2G` — passed with no errors.
- PHP syntax scan — passed; the repository's pre-existing non-fatal `use` warnings remain in older smoke scripts.
- `npm run verify:js` — 73 suites, 212 tests passed; lint, typecheck, build, and vector-store gating passed.
- `bash scripts/test-wp-modern-setup.sh` — classified `ENVIRONMENT_STARTUP_FAILURE` (Docker unavailable or not initialized); application assertions were not run.
- `npm run plugin-zip` — passed.
- `bash scripts/assert-package.sh` — passed.
- `unzip -t wp-rag-ai-chatbot.zip` — passed.
- Package: `wp-rag-ai-chatbot.zip`; SHA-256: `d9b2764bf317d50b54540d93a97de42f6c29dc79c7dd7715f332cbf5d7dbd2e7`.

The runtime-only package is ready for WordPress upload. The package was uploaded and replaced on `digitalmx.no/update`; the modern Overview and Provider screens load successfully, the saved Gemini credential remains connected, and a fresh admin load produced no browser console errors.

## Frontend publishing

After package verification, install the runtime-only `wp-rag-ai-chatbot.zip`, configure Gemini in the plugin admin, create or bind a published bot, then use one of these existing surfaces:

```text
[wp_rag_ai_chatbot bot="BOT_ID"]
[wp_rag_ai_chatbot_embed bot="BOT_ID"]
[wp_rag_ai_chatbot_fullscreen bot="BOT_ID"]
```

The Gutenberg block is `wp-rag-ai-chatbot/chatbot`; select the published bot in its `bot` attribute.
