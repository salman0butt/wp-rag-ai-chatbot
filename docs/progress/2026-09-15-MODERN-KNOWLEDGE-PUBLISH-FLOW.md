# Modern Knowledge Publish Flow

## Task 9 scope

Added the real WordPress end-to-end smoke for the approved modern setup flow. It covers manual source creation, persisted `sync.source` observation, queued child `index.document` processing, document/chunk/local-vector persistence, Gemini bot creation and source binding, production Playground and public chat, all public shortcodes, the dynamic Gutenberg block, disabled/unbound fail-closed behavior, and public-bootstrap secret/path checks.

The smoke uses the existing WordPress job hook and production runtime composition. Its provider HTTP calls are locally intercepted with a test-only fake response so the check is deterministic and does not require a live Gemini account.

## Verification status

Status: implementation added; live WordPress/package evidence is pending until the worktree environment is available.

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

No command above is recorded as passing until it is run from this worktree and its output is available. The final package path, SHA-256, test counts, commit, and frontend publishing instructions should be filled in only after fresh verification.

## Frontend publishing

After package verification, install the runtime-only `wp-rag-ai-chatbot.zip`, configure Gemini in the plugin admin, create or bind a published bot, then use one of these existing surfaces:

```text
[wp_rag_ai_chatbot bot="BOT_ID"]
[wp_rag_ai_chatbot_embed bot="BOT_ID"]
[wp_rag_ai_chatbot_fullscreen bot="BOT_ID"]
```

The Gutenberg block is `wp-rag-ai-chatbot/chatbot`; select the published bot in its `bot` attribute. Keep the live WordPress update as a separate handoff after package verification.
