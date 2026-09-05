# M09 — Database Job Queue, Synchronization, Retries & Recovery

Status: **COMPLETE — merged and post-merge verified**

## Goal

Run large indexing/synchronization reliably outside normal page-request lifetime.

## Dependencies

M02 database foundation plus completed M07 indexing and M08 embedding/vector boundaries.

## Architecture gate

- Classification: **architectural**.
- Design/spec: `docs/superpowers/specs/2026-09-05-m09-job-queue-sync-recovery-design.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- Implementation plan: `docs/superpowers/plans/2026-09-05-m09-job-queue-sync-recovery.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- Selected architecture: one versioned per-site jobs table; typed/allowlisted job handlers; optimistic conditional leases; deterministic bounded retry/backoff; bounded workers; cooperative cancellation/progress; shared WP-Cron/WP-CLI entrypoints; terminal-only bounded cleanup; identifier-only M07/M08 synchronization orchestration.
- No Redis/external queue dependency, no `SKIP LOCKED` requirement, and no provider-level automatic retry loop.

## Acceptance criteria — COMPLETE

- Concurrent workers do not double-run leased jobs.
- Expired leases recover while stale owners cannot mutate reclaimed jobs.
- Retryable and terminal failures differ and attempts are bounded.
- Duplicate enqueue is controlled through active `(type,idempotency_key)` semantics.
- Progress is bounded/monotonic and cancellation is tested.
- WP-Cron and WP-CLI/server-cron execute the same bounded worker service.
- Low-traffic/WP-Cron-disabled operation is documented.
- Job payloads/types are validated and cannot execute arbitrary PHP callables/classes.
- Secrets are excluded from queue payload/progress/error persistence.
- Queue scans, worker loops, synchronization units, and cleanup are bounded.

## Tasks

1. **Jobs schema and immutable queue contracts — COMPLETE.** Final GREEN `62534b47e10a5b47e8770ebcbed0c31877f35623` / CI `33934918490`; review `5119113623`: Critical 0 / Important 0 unresolved.
2. **Atomic enqueue/lease/heartbeat/progress/cancellation/recovery — COMPLETE.** Review RED `0bf964f402a07f3846afa5095c578bcf04feb150`; final GREEN `a5d3203677ea1cca951391755e766657b795477f` / CI `33940511092`; review `5119484425`: Critical 0 / Important 0 unresolved.
3. **Retry/failure state machine, typed handler registry and bounded worker — COMPLETE.** REDs `429419e629021a02f10753f4dddcbe6ec8169677`, `95c648f49d3089ebf8925342a2a2e1afe004858a`, `f4ae81a364d607bd1a7326d29ed7d9d8dd41056f`; final GREEN `99b9f176a2d16a9214ed8d5d594be536f56ae06a` / CI `33950927777`; review `5120228931`: Critical 0 / Important 0 unresolved.
4. **WP-Cron, WP-CLI/server-cron execution and bounded cleanup — COMPLETE.** RED `92f748ae6f6287c0d492b63216797c85b24f9aae`; final integration `08c1e3b5fc9c36b78135b4b5a63746ca29605152` / CI `33956576384`; review `5120610581`: Critical 0 / Important 0 unresolved.
5. **M07/M08 synchronization orchestration through queued jobs — COMPLETE.** Initial RED `383888696dd8aae7598b806eb79ef9c689d2c32d`; review RED `b70f2d8f4818d7d0dcdb4fb3f20818efab318585`; fix `19d6b2f8cb3b689761e5c5ab0a36439a3c2d74db`; final implementation/review head `d2ddad95ad47563e1da319da10ce4db88bfbd6df` / CI `33960825615`; review `5120881838`: Critical 0 / Important 0 unresolved.
6. **Whole-M09 security/performance/recovery review, verification, merge and post-merge closeout — COMPLETE.** Whole review `5120888824`: Critical 0 / Important 0 unresolved; zero unresolved inline review threads.

## Delivered behavior

M09 introduces a WordPress-native durable background queue with:

- schema V5 `${prefix}rag_ai_jobs` and indexed due/recovery/idempotency/cleanup paths;
- active idempotent enqueue and deterministic queue ordering;
- optimistic one-winner lease claims, heartbeat/progress, stale-owner protection and expired-lease reclaim;
- queued/running cancellation and cancellation-aware completion;
- deterministic bounded retry/backoff and final-attempt handling;
- typed allowlisted handlers and opaque worker identities;
- bounded worker starts by count and wall-clock budget;
- shared WP-Cron and WP-CLI/server-cron execution;
- terminal-only cleanup capped at 500 rows per pass;
- strict identifier-only `index.document` jobs with deterministic generation-scoped idempotency;
- server-side synchronization reconstruction boundaries, M07 planning reuse and M08 execution reuse;
- bounded synchronization progress/heartbeat/cancellation checkpoints;
- safe retryable/terminal provider/vector classification using constant persisted messages;
- rerun behavior preserving stable M07 chunk/M08 vector identity.

## Task 5 final evidence

Exact Task 5 implementation/review head `d2ddad95ad47563e1da319da10ce4db88bfbd6df` / CI `33960825615`:

- all four permanent jobs GREEN;
- PHPUnit **492/492**, **2,058 assertions**;
- PHPStan **0 errors**;
- Composer audit clean;
- artifact `9967877267`, digest `sha256:25967d9332511c6fc4bc304e7da24ba0671c92421f5afe616175501141a06403`.

Task 5 Important review finding was fixed regression-first: normalized provider/vector exceptions previously escaped `DocumentIndexJobHandler`, which would collapse retryable outages into terminal generic failures. RED `b70f2d8f4818d7d0dcdb4fb3f20818efab318585` / CI `33960520400`; minimum fix `19d6b2f8cb3b689761e5c5ab0a36439a3c2d74db`; final review `5120881838`: Critical 0 / Important 0 unresolved.

## Whole-M09 review

Review `5120888824` rechecked state-machine, recovery, concurrency, retry, idempotency, cancellation, handler allowlisting, payload secrecy, cron/CLI, cleanup, synchronization and bounded-work invariants.

Result: **Critical 0 / Important 0 unresolved**. Accessibility: N/A because M09 adds no user-facing UI.

### Security

- Queue payloads are bounded JSON-only data and persisted job types cannot execute arbitrary callables/classes.
- SQL values remain prepared and table identifiers plugin-owned/site-scoped.
- Current live lease ownership is required for running mutations; stale owners cannot overwrite reclaimed/completed jobs.
- Unexpected runtime errors are constant-sanitized.
- Synchronization payloads contain no credentials, source/chunk bodies, embeddings, or raw provider/vector diagnostic messages.
- Provider/vector normalized failures map to stable bounded codes and constant messages.

### Recovery / concurrency

- Active idempotency suppresses duplicate active work without mutating existing payloads.
- Due/recovery scans are deterministic and bounded.
- Lease expiry makes jobs reclaimable without authorizing stale owners.
- Cancellation wins before handler lookup and is checked cooperatively during synchronization.
- Attempts/backoff are bounded and final attempts cannot retry.
- Synchronization reruns preserve stable M07/M08 identities.

### Performance

- Queue/recovery/idempotency/cleanup paths are indexed.
- Candidate scans and worker invocation are bounded by count/time.
- CLI work limit is 1..100 and cleanup is capped at 500 terminal rows.
- M07/M08 bounded planning/execution remains the synchronization mutation boundary.
- No global worker lock or external queue dependency was added.

## Final verification / merge

Final pre-merge PR head `40259aa5d23826398344017427efbd905c0d7913` / CI `33961209970`:

- `php-quality`, `js-quality`, `package`, `wordpress-smoke` GREEN;
- PHPUnit **492/492**, **2,058 assertions**;
- PHPStan **0 errors**;
- Composer audit clean;
- artifact `9967995819`, digest `sha256:68644f8d961a28b58ed7a4859563421bc6883b2fafe52d7b07cbbd8c6776bc71`.

PR #13 was marked ready only after this exact-head gate and merged with `expected_head_sha=40259aa5d23826398344017427efbd905c0d7913`.

Merge SHA: `0a4ba0d3133e41d28812d5ddb81abad8266b0c26`.

Fresh post-merge `main` CI `33961341720` on that exact SHA passed all four permanent jobs. Artifact `9968035763`, digest `sha256:de944bc71d41444cab9f4974ce4f81788536d3769b347d7391905a8c587f96d8`.

## Known limitations / deferrals

- M10 owns hybrid retrieval/reranking.
- M13 owns the primary admin jobs/progress UI.
- Cooperative cancellation is checkpoint-based rather than unsafe process termination.
- WP-Cron depends on traffic unless operators configure the documented WP-CLI/server-cron path.
- Source-specific persistent reconstruction may be registered later; unavailable reconstruction fails explicitly instead of serializing live runtime objects.

## Documentation

Detailed closeout evidence: `docs/progress/M09-CLOSEOUT.md`.

## Next milestone

**M10 — Hybrid Retrieval.** Recover its milestone and classify the architecture gate before implementation. Apply strict TDD, independent review, exact-SHA CI, durable evidence, and post-merge verification.
