#!/usr/bin/env bash
set -euo pipefail

if [[ "${WP_RAG_AI_LIVE_CHROMA_TESTS:-}" != "1" ]]; then
  echo "Live Chroma smoke skipped because WP_RAG_AI_LIVE_CHROMA_TESTS is not 1."
  exit 0
fi

endpoint="${CHROMA_ENDPOINT:-}"
tenant="${CHROMA_TENANT:-}"
database="${CHROMA_DATABASE:-}"
token="${CHROMA_TOKEN:-}"

if [[ -z "${endpoint//[[:space:]]/}" ]]; then
  echo "Live Chroma smoke requires CHROMA_ENDPOINT." >&2
  exit 2
fi

if [[ -z "${tenant//[[:space:]]/}" ]]; then
  echo "Live Chroma smoke requires CHROMA_TENANT." >&2
  exit 2
fi

if [[ -z "${database//[[:space:]]/}" ]]; then
  echo "Live Chroma smoke requires CHROMA_DATABASE." >&2
  exit 2
fi

npm run --silent wp-env -- run cli env \
  "CHROMA_ENDPOINT=${endpoint}" \
  "CHROMA_TENANT=${tenant}" \
  "CHROMA_DATABASE=${database}" \
  "CHROMA_TOKEN=${token}" \
  wp eval-file \
  wp-content/plugins/wp-rag-ai-chatbot/scripts/live-chroma-smoke.php
