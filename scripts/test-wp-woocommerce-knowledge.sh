#!/usr/bin/env bash
set -euo pipefail

WP="npm run --silent wp-env -- run cli wp"
WOO_VERSION="11.0.1"
WOO_ARCHIVE="https://downloads.wordpress.org/plugin/woocommerce.${WOO_VERSION}.zip"

$WP plugin activate wp-rag-ai-chatbot --quiet
$WP plugin install "${WOO_ARCHIVE}" --force --activate --quiet

INSTALLED_WOO_VERSION="$($WP plugin get woocommerce --field=version)"
if [[ "${INSTALLED_WOO_VERSION}" != "${WOO_VERSION}" ]]; then
	printf 'Expected WooCommerce %s, installed %s.\n' "${WOO_VERSION}" "${INSTALLED_WOO_VERSION}" >&2
	exit 1
fi

$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-woocommerce-knowledge.php

$WP plugin deactivate woocommerce --quiet
$WP plugin activate wp-rag-ai-chatbot --quiet
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-knowledge.php

printf 'WordPress/WooCommerce knowledge smoke passed with WooCommerce %s and disabled fallback.\n' "${WOO_VERSION}"
