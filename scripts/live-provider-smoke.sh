#!/usr/bin/env bash
set -euo pipefail

if [[ "${WP_RAG_AI_LIVE_PROVIDER_TESTS:-}" != "1" ]]; then
  echo "Live provider smoke skipped (WP_RAG_AI_LIVE_PROVIDER_TESTS is not 1)."
  exit 0
fi

provider="${1:-}"
case "$provider" in
  openai)
    key_name="OPENAI_API_KEY"
    key_value="${OPENAI_API_KEY:-}"
    model="${WP_RAG_AI_LIVE_OPENAI_MODEL:-}"
    ;;
  openrouter)
    key_name="OPENROUTER_API_KEY"
    key_value="${OPENROUTER_API_KEY:-}"
    model="${WP_RAG_AI_LIVE_OPENROUTER_MODEL:-}"
    ;;
  *)
    echo "Live provider must be openai or openrouter." >&2
    exit 2
    ;;
esac

if [[ -z "${key_value//[[:space:]]/}" ]]; then
  echo "Live provider credential is missing: ${key_name}." >&2
  exit 2
fi

# Pass the credential only to the isolated wp-env CLI process. Do not echo it.
npm run --silent wp-env -- run cli env \
  "${key_name}=${key_value}" \
  wp eval-file \
  wp-content/plugins/wp-rag-ai-chatbot/scripts/live-provider-smoke.php \
  "$provider" \
  "$model"
