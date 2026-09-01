# M06 — WooCommerce Knowledge Ingestion

Status: NOT STARTED

## Goal
Normalize WooCommerce catalog knowledge while clearly separating stable indexed facts from live transactional state.

## Dependencies
M04; WooCommerce optional environment.

## In Scope
Title/descriptions/SKU/categories/tags/attributes/variations metadata/product URL and appropriate catalog metadata; source change detection; product access/status policies.

## Out of Scope
Cart/order actions and live commerce UI (M18); embedding/index runtime (M07-M08).

## Architecture
WooCommerce source adapter -> canonical Document(s). Dynamic price/stock/cart/order/discount values are marked as live-tool data and not trusted from embeddings for transactional answers.

## Acceptance Criteria
Simple/variable products normalize correctly; SKU/exact metadata preserved; product updates/deletes emit correct source version changes; disabled WooCommerce does not break plugin.

## Tasks
Pending plan.

## TDD Evidence
Pending.

## Integration Test Evidence
WooCommerce fixtures/integration required.

## E2E / Visual Verification
N/A unless UI introduced.

## Security Review
Product visibility, customer-specific metadata exclusion, safe metadata allowlist.

## Accessibility Review where UI exists
N/A.

## Performance Review where relevant
Bulk catalog enumeration/N+1 avoidance.

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
M07 — Normalization, Chunking, Deduplication & Incremental Indexing.
