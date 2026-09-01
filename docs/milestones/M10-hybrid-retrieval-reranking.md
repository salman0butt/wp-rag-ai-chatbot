# M10 — Semantic + Keyword + Hybrid Retrieval, Filters & Reranking

Status: NOT STARTED

## Goal
Build explainable production retrieval that combines semantic and exact/lexical signals with filters and optional reranking.

## Dependencies
M08 vectors; indexed chunks.

## In Scope
Query preprocessing; semantic search; lexical/exact search; score normalization; weighted/RRF-style fusion selected by plan; metadata/access filters; optional reranker interface/adapters; confidence/context candidate output; trace data.

## Out of Scope
Final answer generation (M11), eval product UI (M21).

## Architecture
Parallel retrieval -> normalized fusion -> filters -> optional rerank -> threshold/context candidates, all observable.

## Acceptance Criteria
Exact SKU/identifier fixtures are retrievable; semantic paraphrase fixtures are retrievable; hybrid beats or matches defined baselines; filters cannot leak restricted chunks; scores/traces deterministic enough to debug.

## Tasks
Pending plan.

## TDD Evidence
Required retrieval behavior tests.

## Integration Test Evidence
End-to-end indexed fixture retrieval required.

## E2E / Visual Verification
N/A until debugger UI M13.

## Security Review
Access/visibility filters, query abuse bounds, untrusted metadata.

## Accessibility Review where UI exists
N/A.

## Performance Review where relevant
Candidate limits, lexical indexes, vector/local-store bounds, rerank top-N.

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
M11 — RAG Chat Orchestration.
