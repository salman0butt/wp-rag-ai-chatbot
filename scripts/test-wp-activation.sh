#!/usr/bin/env bash
set -euo pipefail

npm run wp-env -- run cli wp plugin deactivate wp-rag-ai-chatbot --quiet || true
npm run wp-env -- run cli wp plugin activate wp-rag-ai-chatbot --quiet
npm run wp-env -- run cli wp plugin is-active wp-rag-ai-chatbot
npm run wp-env -- run cli wp eval 'if (!class_exists("WpRagAiChatbot\\Core\\Bootstrap")) { fwrite(STDERR, "Bootstrap class not loaded\n"); exit(1); }'
npm run wp-env -- run cli wp plugin deactivate wp-rag-ai-chatbot --quiet
npm run wp-env -- run cli wp plugin activate wp-rag-ai-chatbot --quiet
npm run wp-env -- run cli wp plugin is-active wp-rag-ai-chatbot
