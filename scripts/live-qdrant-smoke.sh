#!/usr/bin/env bash
set -euo pipefail

if [[ "${WP_RAG_AI_LIVE_QDRANT_TESTS:-}" != "1" ]]; then
  echo "Live Qdrant smoke skipped because WP_RAG_AI_LIVE_QDRANT_TESTS is not 1."
  exit 0
fi

endpoint="${QDRANT_URL:-}"
api_key="${QDRANT_API_KEY:-}"

if [[ -z "${endpoint//[[:space:]]/}" ]]; then
  echo "Live Qdrant smoke requires QDRANT_URL." >&2
  exit 2
fi

if [[ -z "${api_key//[[:space:]]/}" ]]; then
  echo "Live Qdrant smoke requires QDRANT_API_KEY." >&2
  exit 2
fi

npm run --silent wp-env -- run cli env \
  "QDRANT_URL=${endpoint}" \
  "QDRANT_API_KEY=${api_key}" \
  wp eval-file \
  wp-content/plugins/wp-rag-ai-chatbot/scripts/live-qdrant-smoke.php
