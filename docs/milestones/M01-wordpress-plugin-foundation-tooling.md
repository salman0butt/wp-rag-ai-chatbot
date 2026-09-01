# M01 — WordPress Plugin Foundation & Tooling

Status: NOT STARTED

## Goal
Create the minimal production plugin skeleton and reliable PHP/JS development, test, static-analysis, lint, build, WordPress integration, and CI foundations.

## Dependencies
M00 approved written spec and M01 implementation plan.

## In Scope
Plugin bootstrap/lifecycle boundaries; Composer/PSR-4; selected PHP test/static/WPCS tooling; JS/TS build/test/lint/typecheck; WordPress test environment; initial CI; activation smoke test; coding conventions.

## Out of Scope
Product database schema, providers, RAG, admin product UI.

## Architecture
Thin WordPress entry point delegates to focused bootstrap/application components. No business logic in global plugin file.

## Acceptance Criteria
Tooling commands are documented/reproducible; plugin installs and activates in supported baseline test environment; initial test fails-before-code behaviors follow TDD; production build is clean; no secrets/dev artifacts packaged.

## Tasks
Defined by Superpowers M01 implementation plan after written-spec approval.

## TDD Evidence
Pending.

## Integration Test Evidence
Pending WordPress activation/bootstrap tests.

## E2E / Visual Verification
Only if M01 introduces visible admin output.

## Security Review
Plugin bootstrap, capability boundaries, secret files, package exclusions.

## Accessibility Review where UI exists
Not applicable unless UI is introduced.

## Performance Review where relevant
No unnecessary frontend assets/hooks on requests.

## Code Review Findings
Pending.

## Fixes
Pending.

## Fresh Verification Commands
Pending plan/tool selection.

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
All acceptance/test/review/verification/docs/commit gates must pass.

## Next Milestone
M02 — Database Schema, Migrations & Domain Repositories.
