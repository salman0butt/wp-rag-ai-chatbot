# M09 — Database Job Queue, Synchronization, Retries & Recovery

Status: **IN PROGRESS — Tasks 1–4 complete; Task 5 next**

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
3. **Task 3 — Retry/failure state machine, typed handler registry and bounded worker — COMPLETE.**
4. **Task 4 — WP-Cron, WP-CLI/server-cron execution and bounded cleanup — COMPLETE.**
5. **Task 5 — M07/M08 synchronization orchestration through queued jobs — NEXT.**
6. Task 6 — Whole-M09 security/performance/recovery review, verification, merge and post-merge closeout.

## Task 1 — Jobs schema and immutable queue contracts — COMPLETE

Delivered schema V5 and `${prefix}rag_ai_jobs`, required queue/recovery/idempotency/cleanup indexes, migration and uninstall lifecycle integration, real WordPress V1→V5/reinstall coverage, stable `JobStatus`, bounded immutable `JobRequest`, immutable hydrated `JobRecord`, `JobRepository::enqueue()`, and recursive rejection of runtime/executable PHP values before queue persistence.

Key evidence:

- final implementation `62534b47e10a5b47e8770ebcbed0c31877f35623` / CI `33934918490`;
- PHPUnit **419/419**, **1,898 assertions**; PHPStan 0 errors; Composer audit clean;
- package artifact `9959839970`, digest `sha256:8f9e663d82e3fa109b49a4f4e9dfc164764e2155e302ba7dbdca706170cf6926`;
- independent review `5119113623`: **Critical 0 / Important 0 unresolved**.

## Task 2 — Atomic queue repository behavior — COMPLETE

Delivered named-lock idempotent enqueue, bounded deterministic due/recovery scans, optimistic one-winner lease claims, current-owner heartbeat/progress/completion/retry/failure transitions, direct/cooperative cancellation, expired-lease reclaim, stale-owner rejection, UTC persistence, and real WordPress/MySQL integration coverage.

Key evidence:

- genuine integration review RED `0bf964f402a07f3846afa5095c578bcf04feb150` / CI `33940320856`: active idempotency lookup returned the oldest duplicate-active job instead of the approved newest active row;
- minimum fix `a5d3203677ea1cca951391755e766657b795477f` / CI `33940511092`;
- PHPUnit **431/431**, **1,962 assertions**; PHPStan 0 errors; Composer audit clean;
- package artifact `9961657307`, digest `sha256:c93ebcef05b7bbbb88472a801db01966e627e5cdead745fd25c3a23323d6a4b8`;
- independent review `5119484425`: **Critical 0 / Important 0 unresolved**.

## Task 3 — Retry state machine, typed handlers and bounded worker — COMPLETE

Delivered:

- deterministic retry policy: 30 seconds × `2^(attempts-1)`, capped at 900 seconds within the validated 1..10 attempt range;
- typed `JobHandler` boundary and explicit allowlisted `JobHandlerRegistry` with duplicate registration rejection and safe unknown-type failure;
- bounded `WorkerConfig` with default 10 jobs / 20-second start budget / 120-second lease and validated upper/lower bounds;
- opaque worker identity generated with `random_bytes()` and rendered as 64 lowercase hex characters;
- bounded worker claim/start loop;
- retryable failure → `retry_wait` only while attempts remain, otherwise terminal failure;
- explicitly terminal failure with no retry;
- constant sanitized persisted diagnostics for unexpected `Throwable` values;
- cancellation checked before handler lookup, plus cooperative `JobCancelledException` handling;
- lease-scoped `JobExecutionContext` heartbeat, progress and cancellation delegation;
- repository completion remains current-lease/live-lease/cancellation aware.

### Task 3 TDD evidence

- Handler-registry genuine RED `429419e629021a02f10753f4dddcbe6ec8169677` / CI `33945855793`: PHPStan **0 errors**; PHPUnit **444 tests / 1,975 assertions** with exactly **2 intended failures** proving valid registration and duplicate-registration behavior were not implemented.
- Worker value-boundary genuine RED `95c648f49d3089ebf8925342a2a2e1afe004858a` / CI `33946308243`: PHPStan **0 errors**; PHPUnit **452 tests / 1,983 assertions** with exactly **3 intended failures** for explicit worker config, typed execution exception and clock boundaries.
- Closeout review genuine RED `f4ae81a364d607bd1a7326d29ed7d9d8dd41056f` / CI `33949274962`: PHPStan clean and exactly **1 intended PHPUnit failure** showing an already-cancelled lease with an unknown type was failed before cancellation handling.
- Minimum closeout fix `99b9f176a2d16a9214ed8d5d594be536f56ae06a` moved cancellation priority ahead of handler resolution without broadening behavior.

### Task 3 verification

Exact implementation SHA `99b9f176a2d16a9214ed8d5d594be536f56ae06a` / CI `33950927777`:

- `php-quality` GREEN;
- `js-quality` GREEN;
- `package` GREEN;
- `wordpress-smoke` GREEN, including activation, database/job-queue smoke, providers, knowledge, file ingestion and WooCommerce knowledge;
- PHPUnit **468/468**, **2,011 assertions**;
- PHPStan **0 errors**;
- Composer audit **clean**;
- package artifact `9964796928`, digest `sha256:b7c26e874ce35ddcdb2b51eea1ee31ff0cc9f7a98cfa81a38ddbc32871380bcf`.

### Task 3 independent review

Review `5120228931` covered retry accounting, typed allowlist behavior, worker count/time/lease bounds, opaque worker identity, retryable/terminal/final-attempt transitions, constant error sanitization, pre-execution/cooperative cancellation, execution-context lease delegation and current-lease persistence boundaries.

Finding fixed regression-first:

1. **Important:** cancellation ran after handler lookup, causing an already-cancelled lease with an unknown persisted type to be terminally failed as `unknown_job_type`. RED `f4ae81a364d607bd1a7326d29ed7d9d8dd41056f` / CI `33949274962`; fixed by `99b9f176a2d16a9214ed8d5d594be536f56ae06a` / CI `33950927777`.

Task 3 final review result: **Critical 0 / Important 0 unresolved**. Zero unresolved inline review threads at review time.

## Task 4 — WordPress execution boundaries and bounded cleanup — COMPLETE

Delivered:

- stable `wp_rag_ai_jobs_run` WP-Cron hook registered only when no existing event is scheduled;
- cron callback delegates to the same bounded `JobRunner`/`JobWorker` used by other entrypoints;
- plugin deactivation clears all scheduled instances of the stable jobs hook and reactivation restores the schedule;
- `wp wp-rag-ai jobs run --limit=<1..100>` registration only when WP-CLI is available, with strict bounded limit validation and the same worker semantics as cron;
- `JobCleanup` plus `WpdbJobCleanupStore` terminal-history cleanup with a hard 500-row maximum per pass;
- cleanup narrows to completed `succeeded`, `failed`, or `cancelled` rows older than the requested cutoff, orders deterministically by `completed_at` then `id`, and uses prepared site-scoped SQL with `LIMIT`;
- the cleanup adapter fails closed unless the database reports an integer affected-row count within the requested bound;
- real WordPress activation/deactivation/reactivation smoke for cron lifecycle;
- real WordPress/MySQL cleanup smoke proving the requested row limit, deterministic oldest-first deletion, cutoff preservation, and non-terminal preservation;
- README operating guidance for low-traffic / `DISABLE_WP_CRON` sites using the same WP-CLI worker command.

### Task 4 TDD / recovery evidence

- Genuine Task 4 behavioral RED `92f748ae6f6287c0d492b63216797c85b24f9aae` / CI `33953938875`: PHPStan **0 errors**; PHPUnit reached **480 tests / 2,007 assertions** with **12 missing-boundary errors + 1 intentional bootstrap failure** for the planned cron/CLI/cleanup/runner/bootstrap behavior. `js-quality`, `package`, and `wordpress-smoke` were GREEN. This is the Task 4 behavioral RED; later lint/static/test-harness failures are not counted as RED evidence.
- Recovered head `b6d0ec6e02c51c56874c7d787a603131ae8a83b8` / CI `33954271488` stopped at PHPStan because `WpdbJobCleanupStore` could return boolean `true` from the generic `Connection::query()` contract while promising `int`; PHPUnit did not run, so this is not behavioral RED.
- Minimum contract fix `201738784662f7cddb6a99d2948cdbfbca301592` rejects every non-integer database result and out-of-bound affected-row count.
- CI `33956249584` then reached PHPStan **0 errors** and all **480 PHPUnit tests / 2,022 assertions** with no behavioral failures; five Brain Monkey expectation-only tests were reported risky because they intentionally had no PHPUnit assertions. Test-harness-only annotations in `e7f14c5dec2f4a931e67b8e40cee383dd7c77757`, `5b4e43acb4b99bcd981a672bd941fc0c3037941a`, and `244b6a180694986a7fab43db505f4f470a7de789` marked those cases explicitly without weakening their expectations.
- Real cleanup integration coverage `605e2a877cf5a899921cc00d63be10aeb1bb83d1`, server-cron operations documentation `e3f60a31806c078f7e9f33c1617979bf7d4d5d56`, and real cron lifecycle smoke `08c1e3b5fc9c36b78135b4b5a63746ca29605152` completed the Task 4 integration/operations gate.

### Task 4 verification

Exact implementation/integration SHA `08c1e3b5fc9c36b78135b4b5a63746ca29605152` / CI `33956576384`:

- `php-quality` GREEN;
- `js-quality` GREEN;
- `package` GREEN;
- `wordpress-smoke` GREEN, including cron activation/deactivation/reactivation and real MySQL terminal cleanup behavior;
- PHPUnit **480/480**, **2,022 assertions**;
- PHPStan **0 errors**;
- Composer audit **clean**;
- package artifact `9966560614`, digest `sha256:155f892e7935e2fa23cfcad536cd408447e1b3de538b2c7558981eeab8a9c4d4`.

### Task 4 independent review

Review `5120610581` covered cron schedule/delegation lifecycle, shared worker semantics, WP-CLI availability and bounded input validation, cleanup SQL/status/cutoff/order/cardinality boundaries, real integration coverage, low-traffic operations guidance, security and performance scope.

Review result: **Critical 0 / Important 0 unresolved**. Accessibility: N/A because Task 4 adds no UI. Zero unresolved inline review threads at review time.

## Security review

Tasks 1–4 preserve prepared value SQL, plugin-owned table identifiers, data-only bounded payloads, typed/allowlisted job execution, opaque lease identities, current-owner predicates for running transitions, cancellation-aware completion and constant sanitization of unexpected runtime failures. WordPress entrypoints delegate to the same typed worker instead of exposing arbitrary callables. WP-CLI is registered only when the WP-CLI environment is available. Cleanup remains site-scoped, prepared and terminal-only. No new secret persistence is introduced.

## Performance review

The jobs schema provides queue-scan, lease-recovery, idempotency and cleanup indexes. Repository candidate scans remain bounded. Task 3 bounds each worker invocation by both maximum job count and a wall-clock start budget; retry delays are deterministic and capped. Task 4 keeps CLI invocation at 1..100 started jobs, cron on the bounded default worker configuration, and cleanup at no more than 500 terminal rows per pass using SQL cutoff/status narrowing and `LIMIT`.

## Known limitations / deferrals

- M10 owns hybrid retrieval/reranking.
- M13 owns the primary admin jobs/progress UI.
- Cooperative cancellation is checkpoint-based rather than unsafe process termination.
- No external queue backend is introduced in M09.
- WP-Cron depends on WordPress traffic unless operators configure the documented WP-CLI/server-cron path.

## Exact next unfinished action

Begin **Task 5 — M07/M08 synchronization orchestration through queued jobs**. Recover the accepted M07 `IndexPlan` / M08 `IndexEmbeddingExecutor` boundaries and add a lint-clean test-only behavioral RED for a typed synchronization job payload/handler. The handler must validate only allowlisted identifiers/options, load/rebuild the intended current synchronization plan through existing services rather than persisting large source/chunk/embedding payloads, delegate execution to the completed M07/M08 boundaries, report bounded progress/cancellation through `JobExecutionContext`, classify retryable versus terminal failures without exposing secrets, and remain idempotent under lease recovery/retry. Establish genuine RED before production implementation, then run focused/full exact-SHA GREEN CI, independent Task 5 review, regression-first fixes for every Critical/Important finding, and durable evidence before whole-M09 Task 6 closeout.

## Next Milestone
M10 — Hybrid Retrieval, only after M09 is merged and fresh post-merge `main` CI is green.
