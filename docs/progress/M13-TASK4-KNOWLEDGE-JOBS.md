# M13 Task 4 — Knowledge Job Inventory UI

Status: COMPLETE SLICE — Task 4 remains ACTIVE

## Scope

This bounded Task 4 slice integrates the existing Task 3 read-only job inventory into the top-level Knowledge administration screen. It reuses the existing nonce-authenticated same-origin admin client and the existing Task 3 protected REST contract; no new queue, lifecycle state machine, API, authorization seam, or browser-side job cache was introduced.

## Delivered behavior

- The top-level `#/knowledge` bootstrap requests `GET /admin/knowledge/jobs?page=1&per_page=20` after the bounded source page loads.
- Job inventory is server-authoritative. Leaving Knowledge discards the in-memory page; returning to the top-level Knowledge screen refetches it.
- The inventory renders even when the source page is empty, keeping operational indexing visibility independent from source-list presence.
- The client DTO mirrors only the existing Task 3 allow-listed projection: job identity/type/status, attempts, availability/cancellation timestamps, bounded progress fields, sanitized persisted error code/message, lifecycle timestamps, and created/updated timestamps.
- The rendered slice currently exposes type, status, progress, progress message, and sanitized persisted error code/message. It does not expose payload, idempotency key, lease owner/expiry, raw exception/provider payload, source config/hash, or raw document data.
- Lifecycle mutation controls are deliberately deferred to the next fresh TDD slice.

## Strict TDD evidence

### Genuine RED

Commit `b89241f9c8f9a2735e2b42a1e3cfc18770988b6b`, CI `34312568823`.

`lint:js` and TypeScript typecheck passed, then Jest executed 20 suites / 53 tests. Exactly one test failed: `knowledge job inventory bootstrap › loads and renders the bounded safe job page through the admin client`. The bootstrap made only the readiness and source-page requests; the expected third request to `/admin/knowledge/jobs?page=1&per_page=20` was absent. Result: 19 suites passed / 1 failed, 52 tests passed / exactly 1 failed. `php-quality`, `package`, and complete `wordpress-smoke` were GREEN. This is the authoritative behavioral RED.

### Non-RED formatting checkpoint

Commit `0ae391bfce7f6c7b7db8f5860dcdae0c74c0ab46`, CI `34316447840`, is not RED or GREEN. A test-only formatting experiment introduced two Prettier errors and stopped before Jest. Commit `b4149ae99ca8a6faf80327be7b8f7ed2769b9d24` restored the exact verified RED test bytes before production work continued.

### Verified GREEN

Implementation commit `ec9089ddd9cd7e503a848007effa68015af521e2`, CI `34317251523`:

- `php-quality`: GREEN.
- `js-quality`: GREEN — lint and typecheck passed; Jest 20/20 suites and 53/53 tests passed; build and JavaScript live/package gates passed.
- `package`: GREEN.
- `wordpress-smoke`: GREEN through environment startup, activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and cleanup.

## Review

Scoped repository review `5150358437`, anchored to implementation SHA `ec9089ddd9cd7e503a848007effa68015af521e2`: 0 Critical / 0 Important.

### Correctness

The implementation performs one bounded inventory read on the top-level Knowledge overview, renders the returned server DTO, handles the no-sources case, and clears/refetches inventory state rather than treating browser state as authoritative.

### Security and privacy

No new secret-bearing boundary was introduced. The browser DTO follows the existing Task 3 allow-list and excludes payload, idempotency, lease internals, raw provider/exception data, source configuration/hash, and raw document data. Error diagnostics remain limited to the existing sanitized persisted `last_error_code` and `last_error_message` contract.

### Accessibility

This read-only slice uses a labelled section heading and semantic list. Interactive enqueue/cancel/retry controls are intentionally next-slice work and must receive explicit keyboard/focus/error-state coverage before Task 4 closeout.

### Performance

The browser performs one bounded `per_page=20` REST read and linear rendering over that bounded page. It introduces no provider/network fan-out or unbounded client-side accumulation.

A separate Superpowers reviewer-subagent execution transport was unavailable in this runtime because the native MCP tunnel returned transient 404/429 responses. This record therefore does not claim independent-subagent execution. Final Task 4 and milestone-wide independent closeout review remain outstanding.

## Exact next unfinished task

Continue Task 4 under fresh genuine Jest REDs for the existing Task 3 mutation contracts: `POST /admin/knowledge/jobs` enqueue, then `{job_key}/cancel` and `{job_key}/retry`. Refresh the bounded inventory server-authoritatively after each mutation; expose keyboard-labelled controls only for supported UI actions; surface stable `invalid_transition` and sanitized errors without raw exception/provider payloads; and do not cache payload/idempotency/lease internals. Then finish explicit loading/empty/error behavior plus constrained-width, long-content, keyboard/accessibility, and responsive CSS coverage before final Task 4 review and exact-final-SHA CI.