#!/usr/bin/env bash
set -euo pipefail

WP="npm run --silent wp-env -- run cli wp"

$WP plugin activate wp-rag-ai-chatbot --quiet
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-providers.php
