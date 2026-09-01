# M05 — File/Document Ingestion

Status: NOT STARTED

## Goal
Safely extract and normalize supported document/file formats into canonical Documents.

## Dependencies
M04 document/source contracts.

## In Scope
PDF, DOCX, TXT, Markdown, HTML, CSV, JSON, XML extractors where reliable PHP libraries/strategies exist; MIME/size/resource checks; extraction errors; metadata; parser fixtures.

## Out of Scope
OCR-heavy image extraction unless explicitly justified later; embeddings/vector storage.

## Architecture
Extractor registry isolates parser libraries from knowledge/indexing domains; uploads are untrusted until validated.

## Acceptance Criteria
Each supported format has success/failure fixtures; spoofed/oversized/malformed cases fail safely; extraction preserves useful structure where possible; resource limits documented.

## Tasks
Pending plan.

## TDD Evidence
Pending.

## Integration Test Evidence
WordPress upload/media/file integration tests required.

## E2E / Visual Verification
If upload UI exists, verify success/progress/error states.

## Security Review
Malicious upload, MIME spoofing, archive/resource bombs, path traversal, sensitive file handling.

## Accessibility Review where UI exists
Required for upload UI.

## Performance Review where relevant
Large-file memory/time behavior and background-only thresholds.

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
M06 — WooCommerce Knowledge Ingestion.
