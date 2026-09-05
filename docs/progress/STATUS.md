# Global Status

- Completed milestones on `main`: **M00-M08**.
- Current `main` SHA: `00d5630192a2f0977c9e88851964ad7a339598b7` (M08 post-merge closeout integrated).
- M08 post-merge `main` CI: `33930071265` — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed.
- Current milestone: **M09 — Database Job Queue, Synchronization, Retries & Recovery — IN PROGRESS**.
- Active M09 branch: `feat/m09-job-queue-sync-recovery`; draft PR #13.
- M09 Task 1 — jobs schema and immutable queue contracts: **COMPLETE**.
- M09 Task 2 — atomic enqueue/lease/heartbeat/progress/cancellation/recovery repository: **COMPLETE**.
- M09 Task 3 — retry/failure state machine, typed handler registry and bounded worker: **COMPLETE**.
- M09 Task 4 — WP-Cron, WP-CLI/server-cron execution and bounded cleanup: **COMPLETE**.
- M09 Task 5 — M07/M08 synchronization orchestration through queued jobs: **NEXT**.

## M08 final state — COMPLETE

M08 is fully integrated on `main`. Detailed evidence remains in `docs/milestones/M08-embeddings-vector-stores.md`, `docs/progress/M08-CLOSEOUT.md`, and merged PR #11.

## M09 — IN PROGRESS

Architecture/spec and implementation plan are complete and **AUTO-APPROVED — SCHEDULED MODE**:

- `docs/superpowers/specs/2026-09-05-m09-job-queue-sync-recovery-design.md` (`987e520a8c4ceea1f4c7d0e610a9d7825d1f3b1a`)
- `docs/superpowers/plans/2026-09-05-m09-job-queue-sync-recovery.md` (`32bc0aff95f0035d8e6d06c9c2f47497db34776c`)

Selected architecture: one versioned per-site jobs table, typed allowlisted handlers, optimistic conditional leases, deterministic bounded retry/backoff, bounded workers, cooperative cancellation/progress, WP-Cron + WP-CLI execution, and no Redis/external queue dependency.

### Task 1 — COMPLETE

Task 1 delivered schema V5 plus `${prefix}rag_ai_jobs`, queue/recovery/idempotency/cleanup indexes, migration/uninstall lifecycle integration, real WordPress V1→V5 upgrade/reinstall coverage, stable `JobStatus`, bounded `JobRequest`, immutable `JobRecord`, and the `JobRepository::enqueue()` boundary.

Final evidence:

- implementation GREEN `62534b47e10a5b47e8770ebcbed0c31877f35623` / CI `33934918490`;
- PHPUnit **419/419**, **1,898 assertions**; PHPStan 0 errors; Composer audit clean;
- package artifact `9959839970`, digest `sha256:8f9e663d82e3fa109b49a4f4e9dfc164764e2155e302ba7dbdca706170cf6926`;
- independent review `5119113623`: **Critical 0 / Important 0 unresolved**.

### Task 2 — COMPLETE

Task 2 delivered named-lock idempotent enqueue, bounded deterministic queue/recovery scans, optimistic conditional lease claims, current-owner heartbeat/progress/completion/retry/failure predicates, direct/cooperative cancellation, expired-lease reclaim, stale-owner rejection and real WordPress/MySQL queue coverage.

Final evidence:

- review RED `0bf964f402a07f3846afa5095c578bcf04feb150` / CI `33940320856` proved active idempotency lookup returned the oldest duplicate-active row rather than the approved newest row;
- minimum fix `a5d3203677ea1cca951391755e766657b795477f` / CI `33940511092`;
- PHPUnit **431/431**, **1,962 assertions**; PHPStan 0 errors; Composer audit clean;
- package artifact `9961657307`, digest `sha256:c93ebcef05b7bbbb88472a801db01966e627e5cdead745fd25c3a23323d6a4b8`;
- independent review `5119484425`: **Critical 0 / Important 0 unresolved**.

### Task 3 — COMPLETE

Task 3 delivered deterministic bounded retry policy, explicit typed/allowlisted handlers, bounded worker count/time/lease configuration, opaque worker identities, retryable versus terminal/final-attempt transitions, constant sanitization for unexpected failures, cancellation priority/cooperation, and lease-scoped execution-context delegation.

Final evidence:

- handler-registry RED `429419e629021a02f10753f4dddcbe6ec8169677` / CI `33945855793`;
- worker value-boundary RED `95c648f49d3089ebf8925342a2a2e1afe004858a` / CI `33946308243`;
- closeout review RED `f4ae81a364d607bd1a7326d29ed7d9d8dd41056f` / CI `33949274962` proved cancellation ran after unknown-handler resolution;
- minimum fix `99b9f176a2d16a9214ed8d5d594be536f56ae06a`;
- final Task 3 GREEN CI `33950927777`: all four permanent jobs GREEN; PHPUnit **468/468**, **2,011 assertions**; PHPStan **0 errors**; Composer audit **clean**;
- package artifact `9964796928`, digest `sha256:b7c26e874ce35ddcdb2b51eea1ee31ff0cc9f7a98cfa81a38ddbc32871380bcf`;
- independent Task 3 review `5120228931`: **Critical 0 / Important 0 unresolved**.

### Task 4 — COMPLETE

Task 4 delivered the stable `wp_rag_ai_jobs_run` WP-Cron boundary, schedule-if-absent registration, deactivation unscheduling/reactivation restoration, shared bounded worker delegation, conditional WP-CLI registration and `--limit=1..100`, terminal-only cleanup capped at 500 rows per pass, real WordPress cron lifecycle and MySQL cleanup integration coverage, and documented server-cron operation for low-traffic / `DISABLE_WP_CRON` sites.

TDD/recovery/final evidence:

- genuine behavioral RED `92f748ae6f6287c0d492b63216797c85b24f9aae` / CI `33953938875`: PHPStan **0 errors**; PHPUnit **480 tests / 2,007 assertions** with **12 missing-boundary errors + 1 intentional bootstrap failure** for the planned cron/CLI/cleanup/runner/bootstrap behavior; other permanent jobs GREEN;
- recovered static contract failure `b6d0ec6e02c51c56874c7d787a603131ae8a83b8` / CI `33954271488` showed the cleanup adapter could return boolean `true` despite promising an integer affected-row count; fixed fail-closed in `201738784662f7cddb6a99d2948cdbfbca301592`;
- expectation-only PHPUnit risky-test hygiene corrected without weakening Brain Monkey expectations in `e7f14c5dec2f4a931e67b8e40cee383dd7c77757`, `5b4e43acb4b99bcd981a672bd941fc0c3037941a`, and `244b6a180694986a7fab43db505f4f470a7de789`;
- real cleanup integration `605e2a877cf5a899921cc00d63be10aeb1bb83d1`, server-cron operations docs `e3f60a31806c078f7e9f33c1617979bf7d4d5d56`, and cron lifecycle smoke `08c1e3b5fc9c36b78135b4b5a63746ca29605152`;
- final implementation/integration CI `33956576384`: `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all GREEN; PHPUnit **480/480**, **2,022 assertions**; PHPStan **0 errors**; Composer audit **clean**;
- package artifact `9966560614`, digest `sha256:155f892e7935e2fa23cfcad536cd408447e1b3de538b2c7558981eeab8a9c4d4`;
- independent Task 4 review `5120610581`: **Critical 0 / Important 0 unresolved**; zero unresolved inline review threads.

## Remaining M09 work

- Task 5 — M07/M08 synchronization orchestration through queued jobs.
- Task 6 — whole-M09 security/performance/recovery review, verification, merge and post-merge closeout.

## Exact next unfinished action

Begin M09 **Task 5 — M07/M08 synchronization orchestration through queued jobs**. Recover the accepted M07 `IndexPlan` / M08 `IndexEmbeddingExecutor` boundaries and add a lint-clean test-only behavioral RED for a typed synchronization payload/handler. Keep persisted payloads small and identifier-only; rebuild/load current source/chunk/plan state through existing services; delegate accepted plan execution to existing M07/M08 orchestration; report bounded progress/cancellation through `JobExecutionContext`; classify retryable versus terminal errors safely; and preserve idempotency under retry/recovery. Establish genuine RED before production code, then implement the smallest integration, run focused/full exact-SHA GREEN CI, perform independent review, resolve every Critical/Important finding regression-first, and record durable Task 5 evidence before whole-M09 Task 6 closeout.
