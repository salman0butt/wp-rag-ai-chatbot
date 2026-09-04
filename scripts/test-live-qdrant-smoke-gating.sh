#!/usr/bin/env bash
set -euo pipefail

script="scripts/live-qdrant-smoke.sh"

skip_output="$(bash "$script")"
if [[ "$skip_output" != *"Live Qdrant smoke skipped"* ]]; then
  echo "Expected live Qdrant smoke to skip without explicit opt-in." >&2
  exit 1
fi

missing_url_output="$(mktemp)"
if WP_RAG_AI_LIVE_QDRANT_TESTS=1 bash "$script" >"$missing_url_output" 2>&1; then
  echo "Expected live Qdrant smoke to reject a missing QDRANT_URL." >&2
  exit 1
fi
if ! grep -q 'QDRANT_URL' "$missing_url_output"; then
  echo "Expected missing QDRANT_URL diagnostic." >&2
  exit 1
fi

missing_key_output="$(mktemp)"
if WP_RAG_AI_LIVE_QDRANT_TESTS=1 QDRANT_URL='https://qdrant.example.test' bash "$script" >"$missing_key_output" 2>&1; then
  echo "Expected live Qdrant smoke to reject a missing QDRANT_API_KEY." >&2
  exit 1
fi
if ! grep -q 'QDRANT_API_KEY' "$missing_key_output"; then
  echo "Expected missing QDRANT_API_KEY diagnostic." >&2
  exit 1
fi

mock_dir="$(mktemp -d)"
mock_args="$(mktemp)"
cat >"$mock_dir/npm" <<'MOCK'
#!/usr/bin/env bash
printf '%s\n' "$@" >"${WP_RAG_AI_QDRANT_MOCK_ARGS}"
MOCK
chmod +x "$mock_dir/npm"

live_output="$(
  PATH="$mock_dir:$PATH" \
  WP_RAG_AI_QDRANT_MOCK_ARGS="$mock_args" \
  WP_RAG_AI_LIVE_QDRANT_TESTS=1 \
  QDRANT_URL='https://qdrant.example.test' \
  QDRANT_API_KEY='test-secret' \
  bash "$script"
)"

if [[ "$live_output" == *'test-secret'* ]]; then
  echo "Live Qdrant smoke must not echo credentials." >&2
  exit 1
fi
if ! grep -qx 'QDRANT_URL=https://qdrant.example.test' "$mock_args"; then
  echo "Expected Qdrant URL to be scoped to the wp-env CLI process." >&2
  exit 1
fi
if ! grep -qx 'QDRANT_API_KEY=test-secret' "$mock_args"; then
  echo "Expected Qdrant API key to be scoped to the wp-env CLI process." >&2
  exit 1
fi
if ! grep -qx 'wp-content/plugins/wp-rag-ai-chatbot/scripts/live-qdrant-smoke.php' "$mock_args"; then
  echo "Expected the live Qdrant WP-CLI smoke entrypoint." >&2
  exit 1
fi

rm -f "$missing_url_output" "$missing_key_output" "$mock_args"
rm -rf "$mock_dir"

echo "Live Qdrant smoke gating checks passed."
