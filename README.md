# WP RAG AI Chatbot

WordPress-native RAG AI chatbot and customer-support platform.

> Repository initialization only. Production implementation starts after the approved Superpowers architecture/specification and implementation plans are persisted.

## Background job execution

M09 runs queued synchronization work through one bounded worker service. While the plugin is active, WordPress schedules the stable `wp_rag_ai_jobs_run` hook hourly when no existing event is registered. Deactivation removes that schedule.

On low-traffic sites, or when `DISABLE_WP_CRON` is enabled, run the same worker from a real server cron using WP-CLI. For example, from the WordPress installation directory:

```sh
wp wp-rag-ai jobs run --limit=10
```

The `--limit` value must be between 1 and 100. Cron and WP-CLI delegate to the same worker semantics; WP-CLI is an execution entrypoint, not a separate queue implementation. Configure the operating-system scheduler at an interval appropriate for the site, and keep queue payloads free of credentials or other secrets.

Terminal queue-history cleanup is deliberately bounded to at most 500 rows per pass. Cleanup only targets completed `succeeded`, `failed`, or `cancelled` jobs older than the selected retention cutoff; active jobs are not cleanup candidates.
