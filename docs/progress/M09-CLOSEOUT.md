# M09 Closeout — Database Job Queue, Synchronization, Retries & Recovery

Status: **FEATURE COMPLETE — final pre-merge verification required**

## Architecture / plan

M09 was classified architectural and completed under the repository's scheduled-mode auto-approval procedure:

- design/spec: `docs/superpowers/specs/2026-09-05-m09-job-queue-sync-recovery-design.md` — **AUTO-APPROVED — SCHEDULED MODE**;
- implementation plan: `docs/superpowers/plans/2026-09-05-m09-job-queue-sync-recovery.md` — **AUTO-APPROVED — SCHEDULED MODE**.

The selected architecture is WordPress-native: one versioned per-site jobs table, optimistic conditional leases, typed/allowlisted handlers, deterministic bounded retry/backoff, bounded workers, cooperative cancellation/progress, WP-Cron and WP-CLI entrypoints over the same worker, terminal-only bounded cleanup, and identifier-only M07/M08 synchronization orchestration. No Redis/external queue dependency or `SKIP LOCKED` requirement was introduced.

## Completed scope

### Task 1 — jobs schema and immutable queue contracts

- Schema V5 and `${prefix}rag_ai_jobs` with due-work, recovery, idempotency and cleanup indexes.
- Migration, uninstall and reinstall lifecycle coverage.
- Stable queue states, bounded JSON-only requests, immutable job records and enqueue boundary.
- Runtime/executable PHP objects rejected before persistence.
- Final GREEN `62534b47e10a5b47e8770ebcbed0c31877f35623` / CI `33934918490`.
- Review `5119113623`: **Critical 0 / Important 0 unresolved**.

### Task 2 — atomic queue repository and recovery

- Named-lock active-idempotency enqueue.
- Deterministic bounded candidate scans.
- One-winner optimistic lease claim, heartbeat/progress, retry/fail/complete transitions.
- Queued/running cancellation, expired lease reclaim and stale-owner rejection.
- Real WordPress/MySQL integration.
- Review RED `0bf964f402a07f3846afa5095c578bcf04feb150` / CI `33940320856` fixed newest-active idempotency selection.
- Final GREEN `a5d3203677ea1cca951391755e766657b795477f` / CI `33940511092`.
- Review `5119484425`: **Critical 0 / Important 0 unresolved**.

### Task 3 — retry state machine, handlers and bounded worker

- Deterministic backoff capped at 900 seconds and attempts capped at 10.
- Explicit typed handler registry and safe unknown-type handling.
- Bounded job count/start budget/lease duration and cryptographically opaque worker identity.
- Retryable vs terminal/final-attempt transitions.
- Constant unexpected-Throwable persistence.
- Cancellation priority and cooperative cancellation.
- Lease-scoped heartbeat/progress/cancellation context.
- Behavioral REDs `429419e629021a02f10753f4dddcbe6ec8169677`, `95c648f49d3089ebf8925342a2a2e1afe004858a`, and closeout RED `f4ae81a364d607bd1a7326d29ed7d9d8dd41056f`.
- Final GREEN `99b9f176a2d16a9214ed8d5d594be536f56ae06a` / CI `33950927777`.
- Review `5120228931`: **Critical 0 / Important 0 unresolved**.

### Task 4 — WordPress execution and cleanup

- Stable schedule-if-absent WP-Cron hook and deactivation unscheduling.
- Shared worker semantics for cron and `wp wp-rag-ai jobs run --limit=<1..100>`.
- Terminal-only cleanup with a 500-row hard cap and deterministic ordering.
- Real WordPress cron lifecycle and real MySQL cleanup coverage.
- Documented WP-CLI/server-cron path for low-traffic / `DISABLE_WP_CRON` sites.
- Genuine RED `92f748ae6f6287c0d492b63216797c85b24f9aae` / CI `33953938875`.
- Final GREEN `08c1e3b5fc9c36b78135b4b5a63746ca29605152` / CI `33956576384`.
- Review `5120610581`: **Critical 0 / Important 0 unresolved**.

### Task 5 — queued M07/M08 synchronization orchestration

- Stable `index.document` typed job.
- Exact-shape identifier-only payload: document/source/collection/configuration/generation identities only.
- Deterministic generation-scoped SHA-256 idempotency key.
- Explicit server-side dependency reconstruction boundary; unavailable reconstruction fails closed without serializing live source/provider objects.
- M07 `DocumentIndexPipeline`/`IndexPlan` and M08 `IndexEmbeddingExecutor` reuse.
- Bounded progress, heartbeat and cancellation checkpoints.
- Safe provider/vector failure classification with constant persisted messages.
- Rerun integration proves stable M07 chunk/M08 vector identity rather than duplicate vector records.
- Initial RED `383888696dd8aae7598b806eb79ef9c689d2c32d` / CI `33959103043`.
- Review RED `b70f2d8f4818d7d0dcdb4fb3f20818efab318585` / CI `33960520400` proved normalized provider/vector exceptions escaped and would become terminal generic worker failures.
- Minimum fix `19d6b2f8cb3b689761e5c5ab0a36439a3c2d74db`; standards-only follow-up `cb491d6a42f141f6315a2522327938e2630fd874`.
- Rerun identity hardening `ec7c519162fac587b9a921ad2d228e0df0479da0` and taxonomy coverage `d2ddad95ad47563e1da319da10ce4db88bfbd6df`.
- Task 5 GREEN CI `33960825615`: all four permanent jobs passed; PHPUnit **492/492**, **2,058 assertions**; PHPStan 0; Composer audit clean; artifact `9967877267`, digest `sha256:25967d9332511c6fc4bc304e7da24ba0671c92421f5afe616175501141a06403`.
- Review `5120881838`: **Critical 0 / Important 0 unresolved**.

## Whole-M09 review

Independent whole-milestone review `5120888824` covered cross-task state-machine, recovery, concurrency, idempotency, retry, cancellation, entrypoint, synchronization, security and performance invariants.

Result: **Critical 0 / Important 0 unresolved**. Zero unresolved inline review threads at review time. Accessibility is N/A because M09 adds no user-facing UI.

### Security

- Queue payloads are bounded JSON data and handler types are allowlisted; persisted type/payload values cannot select arbitrary PHP callables/classes.
- SQL values remain prepared and table identifiers remain plugin-owned/site-scoped.
- Lease owners are opaque and required for running-state mutation.
- Stale lease owners cannot update reclaimed/completed work.
- Unexpected Throwables persist only constant generic diagnostics.
- Task 5 persists no credentials, source bodies, chunks, embeddings or raw provider/vector error messages.
- WP-Cron and WP-CLI delegate to the same typed worker instead of exposing a separate mutation surface.

### Recovery / concurrency

- Active idempotency lookup suppresses duplicate active work without mutating the existing payload.
- Due/recovery scans are deterministic and bounded.
- Conditional SQL predicates arbitrate one winning lease owner.
- Lease expiry makes work reclaimable without granting stale owners mutation rights.
- Cancellation wins before handler resolution and is checked cooperatively around synchronization phases.
- Attempts and backoff are bounded; final attempts cannot return to retry-wait.
- Rerun synchronization keeps stable M07/M08 identities.

### Performance

- Queue, recovery, idempotency and cleanup access paths are indexed.
- Candidate scans and worker loops are bounded.
- Worker starts are limited by count and wall-clock start budget.
- CLI limits are 1..100; cleanup is capped at 500 terminal rows per pass.
- Synchronization reuses M07/M08 bounded plan/execution limits and adds no unbounded provider loop.
- No global worker lock or external queue dependency is required.

## Pre-merge verification

Task 5 exact implementation/review head `d2ddad95ad47563e1da319da10ce4db88bfbd6df` / CI `33960825615` passed all permanent jobs with PHPUnit **492/492**, **2,058 assertions**, PHPStan 0 errors and clean Composer audit.

Task 5 documentation / Task 6 review head `896ad25de22a05994329d57418ce9b85012276bf` / CI `33961044907` passed `php-quality`, `js-quality`, `package`, and `wordpress-smoke`. Artifact `9967946558`, digest `sha256:4cb9c6f91c3842f02deb0cce645dc413f952a379fb3b90c4e60d4649ed139028`.

This closeout file creates a later documentation-only head. Repository policy therefore requires one final exact-head CI run after this commit before PR #13 may merge.

## Merge gate

Before merging PR #13:

1. final PR head must equal the SHA whose CI is being accepted;
2. all four permanent CI jobs must be GREEN on that exact SHA;
3. whole-M09 review must remain Critical 0 / Important 0 unresolved;
4. unresolved inline review threads must remain zero;
5. PR must be transitioned from draft only after the above are true;
6. merge must use expected-head-SHA protection;
7. fresh post-merge `main` CI must pass before M09 is considered integrated.

## Post-merge closeout

After merge and fresh `main` CI, update durable project status to **M09 COMPLETE**, record the merge SHA/post-merge run/artifact, and advance the current milestone to M10 — Hybrid Retrieval.
