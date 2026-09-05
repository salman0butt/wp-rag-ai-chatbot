# M09 Closeout — Database Job Queue, Synchronization, Retries & Recovery

Status: **COMPLETE — merged and post-merge verified on `main`**

## Architecture / plan

M09 was architectural and completed under scheduled-mode auto-approval:

- design/spec: `docs/superpowers/specs/2026-09-05-m09-job-queue-sync-recovery-design.md` — **AUTO-APPROVED — SCHEDULED MODE**;
- implementation plan: `docs/superpowers/plans/2026-09-05-m09-job-queue-sync-recovery.md` — **AUTO-APPROVED — SCHEDULED MODE**.

Selected architecture: versioned per-site jobs table, optimistic conditional leases, typed/allowlisted handlers, deterministic bounded retry/backoff, bounded workers, cooperative progress/cancellation, shared WP-Cron/WP-CLI execution, terminal-only bounded cleanup, and identifier-only M07/M08 synchronization orchestration. No Redis/external queue dependency and no `SKIP LOCKED` requirement.

## Completed tasks

1. **Jobs schema and immutable queue contracts — COMPLETE.** Final GREEN `62534b47e10a5b47e8770ebcbed0c31877f35623` / CI `33934918490`; review `5119113623`: Critical 0 / Important 0 unresolved.
2. **Atomic queue repository and recovery — COMPLETE.** Review RED `0bf964f402a07f3846afa5095c578bcf04feb150`; final GREEN `a5d3203677ea1cca951391755e766657b795477f` / CI `33940511092`; review `5119484425`: Critical 0 / Important 0 unresolved.
3. **Retry state machine, typed handlers and bounded worker — COMPLETE.** Behavioral REDs `429419e629021a02f10753f4dddcbe6ec8169677`, `95c648f49d3089ebf8925342a2a2e1afe004858a`, `f4ae81a364d607bd1a7326d29ed7d9d8dd41056f`; final GREEN `99b9f176a2d16a9214ed8d5d594be536f56ae06a` / CI `33950927777`; review `5120228931`: Critical 0 / Important 0 unresolved.
4. **WordPress cron/CLI execution and bounded cleanup — COMPLETE.** Genuine RED `92f748ae6f6287c0d492b63216797c85b24f9aae`; final integration `08c1e3b5fc9c36b78135b4b5a63746ca29605152` / CI `33956576384`; review `5120610581`: Critical 0 / Important 0 unresolved.
5. **Queued M07/M08 synchronization orchestration — COMPLETE.** Initial RED `383888696dd8aae7598b806eb79ef9c689d2c32d`; review RED `b70f2d8f4818d7d0dcdb4fb3f20818efab318585`; fix `19d6b2f8cb3b689761e5c5ab0a36439a3c2d74db`; final implementation/review head `d2ddad95ad47563e1da319da10ce4db88bfbd6df` / CI `33960825615`; review `5120881838`: Critical 0 / Important 0 unresolved.
6. **Whole-M09 closeout — COMPLETE.** Whole-milestone review `5120888824`: Critical 0 / Important 0 unresolved; zero unresolved inline review threads.

## Final verification

### Task 5 implementation gate

`d2ddad95ad47563e1da319da10ce4db88bfbd6df` / CI `33960825615`:

- `php-quality` GREEN;
- `js-quality` GREEN;
- `package` GREEN;
- `wordpress-smoke` GREEN;
- PHPUnit **492/492**, **2,058 assertions**;
- PHPStan **0 errors**;
- Composer audit clean;
- artifact `9967877267`, digest `sha256:25967d9332511c6fc4bc304e7da24ba0671c92421f5afe616175501141a06403`.

### Final pre-merge gate

Final PR #13 head `40259aa5d23826398344017427efbd905c0d7913` / CI `33961209970`:

- all four permanent jobs GREEN;
- PHPUnit **492/492**, **2,058 assertions**;
- PHPStan **0 errors**;
- Composer audit clean;
- artifact `9967995819`, digest `sha256:68644f8d961a28b58ed7a4859563421bc6883b2fafe52d7b07cbbd8c6776bc71`.

PR #13 was marked ready only after the exact-head gate passed and merged with expected-head-SHA protection.

### Merge

- PR: #13 — `feat: add M09 job queue and recovery`
- Accepted head: `40259aa5d23826398344017427efbd905c0d7913`
- Merge SHA: `0a4ba0d3133e41d28812d5ddb81abad8266b0c26`

### Fresh post-merge `main` verification

CI `33961341720` ran on exact `main` SHA `0a4ba0d3133e41d28812d5ddb81abad8266b0c26` and passed:

- `php-quality` GREEN;
- `js-quality` GREEN;
- `package` GREEN;
- `wordpress-smoke` GREEN, including activation, database queue coverage, providers, knowledge, file ingestion and WooCommerce knowledge;
- package artifact `9968035763`, digest `sha256:de944bc71d41444cab9f4974ce4f81788536d3769b347d7391905a8c587f96d8`.

## Security closeout

- Queue payloads remain bounded JSON data; typed persisted values cannot select arbitrary PHP callables/classes.
- SQL values remain prepared and table names plugin-owned/site-scoped.
- Lease owners are opaque and required for running-state mutations; stale owners cannot overwrite reclaimed/completed work.
- Unexpected runtime failures persist only constant generic diagnostics.
- `index.document` payloads contain stable identifiers only and persist no credentials, source bodies, chunks, embeddings, or raw provider/vector messages.
- Normalized provider/vector errors map to safe constant messages and bounded retry semantics.
- Cron and CLI delegate to the same typed worker; no separate untyped execution surface exists.

## Recovery / concurrency closeout

- Active idempotency suppresses duplicate active work without rewriting the existing payload.
- Due/recovery scans are deterministic and bounded.
- Conditional lease predicates produce one current owner; expiry enables reclaim while stale owners remain unauthorized.
- Cancellation wins before handler resolution and is checked cooperatively around synchronization phases.
- Attempts and backoff are bounded; final attempts cannot return to retry-wait.
- Rerunning synchronization preserves stable M07 chunk/M08 vector identity.

## Performance closeout

- Queue/recovery/idempotency/cleanup access paths are indexed.
- Candidate scans and worker loops are bounded by count/time.
- WP-CLI accepts only 1..100 work starts; cleanup deletes at most 500 terminal rows per pass.
- Synchronization reuses M07/M08 bounded planning/execution limits and adds no unbounded provider loop.
- No global worker lock or external queue dependency was introduced.

## Known limitations / deferrals

- M10 owns hybrid retrieval/reranking.
- M13 owns the primary admin jobs/progress UI.
- Cooperative cancellation is checkpoint-based rather than unsafe process termination.
- WP-Cron depends on WordPress traffic unless the documented WP-CLI/server-cron path is configured.
- Source-specific persistent reconstruction can be registered later; unavailable reconstruction fails explicitly rather than serializing runtime source/provider objects.

## Next milestone

**M10 — Hybrid Retrieval.** Recover/classify its milestone before implementation and apply the repository architecture gate, strict TDD, independent review, exact-SHA verification, and post-merge closeout process.
