# M02 — Database Schema, Migrations & Domain Repositories

Status: NOT STARTED

## Goal
Introduce versioned database infrastructure and the minimal domain repositories needed by upcoming milestones.

## Dependencies
M01.

## In Scope
Migration runner/versioning/locks; fresh-install and upgrade tests; initial bounded tables/repositories; prepared SQL; pagination; transaction/error strategy; uninstall/data-retention foundation.

## Out of Scope
Pre-creating every final product table; vector search implementation; analytics product UI.

## Architecture
Dedicated tables for application-scale data, repositories isolating SQL, incremental migrations rather than giant option blobs.

## Acceptance Criteria
Fresh install and repeat/idempotent migration paths pass; repository queries are prepared/paginated; schema version/recovery behavior documented; no thousands-of-records option storage.

## Tasks
Pending milestone plan.

## TDD Evidence
Pending.

## Integration Test Evidence
Fresh/upgrade migration and repository integration tests required.

## E2E / Visual Verification
Not applicable unless migration/admin diagnostics UI is added.

## Security Review
SQL injection, capability boundaries for migration endpoints/commands, sensitive data handling.

## Accessibility Review where UI exists
N/A unless UI.

## Performance Review where relevant
Indexes, bounded queries, migration scale.

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
M03 — AI Providers, Credentials & Compatibility.
