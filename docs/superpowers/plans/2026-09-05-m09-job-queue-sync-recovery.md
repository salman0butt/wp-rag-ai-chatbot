# M09 Job Queue, Synchronization, Retries & Recovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a WordPress-native persisted job queue that executes large indexing/synchronization work safely through atomic leases, bounded workers, retries/backoff, recovery, idempotency, progress/cancellation, WP-Cron, and WP-CLI.

**Architecture:** Add schema V5 with one per-site jobs table, keep queue domain/application logic in `Jobs`, implement storage in `Database`, and expose WordPress cron/CLI only as thin composition boundaries around one bounded worker. Lease ownership is optimistic and conditional rather than based on `SKIP LOCKED`; job types are allowlisted handlers, and M07/M08 behavior is reused rather than duplicated.

**Tech Stack:** PHP 8.2+, WordPress 6.9+, MySQL/MariaDB via `$wpdb`, PHPUnit/PHPStan/WPCS, GitHub Actions WordPress integration environment.

**Spec:** `docs/superpowers/specs/2026-09-05-m09-job-queue-sync-recovery-design.md`

## Global Constraints

- Runtime remains PHP/WordPress; no Redis, RabbitMQ, Kafka, Action Scheduler, Node/Python service, or mandatory external queue.
- Persist UTC timestamps only.
- Jobs table is schema migration V5 and per-site through `$wpdb->prefix`.
- Prepared SQL for every untrusted value; table identifiers come only from `TableNames`.
- Persisted job `type` never names arbitrary classes/functions; only `JobHandlerRegistry` registrations may execute.
- Job payload JSON object maximum: 64 KiB encoded and nesting depth 8; credentials/tokens must never be persisted.
- Attempt input bound: 1..10; retry backoff: 30 seconds × `2^(attempts-1)`, capped at 900 seconds.
- Lease duration bound: 30..900 seconds; default 120 seconds.
- Worker default bound: at most 10 jobs and at most 20 seconds before starting another job.
- Cleanup: terminal jobs only, older than configured retention, maximum 500 rows per pass.
- Running-state writes require the current lease token.
- Provider/vector adapters remain single-attempt; only M09 job policy retries an idempotent job unit.
- M10 retrieval and M13 admin progress UI remain out of scope.
- Every behavior task must record real RED before production code and exact-SHA GREEN after implementation; lint/static-only failures do not count as behavioral RED.

---

### Task 1: Jobs schema and immutable queue contracts

**Files:**
- Modify: `src/Database/DatabaseSchema.php`
- Modify: `src/Database/DatabaseBootstrap.php`
- Modify: `src/Database/TableNames.php`
- Modify: `src/Database/DatabaseUninstaller.php`
- Create: `src/Database/Migrations/V005CreateJobsTable.php`
- Create: `src/Jobs/JobStatus.php`
- Create: `src/Jobs/JobRequest.php`
- Create: `src/Jobs/JobRecord.php`
- Create: `src/Jobs/JobRepository.php`
- Create: `src/Jobs/JobQueueException.php`
- Test: `tests/Unit/Jobs/JobContractsTest.php`
- Test: `tests/Unit/Database/Migrations/V005CreateJobsTableTest.php`
- Modify: `tests/Integration/Database/DatabaseMigrationsTest.php`
- Modify: `scripts/test-wordpress-database.php`

**Interfaces:**
- Consumes: `WpRagAiChatbot\Database\Migration`, `Connection`, `TableNames`, `DatabaseBootstrap`.
- Produces: `JobStatus`, `JobRequest`, `JobRecord`, and `JobRepository` used by every later task.
- `JobRequest::__construct(string $type, array $payload, ?string $idempotencyKey = null, int $maxAttempts = 3)` validates type/key/payload/attempt bounds before persistence.
- `JobRepository::enqueue(JobRequest $request, DateTimeImmutable $now): JobRecord` becomes the enqueue boundary; later tasks add operational methods to this contract.

- [ ] **Step 1: Write failing contract tests**

Add tests that require production symbols and validate bounds before any database work:

```php
public function test_request_rejects_payload_larger_than_64_kib(): void {
    $this->expectException(JobQueueException::class);
    new JobRequest('index_document', array('text' => str_repeat('x', 65537)));
}

public function test_request_rejects_excessive_nested_payload(): void {
    $payload = array('a' => array('b' => array('c' => array('d' => array('e' => array('f' => array('g' => array('h' => array('i' => true)))))))));
    $this->expectException(JobQueueException::class);
    new JobRequest('index_document', $payload);
}

public function test_attempt_bound_is_one_through_ten(): void {
    $this->expectException(JobQueueException::class);
    new JobRequest('index_document', array('document_id' => 10), null, 11);
}
```

Add migration tests asserting V5/table name/table lifecycle does not yet exist.

- [ ] **Step 2: Push test-only commit and verify genuine RED**

Run through authoritative CI after pushing the test-only branch head.

Expected behavioral RED: missing `Jobs` contracts and/or V005 migration/table helpers. PHPCS/PHPStan must reach behavioral execution; fixture-format failures are corrected in test-only commits and are not counted as RED.

- [ ] **Step 3: Implement immutable contracts and schema V5**

Create `JobStatus` as a backed string enum:

```php
enum JobStatus: string {
    case QUEUED = 'queued';
    case RUNNING = 'running';
    case RETRY_WAIT = 'retry_wait';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
```

`JobRequest` must JSON-encode deterministically enough to enforce size, reject non-object/list-root payloads, reject non-JSON values, cap nesting at 8, validate stable type grammar `[a-z0-9][a-z0-9_.-]{0,99}`, idempotency key <=191 characters, and max attempts 1..10.

Create V005 with the exact schema/indexes defined in the spec, increment `DatabaseSchema::VERSION` to 5, register V005 after V004, add `TableNames::jobs()`, add jobs to safe uninstall deletion order, and preserve existing migrations unchanged.

- [ ] **Step 4: Verify focused GREEN and real WordPress migration lifecycle**

Expected: contract/migration tests pass; V4→V5, repeat migration, uninstall delete/reinstall tests prove jobs table lifecycle.

- [ ] **Step 5: Run permanent CI and independent Task 1 review**

Require `php-quality`, `js-quality`, `package`, `wordpress-smoke` green. Review migration compatibility, JSON bounds, uninstall ordering, schema indexes and scope leakage. Fix every Critical/Important finding with a regression test first.

- [ ] **Step 6: Commit durable Task 1 evidence**

Update `docs/milestones/M09-job-queue-sync-recovery.md` and `docs/progress/STATUS.md` with exact RED/GREEN/review evidence.

---

### Task 2: Atomic enqueue, lease claim, heartbeat, progress, cancellation and recovery repository

**Files:**
- Modify: `src/Jobs/JobRepository.php`
- Create: `src/Jobs/JobLease.php`
- Create: `src/Jobs/JobProgress.php`
- Create: `src/Jobs/Clock.php`
- Create: `src/Jobs/SystemUtcClock.php`
- Create: `src/Jobs/WorkerConfig.php`
- Create: `src/Database/Repository/WpdbJobRepository.php`
- Create: `src/Database/JobIdempotencyLock.php`
- Create: `src/Database/WpdbNamedJobIdempotencyLock.php`
- Test: `tests/Unit/Jobs/JobLeaseAndProgressTest.php`
- Test: `tests/Unit/Database/Repository/WpdbJobRepositoryTest.php`
- Modify: `scripts/test-wordpress-database.php`

**Interfaces:**
- `JobRepository::claimNext(string $workerToken, DateTimeImmutable $now, int $leaseSeconds): ?JobLease`
- `JobRepository::heartbeat(JobLease $lease, DateTimeImmutable $now, int $leaseSeconds): JobLease`
- `JobRepository::updateProgress(JobLease $lease, JobProgress $progress, DateTimeImmutable $now): void`
- `JobRepository::cancellationRequested(JobLease $lease): bool`
- `JobRepository::requestCancellation(string $jobKey, DateTimeImmutable $now): JobRecord`
- `JobRepository::complete(JobLease $lease, DateTimeImmutable $now): void`
- `JobRepository::markRetry(JobLease $lease, string $code, string $message, DateTimeImmutable $availableAt, DateTimeImmutable $now): void`
- `JobRepository::markFailed(JobLease $lease, string $code, string $message, DateTimeImmutable $now): void`

- [ ] **Step 1: Write failing lease/concurrency tests**

Required scenarios:

```php
public function test_second_worker_cannot_claim_same_candidate(): void {
    $job = $repo->enqueue($request, $now);
    $first = $repo->claimNext('worker-a-token', $now, 120);
    self::assertSame($job->jobKey, $first?->job->jobKey);
    self::assertNull($repo->claimNext('worker-b-token', $now, 120));
}

public function test_expired_lease_is_reclaimed_and_stale_owner_cannot_complete(): void {
    $old = $repo->claimNext('old-token', $now, 30);
    $new = $repo->claimNext('new-token', $now->modify('+31 seconds'), 30);
    self::assertNotNull($new);
    $this->expectException(JobQueueException::class);
    $repo->complete($old, $now->modify('+32 seconds'));
}
```

Also add idempotent duplicate enqueue, monotonic progress, queued cancellation, running cancellation request, terminal immutability, lease heartbeat, due `retry_wait`, hostile literal values, and deterministic queue ordering tests.

- [ ] **Step 2: Push test-only commit and verify genuine RED**

Expected RED comes from absent repository behavior, not fixture lint.

- [ ] **Step 3: Implement prepared repository and short idempotency lock**

Use a bounded candidate query ordered by `available_at, id`. Claim with a prepared conditional update and require `affected_rows === 1`. Never globally lock workers.

For idempotent enqueue, use a named lock identity derived from database/site prefix and SHA-256 of type + idempotency key. Hold only around lookup+insert and release in `finally`. Return an existing non-terminal matching job without replacing its payload.

All running transitions include `status='running'` and current `lease_owner` in the predicate. Fail closed when a stale lease changes zero rows.

- [ ] **Step 4: Verify focused and WordPress integration GREEN**

The real DB smoke must exercise sequential competing claims that model the atomic race, expired reclaim, stale-owner rejection, idempotent enqueue, prepared hostile literals and cancellation.

- [ ] **Step 5: Permanent CI + independent Task 2 review**

Review atomicity predicates, named-lock release, due-work indexes, stale-worker safety and cancellation race boundaries. Fix Critical/Important findings regression-first.

- [ ] **Step 6: Record Task 2 evidence**

Update durable milestone/status docs.

---

### Task 3: Retry state machine, typed handlers and bounded worker

**Files:**
- Create: `src/Jobs/RetryPolicy.php`
- Create: `src/Jobs/JobExecutionException.php`
- Create: `src/Jobs/JobCancelledException.php`
- Create: `src/Jobs/JobHandler.php`
- Create: `src/Jobs/JobHandlerRegistry.php`
- Create: `src/Jobs/JobExecutionContext.php`
- Create: `src/Jobs/JobWorker.php`
- Test: `tests/Unit/Jobs/RetryPolicyTest.php`
- Test: `tests/Unit/Jobs/JobHandlerRegistryTest.php`
- Test: `tests/Unit/Jobs/JobWorkerTest.php`

**Interfaces:**

```php
interface JobHandler {
    public function type(): string;
    public function handle(JobRecord $job, JobExecutionContext $context): void;
}
```

`JobWorker::run(WorkerConfig $config): JobWorkerResult` repeatedly claims jobs until `maxJobs` is reached or the start-budget clock has elapsed.

- [ ] **Step 1: Write failing worker behavior tests**

Cover:

- 30/60/120/... second deterministic backoff capped at 900;
- duplicate handler registration rejected;
- unknown persisted type fails terminally without arbitrary class execution;
- retryable handler failure becomes `retry_wait` while attempts remain;
- retryable failure on final attempt becomes `failed`;
- terminal failure never retries;
- unexpected `Throwable` persists only constant sanitized diagnostics;
- cancellation signal ends `cancelled`;
- worker respects `maxJobs` and does not start a new job after its wall-clock start budget;
- heartbeat/progress/cancellation context delegates only through current lease.

- [ ] **Step 2: Verify behavioral RED**

Push tests first and record exact failing SHA/run.

- [ ] **Step 3: Implement minimal retry/registry/worker behavior**

`RetryPolicy::delaySeconds(int $attempt): int` returns `min(900, 30 * (2 ** max(0, $attempt - 1)))` within the validated attempt range.

Worker control flow:

```php
while ($started < $config->maxJobs && !$config->startBudgetExceeded($clock)) {
    $lease = $repository->claimNext($workerToken, $clock->now(), $config->leaseSeconds);
    if (null === $lease) {
        break;
    }
    $handler = $registry->forType($lease->job->type);
    try {
        $handler->handle($lease->job, new JobExecutionContext(...));
        $repository->complete($lease, $clock->now());
    } catch (JobCancelledException) {
        $repository->markCancelled($lease, $clock->now());
    } catch (JobExecutionException $error) {
        // retry only when error is retryable and attempts remain.
    } catch (Throwable) {
        // terminal constant sanitized error; no raw Throwable message persisted.
    }
    ++$started;
}
```

Use `random_bytes()`/hex for worker/lease identity; never expose a guessable lease owner from request data.

- [ ] **Step 4: Focused GREEN, full CI, independent review**

Review attempt accounting, lease-loss behavior during handler return, retry classification, error redaction and worker bounds. Any finding receives RED before fix.

- [ ] **Step 5: Record Task 3 evidence**

Update durable docs with exact SHA/run and review result.

---

### Task 4: WP-Cron, WP-CLI/server-cron and bounded cleanup

**Files:**
- Create: `src/Jobs/JobWorkerBootstrap.php`
- Create: `src/Jobs/JobCleanup.php`
- Create: `src/Jobs/WordPressJobCron.php`
- Create: `src/Jobs/WordPressCliJobsCommand.php`
- Modify: plugin bootstrap file that currently registers lifecycle hooks (`wp-rag-ai-chatbot.php` or existing `src/Core` bootstrap resolved during implementation)
- Modify: deactivation lifecycle registration to unschedule the plugin cron hook
- Test: `tests/Unit/Jobs/WordPressJobCronTest.php`
- Test: `tests/Unit/Jobs/WordPressCliJobsCommandTest.php`
- Test: `tests/Unit/Jobs/JobCleanupTest.php`
- Create/Modify: `scripts/test-wordpress-jobs.php`
- Modify: `package.json`
- Modify: `.github/workflows/ci.yml` only if a permanent WordPress jobs smoke command must be wired separately; prefer extending the existing database/integration smoke when sufficient.
- Modify: `README.md`

**Interfaces:**
- Cron hook stable ID: `wp_rag_ai_jobs_run`.
- CLI command: `wp wp-rag-ai jobs run --limit=<1..100>`.
- `JobCleanup::prune(DateTimeImmutable $before, int $limit = 500): int` deletes terminal rows only, maximum 500.

- [ ] **Step 1: Write failing integration-boundary tests**

Require schedule-if-absent, callback delegation, deactivation unschedule, CLI availability guard, bounded limit validation, same worker service for cron/CLI, and cleanup terminal-only behavior.

- [ ] **Step 2: Establish behavioral RED**

No production cron/CLI/cleanup code before the tests fail for missing behavior.

- [ ] **Step 3: Implement thin WordPress entrypoints**

Cron and CLI must compose/call the same application worker. No separate retry/claim behavior in either entrypoint. Cron hook accepts no request-controlled payload. CLI validates `--limit` 1..100 and returns a concise count/status without dumping payloads/errors/secrets.

Document the low-traffic/WP-Cron-disabled path with an example server cron invoking:

```text
wp wp-rag-ai jobs run --limit=10
```

- [ ] **Step 4: Verify WordPress integration, package, security and cleanup bounds**

Require permanent CI green and verify packaged runtime contains cron/CLI classes but no development fixtures.

- [ ] **Step 5: Independent review and durable Task 4 evidence**

Review hook lifecycle, command exposure, no anonymous REST surface, output redaction, cleanup predicate and limits.

---

### Task 5: M07/M08 synchronization orchestration through queued jobs

**Files:**
- Create: `src/Jobs/Sync/DocumentIndexJobPayload.php`
- Create: `src/Jobs/Sync/DocumentIndexJobHandler.php`
- Create: `src/Jobs/Sync/DocumentIndexJobEnqueuer.php`
- Create: `src/Jobs/Sync/DocumentIndexDependencies.php` or a smaller repository-consistent resolver contract if dependency reconstruction requires it
- Modify: composition/bootstrap registration to register the stable handler type
- Test: `tests/Unit/Jobs/Sync/DocumentIndexJobPayloadTest.php`
- Test: `tests/Unit/Jobs/Sync/DocumentIndexJobHandlerTest.php`
- Test: `tests/Unit/Jobs/Sync/DocumentIndexJobEnqueuerTest.php`
- Modify/Create: WordPress integration fixture for one persisted document/source → M07 plan → M08 execution path using fake/offline provider/vector dependencies.

**Interfaces:**
- Stable handler type: `index.document`.
- Payload contains stable server-side identifiers only: document/source identity plus selected collection/config identifiers needed to reconstruct execution; no provider secret, raw callable, serialized object, or arbitrary source content.
- Enqueuer derives an idempotency key from the operation generation/version so repeated scheduling of the same active generation resolves to one active job.

- [ ] **Step 1: Write failing payload/enqueue/handler tests**

Cover:

- secret-shaped/unexpected payload fields rejected;
- missing persistent document/source/config fails before paid provider execution;
- duplicate enqueue for same generation returns one active job;
- handler calls existing M07 pipeline and M08 `IndexEmbeddingExecutor` rather than implementing embedding/vector logic itself;
- retryable normalized provider/store failure is surfaced as retryable job failure without an internal adapter retry;
- terminal validation/configuration failure is non-retryable;
- cancellation is checked between bounded phases;
- successful rerun after interrupted lease remains idempotent through stable M07/M08 identities.

- [ ] **Step 2: Establish exact behavioral RED**

Use offline/fake provider/vector dependencies; normal CI must consume no paid API credits.

- [ ] **Step 3: Implement minimum synchronization handler**

Resolve all sensitive/config/runtime dependencies server-side. Build/obtain the M07 `IndexPlan`, execute through M08, update progress at coarse bounded phases, and translate only known normalized retryable errors into `JobExecutionException::retryable(...)`.

If a source cannot be reconstructed from currently persisted contracts, return a clear terminal error instead of serializing a live object into the queue. Do not broaden M09 into new source persistence.

- [ ] **Step 4: Full GREEN and independent integration review**

Review idempotency generation, provider-cost preflight, secret boundaries, cancellation checkpoints, failure classification, and scope leakage into M10/M13.

- [ ] **Step 5: Record Task 5 evidence**

Update milestone/status/test/security ledgers.

---

### Task 6: Whole-M09 review, verification, PR/merge and post-merge closeout

**Files:**
- Modify: `docs/milestones/M09-job-queue-sync-recovery.md`
- Modify: `docs/progress/STATUS.md`
- Modify: `docs/progress/TEST-MATRIX.md`
- Modify: `docs/progress/SECURITY.md`
- Modify: `docs/progress/KNOWN-ISSUES.md` and/or `docs/progress/TECH-DEBT.md` only for evidence that actually exists
- Modify: `docs/DECISIONS.md` only if implementation materially refines ADR-008 beyond this approved design
- Create: `docs/progress/M09-CLOSEOUT.md` only if the established repository closeout pattern still warrants a dedicated evidence file

**Interfaces:**
- Produces a fully integrated M09 milestone on `main` with queue/recovery semantics available to M10+.

- [ ] **Step 1: Fresh whole-M09 independent review**

Review database migration/upgrades, concurrent claims, lease expiry/reclaim, stale-owner writes, idempotent enqueue, retry exhaustion, error redaction, progress/cancellation, cron/CLI lifecycle, cleanup, synchronization integration, performance bounds, uninstall and package contents.

- [ ] **Step 2: Regression-test every Critical/Important finding before fixing**

No finding is closed through prose alone. Record exact RED/GREEN SHA/run.

- [ ] **Step 3: Run final exact-head permanent CI**

Require:

```text
php-quality      success
js-quality       success
package          success
wordpress-smoke  success
```

The final PHP run must have PHPStan 0 errors, full PHPUnit green, and Composer audit clean. WordPress smoke must cover the V5 jobs table and job integration behavior.

- [ ] **Step 4: Verify artifact/package and durable evidence**

Record artifact ID/digest, final review outcome, no unresolved Critical/Important findings, and no unresolved blocking PR threads.

- [ ] **Step 5: Finish feature branch and PR**

Use exact expected-head protection. Merge only when the final PR SHA is the exact verified SHA and all gates are green.

- [ ] **Step 6: Verify fresh push-triggered `main` CI**

Do not mark M09 complete until fresh default-branch CI is green on the merge SHA.

- [ ] **Step 7: Reconcile post-merge durable state and hand off to M10**

Mark M09 complete only with real merge/post-merge evidence. Exact next milestone: M10 — Hybrid Retrieval & Reranking.

## Plan self-review

- Spec coverage: schema, idempotency, claims, recovery, retries, progress, cancellation, handlers, bounded workers, cron, CLI, cleanup, synchronization integration, security and performance each map to a task.
- Placeholder scan: no TODO/TBD/“similar to” placeholder instructions remain.
- Type consistency: `JobRepository`, `JobLease`, `JobExecutionContext`, `JobHandler`, `JobWorker`, and retry interfaces are defined before consumers.
- TDD sequencing: every behavior task starts with a test-only RED and reserves review findings for regression-first fixes.
- Milestone boundaries: no M10 retrieval or M13 UI work is introduced.
- CI strategy: GitHub Actions remains authoritative for dependency-backed WordPress verification under ADR-018.

Plan is **AUTO-APPROVED — SCHEDULED MODE** and ready for Task 1 execution.
