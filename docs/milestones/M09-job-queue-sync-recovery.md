# M09 — Database Job Queue, Synchronization, Retries & Recovery

Status: **IN PROGRESS — Tasks 1-5 complete; Task 6 next**

## Goal

Run large indexing/synchronization reliably outside normal page-request lifetime.

## Dependencies

M02 database foundation plus completed M07 indexing and M08 embedding/vector boundaries.

## Architecture gate

- Classification: **architectural**.
- Design/spec: `docs/superpowers/specs/2026-09-05-m09-job-queue-sync-recovery-design.md` (`987e520a8c4ceea1f4c7d0e610a9d7825d1f3b1a`) — **AUTO-APPROVED — SCHEDULED MODE**.
- Implementation plan: `docs/superpowers/plans/2026-09-05-m09-job-queue-sync-recovery.md` (`32bc0aff95f0035d8e6d06c9c2f47497db34776c`) — **AUTO-APPROVED — SCHEDULED MODE**.
- Selected architecture: one versioned per-site jobs table; typed/allowlisted job handlers; optimistic conditional leases; deterministic bounded retry/backoff; bounded workers; cooperative cancellation/progress; thin WP-Cron and WP-CLI entrypoints; narrow M07/M08 orchestration reuse.
- No Redis/external queue dependency, no `SKIP LOCKED` requirement, and no provider-level automatic retry loop.

## Acceptance criteria

- Concurrent workers do not double-run leased jobs.
- Expired leases recover while stale owners cannot mutate reclaimed jobs.
- Retryable and terminal failures differ and attempts are bounded.
- Duplicate enqueue is controlled through active `(type,idempotency_key)` semantics.
- Progress is bounded/monotonic and cancellation is tested.
- WP-Cron and WP-CLI/server-cron execute the same bounded worker service.
- Low-traffic/WP-Cron-disabled operation is documented.
- Job payloads/types are validated and cannot execute arbitrary PHP callables/classes.
- Secrets are excluded from payload/progress/error persistence.
- Queue scans, worker loops, synchronization units, and cleanup are bounded.

## Planned tasks

1. **Task 1 — Jobs schema and immutable queue contracts — COMPLETE.**
2. **Task 2 — Atomic enqueue, lease claim, heartbeat, progress, cancellation and recovery repository — COMPLETE.**
3. **Task 3 — Retry/failure state machine, typed handler registry and bounded worker — COMPLETE.**
4. **Task 4 — WP-Cron, WP-CLI/server-cron execution and bounded cleanup — COMPLETE.**
5. **Task 5 — M07/M08 synchronization orchestration through queued jobs — COMPLETE.**
6. **Task 6 — Whole-M09 security/performance/recovery review, verification, merge and post-merge closeout — NEXT.**

## Task 1 — Jobs schema and immutable queue contracts — COMPLETE

Delivered schema V5 and `${prefix}rag_ai_jobs`, queue/recovery/idempotency/cleanup indexes, migration/uninstall lifecycle coverage, stable queue value contracts, bounded data-only payloads, immutable hydrated jobs, and the repository enqueue boundary.

Final evidence: `62534b47e10a5b47e8770ebcbed0c31877f35623` / CI `33934918490`; PHPUnit **419/419**, **1,898 assertions**; PHPStan 0; Composer audit clean; artifact `9959839970`, digest `sha256:8f9e663d82e3fa109b49a4f4e9dfc164764e2155e302ba7dbdca706170cf6926`; review `5119113623`: **Critical 0 / Important 0 unresolved**.

## Task 2 — Atomic queue repository behavior — COMPLETE

Delivered named-lock idempotent enqueue, deterministic bounded candidate scans, one-winner optimistic lease claims, current-owner transition predicates, heartbeat/progress, cancellation, expired-lease reclaim, stale-owner rejection, retry-wait due behavior, and real WordPress/MySQL coverage.

Review RED `0bf964f402a07f3846afa5095c578bcf04feb150` / CI `33940320856` caught oldest-active idempotency selection; final GREEN `a5d3203677ea1cca951391755e766657b795477f` / CI `33940511092`; PHPUnit **431/431**, **1,962 assertions**; artifact `9961657307`; review `5119484425`: **Critical 0 / Important 0 unresolved**.

## Task 3 — Retry state machine, typed handlers and bounded worker — COMPLETE

Delivered deterministic bounded backoff, explicit typed/allowlisted handlers, duplicate/unknown-type protection, bounded worker count/start-time/lease configuration, opaque worker identity, retryable/terminal/final-attempt transitions, constant unexpected-error sanitization, cancellation priority/cooperation, and lease-scoped heartbeat/progress/cancellation delegation.

Behavioral REDs: `429419e629021a02f10753f4dddcbe6ec8169677` / CI `33945855793`, `95c648f49d3089ebf8925342a2a2e1afe004858a` / CI `33946308243`, and closeout RED `f4ae81a364d607bd1a7326d29ed7d9d8dd41056f` / CI `33949274962`. Final GREEN `99b9f176a2d16a9214ed8d5d594be536f56ae06a` / CI `33950927777`; PHPUnit **468/468**, **2,011 assertions**; artifact `9964796928`; review `5120228931`: **Critical 0 / Important 0 unresolved**.

## Task 4 — WordPress execution boundaries and bounded cleanup — COMPLETE

Delivered stable schedule-if-absent `wp_rag_ai_jobs_run`, deactivation unscheduling/reactivation restoration, shared bounded worker execution, conditional WP-CLI registration with `--limit=1..100`, terminal-history cleanup capped at 500 rows, real cron lifecycle/MySQL cleanup integration, and documented server-cron operation for low-traffic or `DISABLE_WP_CRON` sites.

Behavioral RED `92f748ae6f6287c0d492b63216797c85b24f9aae` / CI `33953938875`; final integration `08c1e3b5fc9c36b78135b4b5a63746ca29605152` / CI `33956576384`; PHPUnit **480/480**, **2,022 assertions**; artifact `9966560614`, digest `sha256:155f892e7935e2fa23cfcad536cd408447e1b3de538b2c7558981eeab8a9c4d4`; review `5120610581`: **Critical 0 / Important 0 unresolved**.

## Task 5 — M07/M08 synchronization orchestration — COMPLETE

Delivered:

- stable typed job `index.document`;
- strict exact-shape identifier-only payload carrying `document_key`, `source_id`, `collection_id`, `configuration_id`, and `generation` only;
- deterministic generation-scoped SHA-256 idempotency identity;
- explicit server-side `DocumentIndexDependencies` reconstruction/execution boundary and fail-closed unavailable implementation rather than serialized PHP source/provider objects;
- allowlisted handler registration through the existing M09 registry;
- M07 `DocumentIndexPipeline` / `IndexPlan` planning reuse and M08 `IndexEmbeddingExecutor` execution reuse;
- bounded progress checkpoints `0/2 -> 1/2 -> 2/2`, heartbeat before mutation, and cooperative cancellation before planning and execution;
- normalized provider/vector failure translation to safe `JobExecutionException` values so only rate-limit/timeout/transport/upstream-provider and unavailable/operation-failed vector categories retry;
- constant persisted failure messages that do not contain raw provider/vector diagnostics;
- real offline M07-to-M08 integration proving rerunning the same synchronization keeps the stable M07 chunk key/M08 vector identity rather than creating duplicate vector records.

### Task 5 TDD evidence

- Initial genuine RED `383888696dd8aae7598b806eb79ef9c689d2c32d` / CI `33959103043`: PHPStan 0 errors; PHPUnit reached **483 tests / 2,024 assertions** with the intended missing synchronization payload behavior.
- Review genuine RED `b70f2d8f4818d7d0dcdb4fb3f20818efab318585` / CI `33960520400`: PHPStan 0 errors; PHPUnit reached **490 tests / 2,040 assertions** with exactly **2 intended errors**, proving normalized provider/vector exceptions escaped the handler and would be terminally collapsed by the worker.
- Minimum behavior fix `19d6b2f8cb3b689761e5c5ab0a36439a3c2d74db`.
- Standards-only follow-up `cb491d6a42f141f6315a2522327938e2630fd874`; the prior PHPCS-only failure is not counted as behavioral RED.
- Rerun identity integration `ec7c519162fac587b9a921ad2d228e0df0479da0`.
- Symmetric provider/vector retryable+terminal coverage `d2ddad95ad47563e1da319da10ce4db88bfbd6df`.

### Task 5 verification

Exact Task 5 implementation/review head `d2ddad95ad47563e1da319da10ce4db88bfbd6df` / CI `33960825615`:

- `php-quality` GREEN;
- `js-quality` GREEN;
- `package` GREEN;
- `wordpress-smoke` GREEN, including activation, database queue coverage, providers, knowledge, file ingestion, and WooCommerce knowledge;
- PHPUnit **492/492**, **2,058 assertions**;
- PHPStan **0 errors**;
- Composer audit **clean**;
- package artifact `9967877267`, digest `sha256:25967d9332511c6fc4bc304e7da24ba0671c92421f5afe616175501141a06403`.

### Task 5 independent review

Review `5120881838` covered payload secrecy/bounds, deterministic enqueue identity, server-side reconstruction/fail-closed unavailable state, allowlisted registration, M07/M08 delegation, progress/heartbeat/cancellation, retry/recovery identity, normalized provider/vector failure handling, integration rerun behavior, and scope boundaries.

Finding fixed regression-first:

1. **Important:** normalized provider/vector exceptions escaped `DocumentIndexJobHandler`; retryable dependency outages therefore became terminal generic worker failures. RED `b70f2d8f4818d7d0dcdb4fb3f20818efab318585` / CI `33960520400`; fixed by `19d6b2f8cb3b689761e5c5ab0a36439a3c2d74db`, then lint-standardized without behavioral change in `cb491d6a42f141f6315a2522327938e2630fd874`.

Task 5 final review result: **Critical 0 / Important 0 unresolved**. Zero unresolved inline review threads. Accessibility: N/A because Task 5 adds no UI.

## M09 security state before Task 6

Tasks 1-5 preserve prepared value SQL and plugin-owned table identifiers, bounded JSON-only payloads, explicit handler allowlisting, opaque lease identities, current-owner predicates on running transitions, cancellation-aware completion, constant generic handling for unexpected runtime failures, and constant sanitized messages for normalized provider/vector dependency failures. No credential, source body, chunk body, embedding vector, callable, or arbitrary class is persisted in Task 5 queue payloads.

## M09 performance state before Task 6

Queue candidate/recovery/idempotency/cleanup paths are indexed and bounded. Worker starts are bounded by count and wall-clock budget; retry delay is deterministic and capped. WP-CLI limits started work to 1..100 per invocation; cleanup deletes at most 500 terminal rows. Synchronization delegates to M07/M08's existing bounded planning/execution limits and adds no unbounded provider/vector loop.

## Known limitations / deferrals

- M10 owns hybrid retrieval/reranking.
- M13 owns the primary admin jobs/progress UI.
- Cooperative cancellation is checkpoint-based rather than unsafe process termination.
- No external queue backend is introduced in M09.
- WP-Cron depends on WordPress traffic unless operators configure the documented WP-CLI/server-cron path.
- Source-specific persistent reconstruction may be registered later; when unavailable now, Task 5 fails explicitly rather than serializing runtime source/provider objects into the durable queue.

## Exact next unfinished action

Perform **Task 6 — whole-M09 closeout**. Re-review the complete M09 delta for security, recovery, state-machine, concurrency, retry, idempotency, bounded-work, cleanup, WordPress-entrypoint, and synchronization invariants. Fix every Critical/Important finding regression-first. Persist whole-milestone closeout evidence, require exact-final-head CI green, transition PR #13 from draft only after all gates are satisfied, merge with expected-head-SHA protection, verify fresh post-merge `main` CI, and persist final M09 completion before beginning M10.

## Next milestone

M10 — Hybrid Retrieval, only after M09 is merged and fresh post-merge `main` CI is green.
