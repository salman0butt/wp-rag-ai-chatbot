#!/usr/bin/env bash
set -euo pipefail

WP="npm run --silent wp-env -- run cli wp"

$WP plugin deactivate wp-rag-ai-chatbot --quiet || true
$WP eval '$p=$GLOBALS["wpdb"]->prefix; $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_jobs"); $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_vectors"); $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_vector_collections"); $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_documents"); $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_sources"); delete_option("wp_rag_ai_db_version"); delete_option("wp_rag_ai_delete_data_on_uninstall");'
$WP plugin activate wp-rag-ai-chatbot --quiet
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-database.php
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-job-queue.php

# Simulate a site that completed only V001.
$WP eval '$p=$GLOBALS["wpdb"]->prefix; $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_jobs"); $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_vectors"); $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_vector_collections"); $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_documents"); update_option("wp_rag_ai_db_version", 1, false);'
# A new WP-CLI process loads active plugins and must perform the normal plugins_loaded upgrade through the current schema.
$WP eval 'if ((int) get_option("wp_rag_ai_db_version", 0) !== 5) { fwrite(STDERR, "Automatic V1 to V5 upgrade failed\n"); exit(1); }'
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-database.php
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-job-queue.php

# Simulate WordPress uninstall with the plugin inactive so a later WP-CLI process cannot auto-migrate deleted data back into existence.
$WP plugin deactivate wp-rag-ai-chatbot --quiet

# Uninstall retains data unless deletion is explicitly enabled.
$WP option update wp_rag_ai_delete_data_on_uninstall 0 --format=json
$WP eval 'define("WP_UNINSTALL_PLUGIN", "wp-rag-ai-chatbot/wp-rag-ai-chatbot.php"); include WP_PLUGIN_DIR . "/wp-rag-ai-chatbot/uninstall.php"; $p=$GLOBALS["wpdb"]->prefix; foreach (["rag_ai_sources","rag_ai_documents","rag_ai_vector_collections","rag_ai_vectors","rag_ai_jobs"] as $s) { $t=$p.$s; if ($GLOBALS["wpdb"]->get_var($GLOBALS["wpdb"]->prepare("SHOW TABLES LIKE %s",$t)) !== $t) { fwrite(STDERR, "Default uninstall unexpectedly deleted {$t}\n"); exit(1); } }'

# Explicit opt-in removes plugin-owned schema and policy/version options.
$WP option update wp_rag_ai_delete_data_on_uninstall 1 --format=json
$WP eval 'define("WP_UNINSTALL_PLUGIN", "wp-rag-ai-chatbot/wp-rag-ai-chatbot.php"); include WP_PLUGIN_DIR . "/wp-rag-ai-chatbot/uninstall.php"; $p=$GLOBALS["wpdb"]->prefix; foreach (["rag_ai_jobs","rag_ai_vectors","rag_ai_vector_collections","rag_ai_documents","rag_ai_sources"] as $s) { $t=$p.$s; if ($GLOBALS["wpdb"]->get_var($GLOBALS["wpdb"]->prepare("SHOW TABLES LIKE %s",$t)) === $t) { fwrite(STDERR, "Opt-in uninstall did not delete {$t}\n"); exit(1); } } if (false !== get_option("wp_rag_ai_db_version", false) || false !== get_option("wp_rag_ai_delete_data_on_uninstall", false)) { fwrite(STDERR, "Opt-in uninstall did not delete plugin database options\n"); exit(1); }'

# A clean reinstall must recreate the schema and repositories successfully.
$WP plugin activate wp-rag-ai-chatbot --quiet
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-database.php
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-job-queue.php
