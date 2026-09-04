#!/usr/bin/env bash
set -euo pipefail

script="scripts/live-chroma-smoke.sh"

if [[ ! -f "$script" ]]; then
  echo "Expected the opt-in live Chroma smoke wrapper." >&2
  exit 1
fi

skip_output="$(bash "$script")"
if [[ "$skip_output" != *"Live Chroma smoke skipped"* ]]; then
  echo "Expected live Chroma smoke to skip without explicit opt-in." >&2
  exit 1
fi

missing_endpoint_output="$(mktemp)"
if WP_RAG_AI_LIVE_CHROMA_TESTS=1 bash "$script" >"$missing_endpoint_output" 2>&1; then
  echo "Expected live Chroma smoke to reject a missing CHROMA_ENDPOINT." >&2
  exit 1
fi
if ! grep -q 'CHROMA_ENDPOINT' "$missing_endpoint_output"; then
  echo "Expected missing CHROMA_ENDPOINT diagnostic." >&2
  exit 1
fi

missing_tenant_output="$(mktemp)"
if WP_RAG_AI_LIVE_CHROMA_TESTS=1 CHROMA_ENDPOINT='https://chroma.example.test' bash "$script" >"$missing_tenant_output" 2>&1; then
  echo "Expected live Chroma smoke to reject a missing CHROMA_TENANT." >&2
  exit 1
fi
if ! grep -q 'CHROMA_TENANT' "$missing_tenant_output"; then
  echo "Expected missing CHROMA_TENANT diagnostic." >&2
  exit 1
fi

missing_database_output="$(mktemp)"
if WP_RAG_AI_LIVE_CHROMA_TESTS=1 CHROMA_ENDPOINT='https://chroma.example.test' CHROMA_TENANT='default_tenant' bash "$script" >"$missing_database_output" 2>&1; then
  echo "Expected live Chroma smoke to reject a missing CHROMA_DATABASE." >&2
  exit 1
fi
if ! grep -q 'CHROMA_DATABASE' "$missing_database_output"; then
  echo "Expected missing CHROMA_DATABASE diagnostic." >&2
  exit 1
fi

mock_dir="$(mktemp -d)"
mock_args="$(mktemp)"
cat >"$mock_dir/npm" <<'MOCK'
#!/usr/bin/env bash
printf '%s\n' "$@" >"${WP_RAG_AI_CHROMA_MOCK_ARGS}"
MOCK
chmod +x "$mock_dir/npm"

live_output="$(
  PATH="$mock_dir:$PATH" \
  WP_RAG_AI_CHROMA_MOCK_ARGS="$mock_args" \
  WP_RAG_AI_LIVE_CHROMA_TESTS=1 \
  CHROMA_ENDPOINT='https://chroma.example.test' \
  CHROMA_TENANT='default_tenant' \
  CHROMA_DATABASE='default_database' \
  CHROMA_TOKEN='test-secret' \
  bash "$script"
)"

if [[ "$live_output" == *'test-secret'* ]]; then
  echo "Live Chroma smoke must not echo credentials." >&2
  exit 1
fi
if ! grep -qx 'CHROMA_ENDPOINT=https://chroma.example.test' "$mock_args"; then
  echo "Expected Chroma endpoint to be scoped to the wp-env CLI process." >&2
  exit 1
fi
if ! grep -qx 'CHROMA_TENANT=default_tenant' "$mock_args"; then
  echo "Expected Chroma tenant to be scoped to the wp-env CLI process." >&2
  exit 1
fi
if ! grep -qx 'CHROMA_DATABASE=default_database' "$mock_args"; then
  echo "Expected Chroma database to be scoped to the wp-env CLI process." >&2
  exit 1
fi
if ! grep -qx 'CHROMA_TOKEN=test-secret' "$mock_args"; then
  echo "Expected optional Chroma token to be scoped to the wp-env CLI process." >&2
  exit 1
fi
if ! grep -qx 'wp-content/plugins/wp-rag-ai-chatbot/scripts/live-chroma-smoke.php' "$mock_args"; then
  echo "Expected the live Chroma WP-CLI smoke entrypoint." >&2
  exit 1
fi

rm -f "$missing_endpoint_output" "$missing_tenant_output" "$missing_database_output" "$mock_args"
rm -rf "$mock_dir"

echo "Live Chroma smoke gating checks passed."
