#!/usr/bin/env bash
set -euo pipefail

if [[ "${WP_RAG_AI_LIVE_PINECONE_TESTS:-}" != "1" ]]; then
  echo "Live Pinecone smoke skipped because WP_RAG_AI_LIVE_PINECONE_TESTS is not 1."
  exit 0
fi

endpoint="${PINECONE_INDEX_HOST:-}"
api_key="${PINECONE_API_KEY:-}"
index_name="${PINECONE_INDEX:-}"

if [[ -z "${endpoint//[[:space:]]/}" ]]; then
  echo "Live Pinecone smoke requires PINECONE_INDEX_HOST." >&2
  exit 2
fi

if [[ -z "${api_key//[[:space:]]/}" ]]; then
  echo "Live Pinecone smoke requires PINECONE_API_KEY." >&2
  exit 2
fi

if [[ -z "${index_name//[[:space:]]/}" ]]; then
  echo "Live Pinecone smoke requires PINECONE_INDEX." >&2
  exit 2
fi

npm run --silent wp-env -- run cli env \
  "PINECONE_INDEX_HOST=${endpoint}" \
  "PINECONE_API_KEY=${api_key}" \
  "PINECONE_INDEX=${index_name}" \
  wp eval-file \
  wp-content/plugins/wp-rag-ai-chatbot/scripts/live-pinecone-smoke.php
