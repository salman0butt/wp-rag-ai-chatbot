# M21 — Analytics, Observability, Usage/Cost Tracking & RAG Evaluation/Regression Suite

Status: NOT STARTED

## Goal
Make product/RAG behavior measurable, diagnosable, cost-aware, and regression-testable.

## Dependencies
M10-M20 instrumentation hooks.

## In Scope
Privacy-aware events/aggregates; impressions/opens/conversations/messages/resolution/escalations/leads/feedback/unanswered/topics/source/model/embedding/latency/cost/errors; retention; saved eval cases/runs; Recall@K/MRR/source hit/citation/no-answer and answer/groundedness metrics where reliable; compare configurations.

## Out of Scope
Collecting unnecessary PII or pretending subjective LLM judges are deterministic ground truth.

## Architecture
Bounded raw events + aggregates; structured RAG traces; evaluation runner reuses production retrieval/RAG services under controlled configuration snapshots.

## Acceptance Criteria
Cost/token accounting normalized; retention cleanup works; eval suite detects seeded retrieval/citation/no-answer regressions; configuration snapshot reproducible; metrics caveats documented.

## Tasks
Pending plan; likely split analytics and eval implementation plans.

## TDD Evidence
Required.

## Integration Test Evidence
End-to-end eval fixtures and analytics aggregation.

## E2E / Visual Verification
Dashboards/eval runner desktop/mobile/loading/empty/error/large suites.

## Security Review
PII minimization, trace redaction, admin access, retention.

## Accessibility Review where UI exists
Required.

## Performance Review where relevant
Event volume, aggregation schedules, eval batching/cost limits.

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
M22 — Security/Privacy Hardening.
