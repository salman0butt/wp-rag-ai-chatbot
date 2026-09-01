# M09 — Database Job Queue, Synchronization, Retries & Recovery

Status: NOT STARTED

## Goal
Run large indexing/synchronization reliably outside normal page-request lifetime.

## Dependencies
M02 DB, M07-M08 indexing/vector behaviors.

## In Scope
DB queue; leases; attempts; retry/backoff; failure state; idempotency; progress; cancellation where practical; interrupted-job reclaim; WP-Cron; server-cron/WP-CLI worker path; sync orchestration.

## Out of Scope
Redis/external queue dependencies.

## Architecture
Persisted state machine with atomic lease acquisition and idempotent handlers.

## Acceptance Criteria
Concurrent workers do not double-run leased jobs; expired leases recover; retryable/terminal errors differ; duplicate enqueue is controlled; progress and cancellation behavior tested; low-traffic/WP-Cron-disabled path documented.

## Tasks
Pending plan.

## TDD Evidence
Pending.

## Integration Test Evidence
Database concurrency/recovery tests required.

## E2E / Visual Verification
Admin progress UI belongs mainly M13; worker diagnostics may be verified if exposed.

## Security Review
Payload validation, privileged job types, no secret leakage in payload/error logs.

## Accessibility Review where UI exists
N/A unless diagnostics UI.

## Performance Review where relevant
Worker batch sizing, lock contention, table cleanup.

## Code Review Findings
Pending.

## Fixes
Pending.

## Fresh Verification Commands
Pending.

## Fresh Verification Results
Pending.

## Commits
Pending.

## Files Changed
Pending.

## Known Limitations
Pending.

## Documentation Updated
Pending.

## Completion Checklist
All mandatory gates.

## Next Milestone
M10 — Hybrid Retrieval.
