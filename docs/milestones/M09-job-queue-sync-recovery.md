# M09 — Database Job Queue, Synchronization, Retries & Recovery

Status: **IN PROGRESS — Task 1 next**

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

1. **Task 1 — Jobs schema and immutable queue contracts.**
2. Task 2 — Atomic enqueue, lease claim, heartbeat, progress, cancellation and recovery repository.
3. Task 3 — Retry/failure state machine, typed handler registry and bounded worker.
4. Task 4 — WP-Cron, WP-CLI/server-cron execution and bounded cleanup.
5. Task 5 — M07/M08 synchronization orchestration through queued jobs.
6. Task 6 — Whole-M09 security/performance/recovery review, verification, merge and post-merge closeout.

## TDD evidence

Task 1 RED pending. No production Task 1 behavior may be committed until a lint-clean test-only SHA fails for the intended missing jobs contracts/schema.

## Security review

Design requires allowlisted job types, bounded JSON object payloads (64 KiB, depth 8), prepared SQL, opaque lease tokens, lease-token predicates on running transitions, server-side configuration/credential reconstruction, sanitized errors, and no anonymous web mutation surface.

## Performance review

Design requires due-work/recovery indexes, bounded claim candidates, at most 10 jobs/default worker invocation, a 20-second start budget, lease duration bounds 30..900 seconds, and terminal cleanup capped at 500 rows/pass.

## Known limitations / deferrals

- M10 owns hybrid retrieval/reranking.
- M13 owns the primary admin jobs/progress UI.
- Cooperative cancellation is checkpoint-based rather than unsafe process termination.
- No external queue backend is introduced in M09.

## Exact next unfinished action

Write the Task 1 test-only commit for immutable job request/state contracts plus schema V5/table lifecycle, push it without production implementation, and require GitHub Actions to reach a genuine behavioral RED. Only after that exact RED is recorded may the minimum Task 1 production contracts/migration be implemented.

## Next Milestone
M10 — Hybrid Retrieval, only after M09 is merged and fresh post-merge `main` CI is green.
