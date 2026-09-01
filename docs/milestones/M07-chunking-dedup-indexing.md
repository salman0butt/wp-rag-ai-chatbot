# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing

Status: NOT STARTED

## Goal
Create deterministic normalized content/chunks with traceability, deduplication, hashes, and incremental reindex decisions.

## Dependencies
M04-M06 source/document contracts.

## In Scope
Structure-aware recursive chunking; heading/paragraph/sentence/token limits; configurable overlap; parent-child/sequence metadata; hashes; dedup; source/index versions; affected-chunk decisions.

## Out of Scope
Actual embeddings/vector upserts (M08), async execution engine (M09).

## Architecture
Document -> normalize -> structure -> chunk -> hash -> dedup -> index plan. Chunk records retain citation/source metadata and embedding compatibility fields.

## Acceptance Criteria
Boundary fixtures are deterministic; tiny/huge sections handled; metadata preserved; unchanged content produces zero unnecessary re-embed work; changed sections produce bounded affected work.

## Tasks
Pending plan.

## TDD Evidence
Required for every chunk/index behavior.

## Integration Test Evidence
Source-to-index-plan fixtures required.

## E2E / Visual Verification
N/A.

## Security Review
Retrieved metadata/content remains untrusted; normalization must not create executable instructions.

## Accessibility Review where UI exists
N/A.

## Performance Review where relevant
Large-document benchmark and duplicate-embedding avoidance.

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
M08 — Embeddings & Vector Stores.
