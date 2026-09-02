#!/usr/bin/env bash
set -euo pipefail

output="$(env -u WP_RAG_AI_LIVE_PROVIDER_TESTS bash scripts/live-provider-smoke.sh openai)"
if [[ "$output" != *"skipped"* ]]; then
  echo "Live provider smoke did not skip by default." >&2
  exit 1
fi

if WP_RAG_AI_LIVE_PROVIDER_TESTS=1 bash scripts/live-provider-smoke.sh invalid-provider >/dev/null 2>&1; then
  echo "Live provider smoke accepted an invalid provider." >&2
  exit 1
fi

if env -u OPENAI_API_KEY WP_RAG_AI_LIVE_PROVIDER_TESTS=1 bash scripts/live-provider-smoke.sh openai >/dev/null 2>&1; then
  echo "Live provider smoke accepted a missing OpenAI credential." >&2
  exit 1
fi

if env -u OPENROUTER_API_KEY WP_RAG_AI_LIVE_PROVIDER_TESTS=1 bash scripts/live-provider-smoke.sh openrouter >/dev/null 2>&1; then
  echo "Live provider smoke accepted a missing OpenRouter credential." >&2
  exit 1
fi

echo "Live provider smoke gating passed without network calls."
