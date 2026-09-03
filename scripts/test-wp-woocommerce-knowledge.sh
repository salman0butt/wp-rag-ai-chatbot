#!/usr/bin/env bash
set -euo pipefail

WP="npm run --silent wp-env -- run cli wp"
WOO_VERSION="11.0.1"

$WP plugin activate wp-rag-ai-chatbot --quiet
$WP plugin install woocommerce --version="${WOO_VERSION}" --force --activate --quiet
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-woocommerce-knowledge.php

$WP plugin deactivate woocommerce --quiet
$WP plugin activate wp-rag-ai-chatbot --quiet
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-knowledge.php

printf 'WordPress/WooCommerce knowledge smoke passed with WooCommerce %s and disabled fallback.\n' "${WOO_VERSION}"
