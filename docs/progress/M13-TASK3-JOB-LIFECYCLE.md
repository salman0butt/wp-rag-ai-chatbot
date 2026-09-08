# M13 Task 3 — Recoverable Job Status and Safe Lifecycle Controls

Status: COMPLETE

## Scope

Task 3 exposes bounded administrator job status plus safe enqueue, cancel, and retry controls by reusing the existing M09 queue, repository, state-transition, and clock seams. No parallel queue, storage layer, or lifecycle state machine was introduced.

## Delivered contract

- `GET /wp-rag-ai-chatbot/v1/admin/knowledge/jobs` returns one bounded persisted job page.
- `POST /wp-rag-ai-chatbot/v1/admin/knowledge/jobs` enqueues the established identifier-only document-index payload through the M09 enqueuer.
- `POST /wp-rag-ai-chatbot/v1/admin/knowledge/jobs/{job_key}/cancel` delegates supported cancellation to the M09 repository.
- `POST /wp-rag-ai-chatbot/v1/admin/knowledge/jobs/{job_key}/retry` creates a fresh generation only for failed `index.document` jobs.
- All routes use the centralized `AdminCapability::can_manage` permission callback.
- List page size is capped at 100.
- Unsupported lifecycle changes return stable `invalid_transition` and are rejected before the mutation/enqueue seam is invoked.
- The response projection is allow-listed and excludes payload, idempotency key, lease owner/expiry, and other queue mutation internals.
- Persisted `last_error_code` and `last_error_message` come from the established M09 sanitized diagnostic contract rather than raw exception/provider payloads.

## Strict TDD evidence

### Genuine RED

Commit `c7e122f641c7734905711416a9bc318f8cc78fb0`, CI `34267619024`.

Static analysis passed, then PHPUnit executed 687 tests / 2,897 assertions and produced exactly two expected errors because the protected Task 3 collection/action routes were missing. `js-quality`, `package`, and `wordpress-smoke` passed. This is the authoritative behavioral RED.

### Non-GREEN implementation checkpoints

- `f360a2151da5e88de25e5c36746c23f496b04def`, CI `34272080802`: production routes/callbacks were wired, but PHP quality stopped on a redundant impossible null comparison against the WordPress REST request contract. This is not RED and not GREEN.
- `ac9f1947c616b12853a4a06bb684b091cab8a4d3`, CI `34272440264`: static analysis passed and PHPUnit reached the suite, but five pre-existing route-count harness expectations were stale by exactly the two new routes. This is a verification-harness checkpoint, not behavioral RED.
- Harness-only count reconciliations: `c21da352d82cf5bdce20dfb0bfdce6219be8b96a`, `fcc67f9207b2fdeeb85003c60ea569927a70f16d`, `da2cec0277762e66dd5c3efc2f43cc36a76648fe`, `645f58fa08a1c3dff3d31128a700071a94c2c97a`.

### Verified GREEN

Exact implementation/harness head `645f58fa08a1c3dff3d31128a700071a94c2c97a`, CI `34272705657`:

- `php-quality`: GREEN — PHPStan 288/288 with no errors; PHPUnit 687/687 tests / 2,897 assertions; Composer audit clean.
- `js-quality`: GREEN.
- `package`: GREEN.
- `wordpress-smoke`: GREEN through activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and environment cleanup.

## Correctness and persisted-state verification

Unit coverage proves terminal cancellation does not invoke `requestCancellation`, non-failed retry does not enqueue, failed document-index retry reuses the M09 enqueue contract, and new indexing requests accept only the established identifier-only payload. Integration coverage uses persisted job rows and proves an unsupported terminal cancellation performs no mutation query/update.

## Security review

Scoped review `5146492264`: 0 Critical / 0 Important.

- All Task 3 routes remain administrator capability protected.
- Output is allow-listed and does not serialize payload, idempotency, or lease fields.
- Error diagnostics reuse the M09 sanitized code/message contract; arbitrary raw exception/provider payloads are not introduced at this boundary.
- Unsupported transitions are guarded before mutation.

A separate Superpowers subagent execution transport is not exposed in this runtime, so this record does not claim independent-subagent execution. The repository-scoped review path completed with no blocking findings; milestone-wide review remains required at final M13 closeout.

## Performance review

Job inventory reads use prepared bounded pagination with `per_page <= 100`; resource projection is linear in the bounded page and performs no provider/network work. Lifecycle actions target one job identity and reuse existing M09 transitions.

## Accessibility

N/A for this server-only slice. Knowledge/jobs UI and accessibility are Task 4 scope.

## Exact next unfinished task

Task 4 — Knowledge manager admin UI. Consume the Task 1–3 REST DTOs through the existing typed nonce-authenticated admin client. Establish a genuine Jest RED for paginated source rendering, selected detail, loading/empty/error states, supported job actions, and keyboard-labelled controls; then implement server-authoritative pagination and post-mutation refresh without caching secret/unbounded data, including constrained-width and long-content accessibility/CSS coverage before Task 4 closeout.
