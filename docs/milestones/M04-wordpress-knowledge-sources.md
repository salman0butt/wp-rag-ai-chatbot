# M04 — WordPress Knowledge Source Framework

Status: NOT STARTED

## Goal
Normalize selected WordPress content into traceable knowledge Documents through extensible source contracts.

## Dependencies
M02.

## In Scope
Pages/posts/public CPTs; selected taxonomy metadata; manual text; FAQ/Q&A; selected URLs/sitemap configuration foundation where source boundary fits; source versions/hashes/access metadata; source registry/hooks.

## Out of Scope
File parsing (M05), WooCommerce specialization (M06), chunking/embeddings (M07-M08).

## Architecture
KnowledgeSource contract -> canonical Document model with identity/title/URL/content/metadata/version/hash/language/visibility.

## Acceptance Criteria
Supported WP sources normalize deterministically; draft/private/role access policy is explicit; changes produce stable version/hash signals; source extension contract tested.

## Tasks
Pending plan.

## TDD Evidence
Pending.

## Integration Test Evidence
WordPress content lifecycle/source tests required.

## E2E / Visual Verification
N/A unless source admin UI is intentionally introduced early.

## Security Review
Content visibility/permissions, URL configuration validation, stored metadata sanitization.

## Accessibility Review where UI exists
N/A.

## Performance Review where relevant
Pagination and no N+1 source enumeration.

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
M05 — File/Document Ingestion.
