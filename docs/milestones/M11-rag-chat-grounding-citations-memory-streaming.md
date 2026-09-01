# M11 — RAG Chat Orchestration, Grounding, Citations, Memory & Streaming

Status: NOT STARTED

## Goal
Deliver the backend RAG conversation path with deterministic grounding, bounded memory, validated citations, and normalized streaming.

## Dependencies
M03, M10, M09 where async support is needed.

## In Scope
Chat request pipeline; ownership/session foundation; rate/cost checks; memory assembly; retrieval; grounding modes; prompt/context builder; provider generation; citation IDs/validation; strict no-answer; streaming events; persistence hooks; feedback/analytics hooks.

## Out of Scope
Polished frontend widget/admin UIs; full action framework M19.

## Architecture
Server-side orchestration treats retrieved content as untrusted data and keeps authorization outside the model. Provider streaming normalizes into plugin events.

## Acceptance Criteria
Strict no-answer deterministic tests pass; citations only reference selected sources; malicious retrieved instructions cannot elevate policy; memory is bounded; stream events order/error paths tested; provider secrets absent from client responses.

## Tasks
Pending plan.

## TDD Evidence
Required.

## Integration Test Evidence
Full indexed-fixture -> retrieval -> answer/citation contract tests.

## E2E / Visual Verification
Backend streaming smoke; visual widget later M14.

## Security Review
Prompt injection, ownership, rate/cost abuse, output/link/citation safety.

## Accessibility Review where UI exists
N/A.

## Performance Review where relevant
Context/token bounds, retrieval/generation latency, disconnect cleanup.

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
M12 — Admin Onboarding/Bot Management.
