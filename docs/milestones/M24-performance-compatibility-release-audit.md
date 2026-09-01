# M24 — Performance, Compatibility Matrix, Upgrade Testing, Release Packaging & Final Audit

Status: NOT STARTED

## Goal
Prove the complete plugin meets approved requirements and can be safely packaged/released across the supported environment matrix.

## Dependencies
M00-M23 complete.

## In Scope
Performance profiling/benchmarks; WordPress/PHP/Woo/database/browser compatibility; upgrade paths; activation/deactivation/uninstall; multisite decision verification; full test/static/lint/build/E2E/a11y/security suites; OpenAI/OpenRouter/live optional paths; release ZIP; documentation audit; final whole-branch independent review.

## Out of Scope
New product features unless required to fix release-blocking specification gaps.

## Architecture
Release is evidence-driven; compatibility/scale limits are documented honestly and package contents are deterministic.

## Acceptance Criteria
Every item in docs/progress/RELEASE-CHECKLIST.md is evidenced; no unresolved Critical/Important findings; release ZIP installs/activates and required core scenarios work; approved requirement-to-implementation mapping complete.

## Tasks
Pending final audit plan.

## TDD Evidence
All regression suites green; new fixes use TDD.

## Integration Test Evidence
Full supported integration matrix.

## E2E / Visual Verification
Admin/widget/commerce/support critical paths desktop/mobile plus required themes/browser matrix.

## Security Review
Final audit and secrets/package scan.

## Accessibility Review where UI exists
Final WCAG-oriented verification of critical UI.

## Performance Review where relevant
Primary milestone deliverable: indexing/retrieval/chat/admin/widget/data-growth benchmarks and documented limits.

## Code Review Findings
Pending final broad independent review.

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
Only explicitly documented accepted release limitations permitted.

## Documentation Updated
All user/developer/release documentation must match actual behavior.

## Completion Checklist
All release checklist and final verification gates.

## Next Milestone
None — only after this milestone passes may the product be called DONE.
