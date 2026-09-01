# M01 — WordPress Plugin Foundation & Tooling

Status: IMPLEMENTING

## Goal
Create the minimal production plugin skeleton and reliable PHP/JS development, test, static-analysis, lint, build, WordPress integration, and CI foundations.

## Dependencies
M00 approved written spec and `docs/superpowers/plans/2026-09-01-m01-wordpress-plugin-foundation-tooling.md`.

## In Scope
Plugin bootstrap/lifecycle boundaries; Composer/PSR-4; selected PHP test/static/WPCS tooling; JS/TS build/test/lint/typecheck; WordPress test environment; initial CI; activation smoke test; coding conventions.

## Out of Scope
Product database schema, providers, RAG, admin product UI.

## Architecture
Thin WordPress entry point delegates to focused bootstrap/application components. No business logic in global plugin file. Node remains development/build tooling only.

## Acceptance Criteria
- [ ] Tooling commands documented and reproducible.
- [ ] Plugin installs and activates on WordPress 6.9 / PHP 8.2 baseline environment.
- [ ] Production PHP/TS behaviors show genuine RED-before-GREEN evidence.
- [ ] PHP unit tests pass.
- [ ] WPCS passes.
- [ ] PHPStan passes.
- [ ] JS lint/typecheck/unit tests/build pass.
- [ ] Distribution ZIP includes required runtime files and excludes dev/private files.
- [ ] CI passes on the exact candidate commit.
- [ ] Independent review has no unresolved Critical/Important findings.

## Tasks
1. PHP toolchain, entry point, and bootstrap boundary.
2. TypeScript build/test/lint foundation.
3. WordPress activation/deactivation smoke harness and distribution metadata.
4. Package guardrails and CI.
5. Independent review, fresh verification, and durable state.

## TDD Evidence
Pending Task 1 remote RED run. Due chat-runtime DNS/package-manager restrictions, feature-branch GitHub Actions is the authoritative dependency-backed runner; ADR-018 records this environment adaptation.

## Integration Test Evidence
Pending WordPress activation/bootstrap smoke run.

## E2E / Visual Verification
Not applicable unless M01 unexpectedly introduces visible UI; the plan explicitly does not.

## Security Review
Plugin bootstrap, capability boundaries, secret files, dependency audits, CI permissions, and package exclusions.

## Accessibility Review where UI exists
Not applicable; M01 introduces no UI.

## Performance Review where relevant
Bootstrap must not load/enqueue frontend assets or perform expensive work on normal requests.

## Code Review Findings
Pending.

## Fixes
Pending.

## Fresh Verification Commands
Defined in the M01 Superpowers implementation plan.

## Fresh Verification Results
Pending.

## Commits
- `2788ec1f6aac1bb1c1f913fcaf79aebe1a84db9c` — self-reviewed M01 implementation plan.

## Files Changed
Pending implementation.

## Known Limitations
The current chat container cannot install Composer/npm dependencies because external DNS is restricted and Composer is not installed. GitHub Actions will provide dependency-backed test evidence; this limitation does not change runtime architecture.

## Documentation Updated
M00 approval completion, M01 status, execution-environment rulings, and global status updated.

## Completion Checklist
All acceptance/test/review/verification/docs/commit gates must pass.

## Next Milestone
M02 — Database Schema, Migrations & Domain Repositories.
