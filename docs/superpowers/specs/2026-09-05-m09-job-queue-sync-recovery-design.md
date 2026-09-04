# M09 — Database Job Queue, Synchronization, Retries & Recovery Design

Status: **AUTO-APPROVED — SCHEDULED MODE**

## Classification

Architectural. M09 introduces a new persisted execution/recovery subsystem and integration boundaries used by later admin and indexing flows.

## Goal

Run large indexing and synchronization work reliably outside a normal WordPress page request by adding a WordPress-native database queue with atomic leases, bounded workers, retry/backoff, recovery, idempotency, progress, practical cancellation, WP-Cron execution, and a WP-CLI/server-cron path.

## Scope and constraints

M09 owns:

- a dedicated per-site jobs table added through the existing versioned migration system;
- immutable job identity/type/payload contracts and persisted state transitions;
- atomic lease acquisition that prevents two workers from owning the same job concurrently;
- expired-lease recovery;
- retryable versus terminal failure handling with deterministic bounded backoff;
- duplicate-enqueue control through caller-supplied idempotency keys;
- bounded progress and practical cancellation;
- a typed/allowlisted handler registry and worker orchestration;
- WP-Cron and WP-CLI/server-cron worker entrypoints;
- synchronization handlers that reuse existing M07/M08 application services rather than duplicating indexing/vector behavior;
- cleanup/pruning of old terminal jobs with explicit bounds.

M09 does not add Redis, RabbitMQ, Kafka, Action Scheduler, a hosted queue, admin progress UI, hybrid retrieval, chat behavior, or provider-level automatic retries. M10 owns retrieval; M13 owns the primary job/status UI. WordPress 6.9+, PHP 8.2+, and the site's MySQL/MariaDB remain the runtime baseline.

## Recovered architecture

The repository already establishes:

- PHP-first modular-monolith boundaries with a `Jobs` domain;
- a versioned per-site database migration system, currently schema version 4;
- trusted plugin-owned `TableNames`, `$wpdb` prepared SQL, and no future-table precreation outside the owning milestone;
- pure M07 `IndexPlan` generation;
- bounded M08 `IndexEmbeddingExecutor` execution with provider/profile checks and no automatic retries;
- ADR-008 requiring a database-backed queue with leases, retries/backoff, idempotency, progress, recovery, WP-Cron and WP-CLI/server-cron paths;
- no mandatory external runtime infrastructure.

## Approaches considered

### A. Dedicated jobs table + typed handler registry + optimistic atomic leases — selected

Persist each job in a dedicated table and expose small domain/repository contracts. Workers choose eligible candidate IDs and atomically claim one with a conditional `UPDATE`; only a worker that changes exactly one row receives the lease. Handlers are registered by stable allowlisted job type and receive validated typed payloads.

Advantages:

- fits ADR-008 and the existing database/repository style;
- works across supported MySQL/MariaDB versions without requiring `SKIP LOCKED`;
- avoids a new runtime dependency;
- lease ownership/recovery is observable and testable;
- queue semantics remain vendor-neutral and reusable by WP-Cron/CLI;
- typed job handlers prevent arbitrary callable/class execution from persisted payloads.

Trade-off: more application code than adopting a queue library, but the milestone explicitly owns this subsystem and needs stable recovery/security semantics.

### B. Action Scheduler dependency

Advantages: mature WordPress background processing, retries and scheduling already exist.

Rejected because it introduces a new runtime dependency, weakens the product's local-first minimal-infrastructure direction, and does not align with the milestone/ADR's explicitly owned database-queue semantics and lease acceptance tests.

### C. WP-Cron events/options/transients as the queue

Advantages: small amount of code.

Rejected because cron events are triggers rather than a durable queue, options/transients are poor application-scale job storage, and this design cannot cleanly satisfy atomic leases, progress, interrupted-worker recovery, bounded failure history, or concurrent-worker tests.

## Selected architecture

### Module boundaries

`WpRagAiChatbot\Jobs` contains vendor-neutral queue domain/application logic. `WpRagAiChatbot\Database` owns the migration/table/repository adapter. WordPress hook and CLI wiring stay at integration/bootstrap boundaries.

Primary units:

- `JobStatus`: queued, running, retry_wait, succeeded, failed, cancelled.
- `JobRecord`: immutable hydrated job state.
- `JobRequest`: validated enqueue request containing stable type, bounded JSON-compatible payload, idempotency key and retry policy.
- `JobRepository`: persistence contract for enqueue/read/claim/heartbeat/progress/cancel/complete/fail/reclaim/prune.
- `WpdbJobRepository`: prepared SQL implementation.
- `JobHandler`: one typed execution boundary per registered job type.
- `JobHandlerRegistry`: rejects duplicate/unknown types and never resolves arbitrary persisted class/callable names.
- `JobWorker`: bounded claim → execute → transition orchestration.
- `RetryPolicy`: deterministic exponential backoff with explicit cap and no jitter dependency in the persisted contract.
- `JobExecutionContext`: exposes lease heartbeat, cancellation check and bounded progress updates to handlers.
- WordPress cron bootstrap and WP-CLI command: thin callers around the same worker service.

### Persistence schema

Add schema version 5 with `${prefix}rag_ai_jobs`.

Columns:

- `id bigint unsigned auto_increment primary key`
- `job_key varchar(191) not null` — stable opaque application identity.
- `type varchar(100) not null` — allowlisted handler type.
- `status varchar(20) not null`
- `idempotency_key varchar(191) null`
- `payload_json longtext not null`
- `attempts int unsigned not null default 0`
- `max_attempts int unsigned not null default 3`
- `available_at datetime not null`
- `lease_owner varchar(191) null`
- `lease_expires_at datetime null`
- `cancel_requested_at datetime null`
- `progress_current bigint unsigned null`
- `progress_total bigint unsigned null`
- `progress_message varchar(191) null` — bounded non-secret diagnostic code/text only.
- `last_error_code varchar(100) null`
- `last_error_message varchar(500) null` — sanitized only.
- `started_at datetime null`
- `completed_at datetime null`
- `created_at datetime not null`
- `updated_at datetime not null`

Indexes:

- unique `job_key`;
- queue scan `(status, available_at, id)`;
- lease recovery `(status, lease_expires_at, id)`;
- idempotency lookup `(type, idempotency_key, id)`;
- terminal cleanup `(status, completed_at, id)`.

A global unique index on `idempotency_key` is intentionally avoided because the same stable key may legitimately be reused after terminal completion when a caller explicitly creates a new generation. Duplicate suppression is application-controlled: enqueue with the same `(type, idempotency_key)` returns the newest non-terminal matching job rather than inserting a second active job. The repository verifies this under the same database connection and handles the race with an atomic advisory-lock-free insert strategy described below.

### Enqueue/idempotency concurrency

To make duplicate enqueue controlled under concurrent workers without requiring a broad transaction API, `job_key` is generated before insert and idempotent enqueue uses a deterministic MySQL/MariaDB named lock scoped to database + site prefix + hash(type,idempotency_key), reusing the same conceptual mechanism already approved for migration serialization. Inside the short lock:

1. look up the newest matching non-terminal job;
2. return it if present;
3. otherwise insert exactly one queued job;
4. release in `finally`.

Non-idempotent enqueue skips this lock and simply inserts a unique generated `job_key`.

This lock protects only enqueue deduplication; workers do not serialize globally.

### Lease acquisition

Workers use optimistic conditional claims rather than database-specific row-lock extensions:

1. fetch a small ordered candidate set where queued/retry work is due, plus running work whose lease expired;
2. for each candidate, issue one prepared conditional `UPDATE` that changes it to `running`, assigns an unguessable worker-owned lease token, sets `lease_expires_at`, increments `attempts`, and sets `started_at` if absent;
3. the first update affecting exactly one row wins; zero affected rows means another worker won or state changed;
4. re-read by `(job_key, lease_owner)` and execute only that exact lease.

Expired running jobs are claimable only after `lease_expires_at <= now`. Reclaim clears previous lease ownership as part of the successful claim. A stale worker cannot complete/fail/heartbeat a reclaimed job because every running-state mutation requires the current lease token.

### Time model

All persisted timestamps are UTC. Domain/application services receive a `Clock` abstraction so lease expiry/backoff/recovery tests are deterministic. WordPress production uses a UTC system clock. No queue correctness decision depends on PHP/WordPress local timezone.

### Retry and failure semantics

Handlers surface a normalized `JobExecutionException` with a retryability classification and safe code/message. Unknown/unexpected `Throwable` values are terminal by default and are converted to a constant sanitized public/persisted message; raw stack traces, provider bodies, credentials and payload contents are never persisted.

On retryable failure:

- if `attempts < max_attempts`, transition to `retry_wait`, clear lease, and set `available_at = now + backoff(attempts)`;
- otherwise transition to `failed`.

Selected backoff: 30 seconds × `2^(attempts-1)`, capped at 15 minutes. Attempts are bounded 1..10 in input contracts. No random jitter is required for correctness; multiple workers naturally compete through atomic claims.

Provider/vector adapters remain single-attempt; M09 decides whether an entire idempotent job unit may be retried.

### Heartbeats and bounded work

Default lease duration: 120 seconds, configurable in a bounded `WorkerConfig` (30..900 seconds). Long handlers must operate in bounded checkpoints and heartbeat before the lease approaches expiry. A heartbeat is accepted only for the current lease token and running state.

One worker invocation is bounded by both job count and wall-clock budget. Initial defaults:

- maximum 10 jobs per invocation;
- maximum 20 seconds of claim/start orchestration before refusing to start another job.

The currently running handler may finish its own bounded operation; future M13 UI execution does not depend on one HTTP request.

### Progress

Progress is optional. When present, `0 <= current <= total`, and `total > 0`. Progress updates require the current lease token. Message text is bounded to 191 characters and must be pre-sanitized/non-secret. Progress is monotonic for one attempt; a retry may retain the last safe progress snapshot but the handler is responsible for idempotent restart semantics.

### Cancellation

- queued/retry jobs can transition directly to `cancelled`;
- running jobs receive `cancel_requested_at` but retain their lease;
- handler checkpoints call `JobExecutionContext::cancellation_requested()` and abort with a dedicated non-retryable cancellation signal;
- completion uses a lease-token conditional update and refuses to mark success when cancellation was already observed/recorded at the transition boundary.

Cancellation is cooperative, not thread interruption. This satisfies “where practical” without unsafe process termination.

### Handler security model

Persisted `type` values never name PHP classes/functions. `JobHandlerRegistry` is explicitly populated by plugin bootstrap. Every handler validates/decode its own bounded payload schema before side effects.

Payload constraints:

- JSON object only;
- maximum encoded size 64 KiB;
- maximum nesting depth 8;
- scalar/string cardinalities validated by handler-specific contracts;
- no credentials/API keys/tokens may be written to job payloads;
- large document/chunk bodies should be referenced through stable repository identities rather than copied into queue rows whenever the existing persistence model permits it.

### Synchronization orchestration

M09 does not redesign M07/M08. It introduces job handlers/application orchestration that resolve current persistent source/document configuration, produce/obtain the accepted M07 plan, and call the M08 execution boundary in bounded idempotent units.

The first integration contract is intentionally narrow: a synchronization job payload carries stable source/document identity plus collection/profile/provider configuration identifiers required to reconstruct the operation from server-side repositories/configuration. It does not persist provider credentials or arbitrary executable objects.

Where an existing ingestion source is not yet persistently reconstructible, the handler fails explicitly rather than serializing a live PHP object into the queue. Later source-specific milestones/admin orchestration can register additional job handlers through the same typed registry.

### WordPress execution paths

WP-Cron:

- register one plugin-owned cron hook;
- schedule it only if absent;
- callback runs the bounded worker;
- deactivation unschedules the hook;
- cron callback requires no browser/admin request beyond WordPress cron triggering and exposes no public mutation endpoint.

WP-CLI/server cron:

- register a plugin namespace command when WP-CLI is available;
- command executes the same worker service with bounded `--limit` semantics;
- server cron documentation may use `wp wp-rag-ai jobs run --limit=10` (final command spelling fixed in implementation tests/docs);
- no special queue semantics exist only in CLI.

The low-traffic/WP-Cron-disabled path is therefore explicit: configure system cron to invoke WP-CLI on a regular cadence.

### Cleanup

Terminal rows are retained for diagnostics/recovery evidence and pruned only by a bounded explicit cleanup operation. Default retention target is 30 days; one cleanup pass deletes at most 500 terminal rows. Non-terminal jobs are never deleted by retention cleanup.

## Error/state invariants

- only current lease owner may heartbeat, update running progress, complete, retry, fail, or acknowledge cancellation;
- terminal states never transition back to running;
- attempts never exceed `max_attempts`;
- no retry occurs for an explicitly terminal or cancellation error;
- a lease expiry does not itself mark failure; it makes the job reclaimable;
- duplicate enqueue suppression never silently mutates the payload of an existing active job;
- queue ordering is deterministic: `available_at`, then `id`;
- all worker loops and cleanup operations are bounded.

## Testing strategy

### Unit tests

Cover value validation, state invariants, retry policy/backoff, handler registry, worker orchestration, lease-token rejection, cancellation semantics, bounded progress, payload size/depth and error sanitization with fake repository/clock/handlers.

### Database/WordPress integration

Add real WordPress tests for:

- V4→V5 migration and repeat idempotency;
- prepared hostile literal payload/idempotency values;
- concurrent-style double claim: two worker tokens compete and exactly one succeeds;
- expired lease reclaim while stale owner mutations fail;
- duplicate idempotent enqueue control;
- retry_wait due-time behavior;
- queued and running cancellation;
- bounded terminal cleanup;
- uninstall/reinstall table lifecycle.

These tests use the real MySQL/MariaDB-backed WordPress CI environment. We do not claim true parallel-process testing if CI executes the competing claims sequentially; correctness is proven by the same conditional SQL predicates that would arbitrate concurrent workers, and a focused concurrency fixture must show a stale candidate cannot claim after the first update.

### Integration entrypoints

Offline tests verify WP-Cron schedule/callback registration and WP-CLI command wiring invoke the same bounded worker. No normal CI job requires paid providers.

## Security review checklist

- no arbitrary handler/callable execution from persisted type/payload;
- prepared SQL for all values; table names plugin-owned;
- no secrets in payloads/progress/errors;
- bounded payloads, attempts, lease durations, worker limits and cleanup;
- lease tokens unguessable and required on all running transitions;
- stale workers cannot overwrite reclaimed/completed state;
- cron/CLI entrypoints do not expose unauthenticated web mutation endpoints;
- sync handlers reconstruct credentials/config server-side;
- sanitized Throwable handling.

## Performance review checklist

- queue candidate indexes support due-work and expired-lease scans;
- claim candidate batch is bounded;
- worker invocation count/time bounded;
- heartbeat/progress updates use indexed primary/stable identity plus lease token predicates;
- cleanup indexed and capped;
- no unbounded payload/document bodies in queue rows;
- no global worker lock.

## Planned task boundaries

1. Jobs schema/table lifecycle and persistence contracts.
2. Atomic lease claim, heartbeat, progress, cancellation and recovery repository behavior.
3. Retry/failure state machine plus typed handler registry and bounded worker.
4. WP-Cron and WP-CLI/server-cron execution paths plus cleanup.
5. M07/M08 synchronization orchestration integration.
6. Whole-M09 security/performance/recovery review, documentation, merge and post-merge verification.

Each task receives its own test-first RED/GREEN evidence and independent review before the next task is marked complete.

## Self-review

- Placeholder scan: no TODO/TBD or unresolved design choice remains.
- Scope: queue/recovery/synchronization only; M10 retrieval and M13 UI remain excluded.
- Security: handler allowlisting, payload bounds, lease ownership and error redaction are explicit.
- Compatibility: no `SKIP LOCKED`, Redis, Action Scheduler or mandatory external service; design fits WordPress 6.9+/MySQL/MariaDB.
- Testability: clock, repository, handler and worker boundaries permit deterministic unit tests; database race predicates are covered in WordPress integration.
- Migration safety: jobs table is introduced only in M09 as schema V5 and follows existing per-site/versioned migration conventions.

Selected design is therefore **AUTO-APPROVED — SCHEDULED MODE**.
