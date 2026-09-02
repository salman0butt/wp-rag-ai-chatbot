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

# Uninstall retains data unless deletion is explicitly enabled.
$WP option update wp_rag_ai_delete_data_on_uninstall 0 --format=json
$WP eval 'define("WP_UNINSTALL_PLUGIN", "wp-rag-ai-chatbot/wp-rag-ai-chatbot.php"); include WP_PLUGIN_DIR . "/wp-rag-ai-chatbot/uninstall.php";'
$WP eval '$p=$GLOBALS["wpdb"]->prefix; foreach (["rag_ai_sources","rag_ai_documents"] as $s) { $t=$p.$s; if ($GLOBALS["wpdb"]->get_var($GLOBALS["wpdb"]->prepare("SHOW TABLES LIKE %s",$t)) !== $t) { fwrite(STDERR, "Default uninstall unexpectedly deleted {$t}\n"); exit(1); } }'

# Explicit opt-in removes plugin-owned schema and policy/version options.
$WP option update wp_rag_ai_delete_data_on_uninstall 1 --format=json
$WP eval 'define("WP_UNINSTALL_PLUGIN", "wp-rag-ai-chatbot/wp-rag-ai-chatbot.php"); include WP_PLUGIN_DIR . "/wp-rag-ai-chatbot/uninstall.php";'
$WP eval '$p=$GLOBALS["wpdb"]->prefix; foreach (["rag_ai_documents","rag_ai_sources"] as $s) { $t=$p.$s; if ($GLOBALS["wpdb"]->get_var($GLOBALS["wpdb"]->prepare("SHOW TABLES LIKE %s",$t)) === $t) { fwrite(STDERR, "Opt-in uninstall did not delete {$t}\n"); exit(1); } } if (false !== get_option("wp_rag_ai_db_version", false) || false !== get_option("wp_rag_ai_delete_data_on_uninstall", false)) { fwrite(STDERR, "Opt-in uninstall did not delete plugin database options\n"); exit(1); }'

# A clean reinstall must recreate the schema and repositories successfully.
$WP plugin deactivate wp-rag-ai-chatbot --quiet || true
$WP plugin activate wp-rag-ai-chatbot --quiet
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-database.php
