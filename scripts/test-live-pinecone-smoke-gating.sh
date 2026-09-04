#!/usr/bin/env bash
set -euo pipefail

script="scripts/live-pinecone-smoke.sh"

skip_output="$(bash "$script")"
if [[ "$skip_output" != *"Live Pinecone smoke skipped"* ]]; then
  echo "Expected live Pinecone smoke to skip without explicit opt-in." >&2
  exit 1
fi

missing_host_output="$(mktemp)"
if WP_RAG_AI_LIVE_PINECONE_TESTS=1 bash "$script" >"$missing_host_output" 2>&1; then
  echo "Expected live Pinecone smoke to reject a missing PINECONE_INDEX_HOST." >&2
  exit 1
fi
if ! grep -q 'PINECONE_INDEX_HOST' "$missing_host_output"; then
  echo "Expected missing PINECONE_INDEX_HOST diagnostic." >&2
  exit 1
fi

missing_key_output="$(mktemp)"
if WP_RAG_AI_LIVE_PINECONE_TESTS=1 PINECONE_INDEX_HOST='https://pinecone.example.test' bash "$script" >"$missing_key_output" 2>&1; then
  echo "Expected live Pinecone smoke to reject a missing PINECONE_API_KEY." >&2
  exit 1
fi
if ! grep -q 'PINECONE_API_KEY' "$missing_key_output"; then
  echo "Expected missing PINECONE_API_KEY diagnostic." >&2
  exit 1
fi

missing_index_output="$(mktemp)"
if WP_RAG_AI_LIVE_PINECONE_TESTS=1 PINECONE_INDEX_HOST='https://pinecone.example.test' PINECONE_API_KEY='test-secret' bash "$script" >"$missing_index_output" 2>&1; then
  echo "Expected live Pinecone smoke to reject a missing PINECONE_INDEX." >&2
  exit 1
fi
if ! grep -q 'PINECONE_INDEX' "$missing_index_output"; then
  echo "Expected missing PINECONE_INDEX diagnostic." >&2
  exit 1
fi

mock_dir="$(mktemp -d)"
mock_args="$(mktemp)"
cat >"$mock_dir/npm" <<'MOCK'
#!/usr/bin/env bash
printf '%s\n' "$@" >"${WP_RAG_AI_PINECONE_MOCK_ARGS}"
MOCK
chmod +x "$mock_dir/npm"

live_output="$(
  PATH="$mock_dir:$PATH" \
  WP_RAG_AI_PINECONE_MOCK_ARGS="$mock_args" \
  WP_RAG_AI_LIVE_PINECONE_TESTS=1 \
  PINECONE_INDEX_HOST='https://pinecone.example.test' \
  PINECONE_API_KEY='test-secret' \
  PINECONE_INDEX='docs-index' \
  bash "$script"
)"

if [[ "$live_output" == *'test-secret'* ]]; then
  echo "Live Pinecone smoke must not echo credentials." >&2
  exit 1
fi
if ! grep -qx 'PINECONE_INDEX_HOST=https://pinecone.example.test' "$mock_args"; then
  echo "Expected Pinecone index host to be scoped to the wp-env CLI process." >&2
  exit 1
fi
if ! grep -qx 'PINECONE_API_KEY=test-secret' "$mock_args"; then
  echo "Expected Pinecone API key to be scoped to the wp-env CLI process." >&2
  exit 1
fi
if ! grep -qx 'PINECONE_INDEX=docs-index' "$mock_args"; then
  echo "Expected Pinecone index name to be scoped to the wp-env CLI process." >&2
  exit 1
fi
if ! grep -qx 'wp-content/plugins/wp-rag-ai-chatbot/scripts/live-pinecone-smoke.php' "$mock_args"; then
  echo "Expected the live Pinecone WP-CLI smoke entrypoint." >&2
  exit 1
fi

rm -f "$missing_host_output" "$missing_key_output" "$missing_index_output" "$mock_args"
rm -rf "$mock_dir"

echo "Live Pinecone smoke gating checks passed."
