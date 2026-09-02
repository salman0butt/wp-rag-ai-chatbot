# M05 — File/Document Ingestion

Status: IN PROGRESS — Task 1 complete; Tasks 2–7 remain.

## Goal
Safely extract and normalize supported document/file formats into canonical Documents.

## Dependencies
M04 document/source contracts.

## Design / Spec / Plan
- Design/spec: `docs/superpowers/specs/2026-09-03-m05-file-document-ingestion-design.md`.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m05-file-document-ingestion.md`.
- Approval status: `AUTO-APPROVED — SCHEDULED MODE` under repository policy after design/plan self-review.

## In Scope
PDF, DOCX, TXT, Markdown, HTML, CSV, JSON, XML extractors where reliable PHP libraries/strategies exist; MIME/size/resource checks; extraction errors; metadata; parser fixtures.

## Out of Scope
OCR-heavy image extraction unless explicitly justified later; embeddings/vector storage.

## Architecture
Extractor registry isolates parser libraries from knowledge/indexing domains; uploads are untrusted until validated. M05 uses an immutable validated-file boundary, deterministic MIME-to-extractor registry, validation before parser dispatch, and canonical `DocumentRecord` normalization. PDF/DOCX parser libraries remain isolated behind adapters.

## Acceptance Criteria
Each supported format has success/failure fixtures; spoofed/oversized/malformed cases fail safely; extraction preserves useful structure where possible; resource limits documented.

## Tasks
- [x] Task 1 — Extraction contracts and registry.
- [ ] Task 2 — File validation policy.
- [ ] Task 3 — Core text extractors.
- [ ] Task 4 — PDF and DOCX parser adapters.
- [ ] Task 5 — File knowledge source and bootstrap.
- [ ] Task 6 — Real WordPress file-ingestion smoke coverage.
- [ ] Task 7 — Integration, security review, documentation, and merge.

## Task 1 Implementation
Task 1 added:
- `ValidatedFile` immutable trusted metadata value with required path/basename/extension/MIME/positive size/lowercase SHA-256 invariants.
- `ExtractedDocument` immutable normalized parser output requiring non-blank text.
- `DocumentExtractor` parser boundary.
- `DocumentExtractorRegistry` with deterministic normalized MIME ownership, duplicate/blank ownership rejection, and explicit unsupported-MIME failures.
- `ExtractionException` as the normalized extraction-domain failure type.

No file bytes are parsed yet; Task 2 must establish the validation boundary before extractor implementations are added.

## TDD Evidence
### Task 1 RED
- Test-only behavioral RED SHA: `71bcc9dc5545575f410416d44a613c1b60ec5e44`.
- CI: `33690105092`.
- PHPCS and PHPStan reached the test suite successfully; PHPStan reported no errors.
- PHPUnit: **172 tests / 872 assertions / 5 failures**.
- All five failures were the intentional missing-contract assertion: `M05 extraction contracts must exist.`
- Earlier test-only SHAs `6cb10740...` and `d7e86568...` failed WPCS before PHPUnit and therefore are explicitly not behavioral RED evidence.

### Task 1 GREEN
- Initial implementation GREEN SHA: `73a442de5254074630273708504b088fe5e31bd1`.
- CI: `33690461154`.
- PHPStan: no errors.
- PHPUnit: **172 tests / 883 assertions**, all passing.
- Composer audit: no security vulnerability advisories.
- `js-quality` and `package`, including package assertion/artifact upload, passed on the same SHA.
- A narrow WPCS suppression preserves the approved camelCase public domain contract `supportedMimeTypes()` without weakening runtime behavior.

### Review-fix verification
- Review-fix SHA: `7a1d1fc6e2c4386f93b4f547f5ba926d963ec150`.
- CI: `33690705941`.
- PHPStan: no errors.
- PHPUnit: **175 tests / 889 assertions**, all passing.
- Composer audit: no security vulnerability advisories.
- Added focused coverage for malformed SHA-256 values, extractors with no MIME ownership, and blank MIME ownership.

## Integration Test Evidence
Task 1 adds domain contracts only. Existing real WordPress activation/database/provider/knowledge smoke remains the regression gate. Dedicated file-ingestion WordPress smoke is Task 6 after validation and extractors exist.

## E2E / Visual Verification
No M05 upload UI exists in this milestone unit; visual verification is not applicable to Task 1.

## Security Review
Task 1 introduces no parser dispatch or filesystem reads. The registry rejects blank/duplicate MIME ownership and unsupported resolution. The immutable validated-file value rejects structurally invalid trusted state. Actual trust establishment—regular/readable file checks, allowed-root enforcement, traversal/symlink escape, size limits, extension/MIME agreement, and server-side MIME detection—is deliberately Task 2 and must precede parser implementation.

## Accessibility Review where UI exists
No UI exists in Task 1; not applicable.

## Performance Review where relevant
Task 1 is an in-memory constant-time registry/value boundary; no file IO or parsing occurs. Resource limits begin in Task 2/3.

## Code Review Findings
Independent second-pass Task 1 review found **0 Critical** findings and **1 Important** coverage gap: malformed SHA-256 plus empty/blank MIME ownership invariants were implemented but lacked focused regression tests.

## Fixes
The Important review finding was fixed at `7a1d1fc6...` by adding direct tests for invalid SHA-256, empty MIME ownership, and blank MIME ownership. No production behavior change was required. Remaining Critical: **0**. Remaining Important: **0**.

## Fresh Verification Commands
Authoritative CI executes:
- `composer validate --strict`
- `composer install --no-interaction --prefer-dist`
- `composer verify:php`
- `composer audit`
- `npm ci`
- `npm run verify:js`
- package build/assertion/artifact upload
- existing WordPress activation/database/provider/knowledge smoke

## Fresh Verification Results
Task 1 code/review-fix head `7a1d1fc6...`: `php-quality`, `js-quality`, and `package` passed; PHP unit/static/audit details are recorded above. A final exact-head CI pass is required after this durable documentation update before the next task begins.

## Commits
- `a890a07c...` — M05 design/spec.
- `d412bfe6...` — M05 implementation plan.
- `6cb10740...`, `d7e86568...` — test-only pre-RED style iterations, not behavioral RED.
- `71bcc9dc...` — valid Task 1 behavioral RED.
- `dd435efa...` — extraction exception.
- `9d1e1dee...` — validated-file value.
- `8663863d...` — extracted-document value.
- `ce02156a...` — extractor contract.
- `bcd13359...` — extractor registry.
- `9c17519f...`, `73a442de...` — targeted coding-standard correction and verified implementation GREEN.
- `7a1d1fc6...` — independent-review coverage fixes.

## Files Changed
- `src/Documents/Extraction/DocumentExtractor.php`
- `src/Documents/Extraction/DocumentExtractorRegistry.php`
- `src/Documents/Extraction/ExtractedDocument.php`
- `src/Documents/Extraction/ExtractionException.php`
- `src/Documents/Extraction/ValidatedFile.php`
- `tests/Unit/Documents/Extraction/DocumentExtractionContractsTest.php`
- M05 design/spec/plan and progress documentation.

## Known Limitations
Task 1 intentionally does not validate paths/files, detect MIME, parse formats, register a `file` knowledge source, or add WordPress file-ingestion smoke. Those are Tasks 2–6 and remain mandatory before M05 can merge.

## Documentation Updated
- `docs/superpowers/specs/2026-09-03-m05-file-document-ingestion-design.md`
- `docs/superpowers/plans/2026-09-03-m05-file-document-ingestion.md`
- `docs/milestones/M05-file-document-ingestion.md`
- `docs/progress/STATUS.md` is the global handoff ledger.

## Completion Checklist
M05 is not complete. Task 1 is complete after exact-head CI; Tasks 2–7 and final milestone review/integration gates remain.

## Exact Next Unfinished Action
Execute Task 2 with strict TDD: add `MimeTypeDetector`/`FileValidationPolicy` tests for a readable regular file, empty file, >10 MiB file, unsupported extension, MIME spoof, allowed-root traversal/symlink escape, and deterministic lowercase SHA-256; capture genuine behavioral RED before adding production validation code; implement server-side `finfo` plus the explicit extension/MIME allow-list; then require focused/full GREEN, security review, and all permanent exact-head CI jobs green.

## Next Milestone
M06 — WooCommerce Knowledge Ingestion, only after genuine M05 completion and post-merge `main` verification.
