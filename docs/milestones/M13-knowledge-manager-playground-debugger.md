# M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger

Status: NOT STARTED

## Goal
Expose knowledge/source/indexing operations and a deep retrieval/RAG diagnostic playground to administrators.

## Dependencies
M04-M11, M12 admin shell.

## In Scope
Sources/documents/chunks/index status; enqueue/cancel/retry where safe; progress/errors; test question playground; semantic/lexical candidates; raw/normalized/hybrid scores; filters/rerank; selected chunks; context estimate; models; answer/citations; latency/usage/cost/errors.

## Out of Scope
Full eval regression suite M21.

## Architecture
Debug traces are structured domain data with redaction, not arbitrary raw secret dumps.

## Acceptance Criteria
Admin can trace why a source/chunk was selected; secrets/personal data are redacted appropriately; jobs show recoverable status; playground results correlate with backend trace fixtures.

## Tasks
Pending plan.

## TDD Evidence
Backend trace and UI behavior tests required.

## Integration Test Evidence
Knowledge/job/retrieval integration required.

## E2E / Visual Verification
Desktop/mobile; large result sets; loading/empty/error; long URLs/chunks; keyboard accessibility.

## Security Review
Admin capability, sensitive trace redaction, safe retries/cancel.

## Accessibility Review where UI exists
Required.

## Performance Review where relevant
Pagination/virtualization or bounded result counts, trace retention.

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
M14 — Frontend Chatbot/Customizer.
