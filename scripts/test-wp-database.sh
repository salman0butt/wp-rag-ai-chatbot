#!/usr/bin/env bash
set -euo pipefail

WP="npm run --silent wp-env -- run cli wp"

$WP plugin deactivate wp-rag-ai-chatbot --quiet || true
$WP eval '$p=$GLOBALS["wpdb"]->prefix; $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_documents"); $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_sources"); delete_option("wp_rag_ai_db_version"); delete_option("wp_rag_ai_delete_data_on_uninstall");'
$WP plugin activate wp-rag-ai-chatbot --quiet
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-database.php

# Simulate a site that completed only V001.
$WP eval '$p=$GLOBALS["wpdb"]->prefix; $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_documents"); update_option("wp_rag_ai_db_version", 1, false);'
# A new WP-CLI process loads active plugins and must perform the normal plugins_loaded upgrade.
$WP eval 'if ((int) get_option("wp_rag_ai_db_version", 0) !== 2) { fwrite(STDERR, "Automatic V1 to V2 upgrade failed\n"); exit(1); }'
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-database.php
