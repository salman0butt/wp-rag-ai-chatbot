# M03 — AI Providers, Credentials, OpenAI, OpenRouter & WP AI Client Compatibility

Status: NOT STARTED

## Goal
Create provider/capability contracts and secure OpenAI/OpenRouter direct adapters plus WP 7 AI Client/Connectors compatibility.

## Dependencies
M01-M02.

## In Scope
Credentials/configuration backend; model discovery/cache; capability metadata; generation/error/usage normalization; OpenAI current APIs; OpenRouter current APIs; WP AI Client feature detection/adapter; mock contract tests; opt-in live smoke tests.

## Out of Scope
Full embeddings indexing (M08), production RAG orchestration (M11), admin provider UI (M12).

## Architecture
Provider registry + narrow capability contracts; server-only credentials; no hard-coded model IDs in domain logic.

## Acceptance Criteria
OpenAI/OpenRouter mocked contracts pass; capability/model discovery works; errors normalized/redacted; WP 7 adapter degrades cleanly on older supported WP; public REST cannot expose keys.

## Tasks
Pending plan.

## TDD Evidence
Pending.

## Integration Test Evidence
Provider HTTP contract + WordPress configuration integration required.

## E2E / Visual Verification
N/A until admin configuration UI.

## Security Review
Credential precedence/storage, SSRF/endpoint configuration, log redaction, cost abuse surfaces.

## Accessibility Review where UI exists
N/A.

## Performance Review where relevant
Bounded model discovery caching, HTTP timeouts/retries.

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
M04 — WordPress Knowledge Source Framework.
