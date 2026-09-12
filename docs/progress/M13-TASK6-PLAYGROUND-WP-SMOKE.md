# M13 Task 6 — WordPress Playground REST Smoke

Status: **COMPLETE verification prerequisite — exact-head GREEN**

## Scope

Task 6 already had unit/integration coverage for the bounded request contract, production request handler, runtime composition, exact production retrieval/chat execution, protected route registration, and REST success/error projection. The remaining verification gap was a real WordPress-level smoke assertion for the protected administrator route itself.

This smoke coverage does not call external AI services. It exercises only route registration, WordPress capability enforcement, and fail-closed request parsing paths that terminate before provider/retrieval execution.

## Implementation

The WordPress smoke adds:

- `scripts/test-wp-playground-rest.php` — real `WP_REST_Request` assertions inside `wp-env`;
- `scripts/test-wp-playground-rest.sh` — plugin activation plus WP-CLI runner;
- `npm run test:wp:playground-rest` — explicit local/CI command;
- the Playground REST smoke as a required step in the permanent `wordpress-smoke` CI job.

The smoke verifies:

1. `POST /wp-rag-ai-chatbot/v1/admin/debug/playground` is registered by the real plugin bootstrap;
2. an anonymous caller is denied by the route's `AdminCapability::can_manage` permission callback;
3. an administrator request with missing persisted selectors reaches the production callback/request parser and returns the stable `invalid_request` code;
4. an administrator request containing a request-level `provider` override is rejected as `invalid_request`, proving the public REST contract remains closed to arbitrary runtime/provider overrides.

The last two checks deliberately terminate at `PlaygroundRequest` validation, so the smoke requires no provider credentials and performs no external generation, embedding, or vector-store calls.

## Verification evidence

Exact-head SHA: `0230ef184ef9a7558cb38e6541a5d8e207f21fb5`

CI: `34671573305`

The complete workflow is GREEN across all permanent jobs:

- `php-quality` — GREEN;
- `js-quality` — GREEN;
- `package` — GREEN;
- `wordpress-smoke` — GREEN.

Within `wordpress-smoke`, the new `npm run test:wp:playground-rest` step completed successfully after activation, database, provider, knowledge, file-ingestion, and WooCommerce-knowledge smoke checks. Environment cleanup also completed successfully.

## Review

Scoped correctness/security/performance/architecture review:

- **Critical:** 0 unresolved for this verification subunit.
- **Important:** 0 unresolved for this verification subunit.
- The smoke proves the route is protected by real WordPress capability evaluation rather than only unit-level callback assertions.
- The override regression covers the architectural prohibition against request-level provider/runtime selection without requiring live credentials.
- No production execution path or RAG logic was duplicated by the smoke harness.
- Runtime cost is bounded to the existing WordPress smoke environment and two fail-closed request paths after route discovery.

This is repository-approved fallback review evidence, not a claim that an independent reviewer/subagent was available.

## Continuation

Task 6 now has real WordPress route smoke evidence. Perform the final fresh Task 6 correctness/security/performance/architecture review across the complete request → persisted configuration → production retrieval/chat → bounded response path. Resolve every Critical/Important finding under strict RED → GREEN, reconcile the stale Task 6 milestone/status records, require exact-final-head four-job GREEN, and only then mark Task 6 COMPLETE and begin Task 7 automatically.
