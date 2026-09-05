# M09 — Database Job Queue, Synchronization, Retries & Recovery

Status: **IN PROGRESS — Tasks 1–2 complete; Task 3 next**

## Goal
Run large indexing/synchronization reliably outside normal page-request lifetime.

## Dependencies
M02 DB, M07-M08 indexing/vector behaviors.

## Architecture gate

- Classification: **architectural**.
- Design/spec: `docs/superpowers/specs/2026-09-05-m09-job-queue-sync-recovery-design.md` (`987e520a8c4ceea1f4c7d0e610a9d7825d1f3b1a`) — **AUTO-APPROVED — SCHEDULED MODE**.
- Implementation plan: `docs/superpowers/plans/2026-09-05-m09-job-queue-sync-recovery.md` (`32bc0aff95f0035d8e6d06c9c2f47497db34776c`) — **AUTO-APPROVED — SCHEDULED MODE**.
- Selected architecture: one versioned per-site jobs table, typed/allowlisted job handlers, optimistic conditional leases, deterministic bounded retry/backoff, bounded workers, cooperative cancellation/progress, thin WP-Cron and WP-CLI entrypoints, and narrow reuse of M07/M08 orchestration.
- No Redis/external queue dependency; no `SKIP LOCKED` requirement; no provider-level automatic retries.

## Acceptance criteria

- Concurrent workers do not double-run leased jobs.
- Expired leases recover while stale owners cannot mutate reclaimed jobs.
- Retryable and terminal failures differ and attempts are bounded.
- Duplicate enqueue is controlled through active `(type, idempotency_key)` semantics.
- Progress is bounded/monotonic and cancellation is tested.
- WP-Cron and WP-CLI/server-cron execute the same bounded worker service.
- Low-traffic/WP-Cron-disabled operation is documented.
- Job payloads/types are validated and cannot execute arbitrary PHP callables/classes.
- Secrets are excluded from payload/progress/error persistence.
- Queue scans, worker loops and cleanup are bounded.

## Planned tasks

1. **Task 1 — Jobs schema and immutable queue contracts — COMPLETE.**
2. **Task 2 — Atomic enqueue, lease claim, heartbeat, progress, cancellation and recovery repository — COMPLETE.**
3. **Task 3 — Retry/failure state machine, typed handler registry and bounded worker — NEXT.**
4. Task 4 — WP-Cron, WP-CLI/server-cron execution and bounded cleanup.
5. Task 5 — M07/M08 synchronization orchestration through queued jobs.
6. Task 6 — Whole-M09 security/performance/recovery review, verification, merge and post-merge closeout.

## Task 1 — Jobs schema and immutable queue contracts

Task 1 delivered:

- schema V5 and the per-site `${prefix}rag_ai_jobs` table;
- queue, lease-recovery, idempotency lookup and terminal-cleanup indexes;
- migration registration and `TableNames::jobs()` lifecycle integration;
- real WordPress V1→V5 upgrade, idempotent migration, uninstall retention/deletion and clean-reinstall coverage;
- stable `JobStatus` values;
- bounded immutable `JobRequest` validation for type, JSON-object payload, encoded size, nesting depth, idempotency key and attempts;
- immutable hydrated `JobRecord` covering the persisted Task 1 schema;
- the `JobRepository::enqueue(JobRequest, DateTimeImmutable): JobRecord` persistence boundary required by Task 2;
- rejection of runtime-only/executable PHP values before queue persistence.

### Task 1 TDD evidence

Recovered Task 1 production work exposed a real WordPress integration RED at `b09aaabebc9847bfa0f3ecf96060794041547a6f` / CI `33932076035`: PHP/unit quality was green but `wordpress-smoke` failed because the integration fixture still asserted schema V4 after the legitimate V5 migration.

The integration fixture was corrected without weakening production behavior:

- `125963e4ce44c18abe2b718038758a1e5cd8d8f8` — verify schema V5 plus jobs indexes in the real WordPress smoke;
- `307f892727feba9aa5021c4494d83dbf46a1a895` — cover jobs reset, V1→V5 upgrade, uninstall retention/deletion and clean reinstall;
- CI `33934236481` — all four permanent jobs GREEN.

Independent Task 1 review then produced genuine regression REDs:

- `882f892aab88153cae8a3940ae9d23c03e5e0640` — reject non-empty list-root queue payloads;
- `ee75559817f559fcacf2d57c7412b18b3232f324` — require immutable `JobRecord` plus the `JobRepository` enqueue boundary;
- CI `33934436192` reached PHPStan **0 errors**, then PHPUnit **418 tests / 1,890 assertions** with exactly **3 intended failures**.

Minimum fixes added the object-root validation and missing contracts. A final security review found that PHP objects such as closures could still pass `json_encode()` as JSON-like data:

- `46b41e0fa90356f60a468b8f420690731353d3e6` — regression test for executable/runtime-only payload objects;
- CI `33934839257` reached PHPStan **0 errors**, then PHPUnit **419 tests / 1,898 assertions** with exactly **1 intended failure**;
- `62534b47e10a5b47e8770ebcbed0c31877f35623` — recursively reject non-data PHP values before encoding.

Final Task 1 implementation GREEN:

- exact SHA `62534b47e10a5b47e8770ebcbed0c31877f35623`;
- CI `33934918490` — `php-quality`, `js-quality`, `package`, `wordpress-smoke` all GREEN;
- PHPUnit **419/419**, **1,898 assertions**;
- PHPStan **0 errors**;
- Composer audit **clean**;
- package artifact `9959839970`, digest `sha256:8f9e663d82e3fa109b49a4f4e9dfc164764e2155e302ba7dbdca706170cf6926`.

### Task 1 review

Task 1 independent review `5119113623` covered schema/index correctness, per-site prefixing, migration/uninstall lifecycle, stable queue contracts, payload/type/idempotency/attempt bounds, and executable/runtime-object persistence boundaries.

Findings fixed with regression-first evidence:

1. stale V4 WordPress database-smoke expectations after schema V5;
2. non-empty list-root payloads accepted despite JSON-object-root policy;
3. missing planned immutable `JobRecord` and `JobRepository` enqueue boundary;
4. executable/runtime-only PHP objects accepted through `json_encode()` behavior.

Task 1 final review result: **Critical 0 / Important 0 unresolved**.

## Task 2 — Atomic repository behavior

Task 2 delivered:

- non-idempotent enqueue with generated opaque job identities;
- named-lock idempotent enqueue and active-job deduplication with `finally` lock release;
- bounded deterministic due/recovery candidate scans;
- optimistic one-winner conditional lease claims;
- current-lease heartbeat and monotonic progress predicates;
- queued/retry direct cancellation and cooperative running cancellation requests;
- current-lease completion/retry/failure predicates;
- expired-running-job reclaim with stale-owner rejection;
- canonical UTC persistence formatting and bounded lease/error inputs;
- real WordPress/MySQL integration coverage for idempotency, hostile literals, competing claims, lease expiry/reclaim, stale owners, progress, cancellation and due retry-wait work.

### Task 2 TDD and recovery evidence

The recovered Task 2 branch initially reported a one-test PHPUnit failure at `ba77fab1c8a715241e35573d93bd2bf66ebb057c` / CI `33938412971`. Systematic debugging proved this was **not a production behavioral RED**: the PHPUnit fixture's `get_row()` arrow function captured the generated `$inserted_jobkey` by value before the insert callback populated it.

Fixture-only corrections:

- `e8cfe17f3c44c4e14fb662f64c316800b88d5520` — move key capture into the mocked insert invocation;
- `a342eca15332324f0fe3e0b3e50cca4ba558d432` — capture the generated key by reference during mocked rehydration;
- CI `33940191204` — PHP quality GREEN with PHPUnit **431/431, 1,962 assertions**, PHPStan 0 errors and Composer audit clean.

The missing real-database Task 2 gate was then added without production changes:

- `b21620c21ab4dcd07de02432da4ac90ab299b9af` — add the real WordPress queue repository smoke;
- `0bf964f402a07f3846afa5095c578bcf04feb150` — wire it into the permanent database smoke.

This produced a genuine review RED at `0bf964f402a07f3846afa5095c578bcf04feb150` / CI `33940320856`: `php-quality`, `js-quality` and `package` were GREEN, while `wordpress-smoke` failed exactly with **“Idempotent enqueue did not return the newest active matching job.”** The repository selected the oldest active duplicate with `ORDER BY id ASC`, contrary to the approved design's newest-active rule.

Minimum production fix:

- `a5d3203677ea1cca951391755e766657b795477f` — change active idempotency lookup to `ORDER BY id DESC LIMIT 1`.

Final Task 2 implementation GREEN:

- exact implementation SHA `a5d3203677ea1cca951391755e766657b795477f`;
- CI `33940511092` — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all GREEN;
- PHPUnit **431/431**, **1,962 assertions**;
- PHPStan **0 errors**;
- Composer audit **clean**;
- real WordPress database smoke passes duplicate-active newest selection, hostile-literal idempotent round-trip, one-winner lease claim, expired reclaim, stale-owner rejection, heartbeat/progress, cooperative running cancellation, queued cancellation and due `retry_wait` behavior;
- package artifact `9961657307`, digest `sha256:c93ebcef05b7bbbb88472a801db01966e627e5cdead745fd25c3a23323d6a4b8`.

### Task 2 independent review

Task 2 review `5119484425` covered atomicity predicates, named-lock release, bounded due/recovery scans, deterministic ordering, stale-worker safety, current-lease mutation predicates, cancellation race boundaries, prepared hostile literals, UTC persistence and real MySQL/WordPress coverage.

Finding fixed regression-first:

1. **Important:** active idempotency lookup returned the oldest matching non-terminal row instead of the newest row required by the approved M09 design. Regression `0bf964f402a07f3846afa5095c578bcf04feb150` / CI `33940320856`; fixed by `a5d3203677ea1cca951391755e766657b795477f`.

Task 2 final review result: **Critical 0 / Important 0 unresolved**.

## Security review

Tasks 1–2 preserve prepared value SQL, plugin-owned table identifiers, data-only bounded payloads, named-lock scoping, opaque lease identities, current-owner predicates for running transitions, cancellation-aware completion and no anonymous web mutation surface. Task 3 must keep persisted handler types allowlisted and persist only sanitized failures.

## Performance review

Task 1 schema provides queue-scan, lease-recovery, idempotency-lookup and terminal-cleanup indexes. Task 2 keeps due and expired candidate queries bounded to 10 each, merges/sorts deterministically and attempts at most 10 candidate claims per call. Later tasks must preserve bounded worker count/time and cleanup limits.

## Known limitations / deferrals

- M10 owns hybrid retrieval/reranking.
- M13 owns the primary admin jobs/progress UI.
- Cooperative cancellation is checkpoint-based rather than unsafe process termination.
- No external queue backend is introduced in M09.

## Exact next unfinished action

Begin **Task 3 — retry/failure state machine, typed handler registry and bounded worker** with a lint-clean test-only behavioral RED. Cover deterministic 30/60/120/... backoff capped at 900 seconds, duplicate/unknown handler rejection, retryable versus terminal failures, final-attempt failure, constant sanitization of unexpected `Throwable`, cancellation completion, bounded `WorkerConfig` count/time/lease limits, worker token generation, and execution-context heartbeat/progress/cancellation delegation. Require genuine RED before production behavior, then focused/full GREEN, independent review, regression-first fixes and durable Task 3 evidence.

## Next Milestone
M10 — Hybrid Retrieval, only after M09 is merged and fresh post-merge `main` CI is green.
