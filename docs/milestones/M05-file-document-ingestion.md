# M05 — File/Document Ingestion

Status: IN PROGRESS — Tasks 1–2 complete; Tasks 3–7 remain.

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
- [x] Task 2 — File validation policy.
- [ ] Task 3 — Core text extractors.
- [ ] Task 4 — PDF and DOCX parser adapters.
- [ ] Task 5 — File knowledge source and bootstrap.
- [ ] Task 6 — Real WordPress file-ingestion smoke coverage.
- [ ] Task 7 — Integration, security review, documentation, and merge.

## Task 1 Implementation
Task 1 added `ValidatedFile`, `ExtractedDocument`, `DocumentExtractor`, `DocumentExtractorRegistry`, and `ExtractionException`. The registry normalizes deterministic MIME ownership, rejects blank/duplicate ownership and unsupported resolution, while the immutable values reject invalid trusted state.

### Task 1 TDD / Review Evidence
- Valid behavioral RED: `71bcc9dc5545575f410416d44a613c1b60ec5e44`, CI `33690105092`; PHPStan no errors; PHPUnit **172 tests / 872 assertions / 5 intentional missing-contract failures**. Earlier WPCS-only test iterations were not counted as RED.
- Initial GREEN: `73a442de5254074630273708504b088fe5e31bd1`, CI `33690461154`; PHPStan no errors; PHPUnit **172 / 883**; Composer audit, JS quality and package/artifact gates green.
- Independent review found **0 Critical / 1 Important** coverage gap around invalid SHA-256 and empty/blank MIME ownership; fixed at `7a1d1fc6e2c4386f93b4f547f5ba926d963ec150`, with **175 / 889** passing and no remaining Critical/Important findings.
- Final Task 1 documentation head before Task 2: `e5c3a8da89c09591a69c29da045723c982f4eb23`; CI `33690878698` passed all permanent jobs.

## Task 2 Implementation
Task 2 added:
- `MimeTypeDetector` as the server-side MIME-detection boundary.
- `NativeMimeTypeDetector`, backed by PHP `finfo(FILEINFO_MIME_TYPE)` and normalized lowercase output.
- `FileValidationPolicy`, which establishes trusted `ValidatedFile` metadata only after canonical `realpath` resolution, readable regular-file checks, optional allowed-root containment, explicit extension allow-list, positive/default <=10 MiB size enforcement, extension/MIME agreement, and deterministic lowercase SHA-256 hashing.
- Allowed-root containment is checked on canonical paths, so a symlink inside the root that resolves outside the root fails closed.
- The approved public API `validate(string $path, ?string $allowedRoot = null)` is preserved, including PHP named-argument compatibility.

The explicit validation allow-list is:
- `txt` → `text/plain`
- `md`, `markdown` → `text/markdown` or `text/plain`
- `html`, `htm` → `text/html`
- `csv` → `text/csv` or `text/plain`
- `json` → `application/json` or `text/plain`
- `xml` → `application/xml` or `text/xml`
- `pdf` → `application/pdf`
- `docx` → `application/vnd.openxmlformats-officedocument.wordprocessingml.document`

No client-provided MIME value is trusted or accepted by the validation API.

## TDD Evidence
### Task 2 RED
- First test-only SHA `bb7e15fcb444da38c713bd9181d37200c0f42089`, CI `33692300301`, failed WPCS before PHPUnit and is explicitly **not** behavioral RED evidence.
- Valid behavioral RED: `874a2e5d2dc901d79175806f8b5c37a4c2a5ae73`, CI `33692375663`.
- PHPCS passed, PHPStan reported no errors, and PHPUnit executed **183 tests / 897 assertions / 8 failures**.
- All eight failures were exactly the intentionally absent Task 2 contracts: `FileValidationPolicy`, `MimeTypeDetector`, and `NativeMimeTypeDetector`.

### Task 2 Initial GREEN
- Implementation head `0c844673f6c374161f8cd5634223520c249ea1b3`, CI `33692560859`.
- PHPStan: no errors.
- PHPUnit: **183 tests / 929 assertions**, all passing.
- Composer audit: no security vulnerability advisories.
- `php-quality`, `js-quality`, and `package` passed on that exact SHA; WordPress smoke was still running when the independent review cycle began, so this SHA is not the final Task 2 verification head.
- Earlier implementation SHA `f85cfa5d...` failed only WPCS before tests and is not counted as GREEN.

### Independent Review Finding and TDD Fix
A distinct requirement/security second pass found **0 Critical** and **1 Important** issue: the approved public plan names the optional parameter `$allowedRoot`, but the initial implementation exposed `$allowed_root`. Because PHP named arguments make parameter names part of observable API behavior, this was an API-contract defect.

Review-fix TDD:
- Regression RED SHA: `6c2fe061dc6959001d91044f828113ec49de0c62`, CI `33692753424`.
- PHPStan: no errors.
- PHPUnit: **184 tests / 932 assertions / 1 error** — exactly `Unknown named parameter $allowedRoot`.
- The fix changed only the public parameter name to the approved `$allowedRoot`, with a narrow WPCS suppression documenting why camelCase is contractually required.
- SHA `c06ae273...` did not reach PHPUnit because the first suppression placement broke WPCS docblock association; it is explicitly not GREEN evidence.
- Review-fix GREEN code SHA: `1263be3a9e7e80688cccc7234b342ce97c67c24c`, CI `33692911228`.
- PHPStan: no errors.
- PHPUnit: **184 tests / 933 assertions**, all passing.
- Composer audit: no security vulnerability advisories.
- Remaining review findings after the fix: **0 Critical / 0 Important**.

## Integration Test Evidence
Task 2 is a PHP-domain/filesystem trust boundary. Existing WordPress activation/database/provider/knowledge smoke remains the permanent regression gate. Dedicated real WordPress file-ingestion smoke is Task 6 after extractors and source/bootstrap wiring exist.

## E2E / Visual Verification
No M05 upload UI exists in Tasks 1–2; visual verification is not applicable.

## Security Review
Task 2 fails closed for nonexistent/non-regular/unreadable candidates, empty files, files over the configured/default 10 MiB ceiling, unsupported extensions, extension/MIME disagreement, invalid allowed roots, canonical path escape, symlink escape, failed MIME detection, and failed hashing. Server-side `finfo` is the MIME authority; arbitrary `application/octet-stream` is not accepted. No parser, shell execution, network fetch, OCR, or unsafe XML processing is introduced in this task.

Format-specific structural validation and parser resource controls remain mandatory in Tasks 3–4; accepting a MIME/extension pair does not by itself make document contents semantically valid.

## Accessibility Review where UI exists
No UI exists in Tasks 1–2; not applicable.

## Performance Review where relevant
Validation performs bounded filesystem metadata operations, one server MIME inspection, and one SHA-256 pass over a file already constrained to <=10 MiB by default. Parsing/resource limits begin in Tasks 3–4.

## Fresh Verification Commands
Authoritative CI executes:
- `composer validate --strict`
- `composer install --no-interaction --prefer-dist`
- `composer verify:php`
- `composer audit`
- `npm ci`
- `npm run verify:js`
- package build/assertion/artifact upload
- WordPress activation/database/provider/knowledge smoke

## Commits Added for Task 2
- `bb7e15fc...` — initial test-only WPCS iteration; not behavioral RED.
- `874a2e5d...` — valid Task 2 behavioral RED.
- `a4c5735c...` — MIME detector contract.
- `842147ba...` — native `finfo` MIME detector.
- `f85cfa5d...` — initial validation-policy implementation.
- `0c844673...` — coding-standard correction / initial behavioral GREEN.
- `6c2fe061...` — review-finding named-argument regression RED.
- `c06ae273...` — API fix with WPCS placement issue; not GREEN.
- `1263be3a...` — review-fix code GREEN.

## Files Changed Through Task 2
- `src/Documents/Extraction/DocumentExtractor.php`
- `src/Documents/Extraction/DocumentExtractorRegistry.php`
- `src/Documents/Extraction/ExtractedDocument.php`
- `src/Documents/Extraction/ExtractionException.php`
- `src/Documents/Extraction/ValidatedFile.php`
- `src/Documents/Extraction/MimeTypeDetector.php`
- `src/Documents/Extraction/NativeMimeTypeDetector.php`
- `src/Documents/Extraction/FileValidationPolicy.php`
- `tests/Unit/Documents/Extraction/DocumentExtractionContractsTest.php`
- `tests/Unit/Documents/Extraction/FileValidationPolicyTest.php`
- M05 design/spec/plan and progress documentation.

## Known Limitations
Tasks 1–2 intentionally do not parse supported formats, register a `file` knowledge source, or provide real WordPress file-ingestion smoke. Core text extractors are Task 3; PDF/DOCX adapters are Task 4; source/bootstrap and smoke are Tasks 5–6. These remain mandatory before M05 can merge.

## Documentation Updated
- `docs/superpowers/specs/2026-09-03-m05-file-document-ingestion-design.md`
- `docs/superpowers/plans/2026-09-03-m05-file-document-ingestion.md`
- `docs/milestones/M05-file-document-ingestion.md`
- `docs/progress/STATUS.md` is the global handoff ledger.

## Completion Checklist
M05 is not complete. Tasks 1–2 are complete once the final documentation-head CI is green; Tasks 3–7 and final milestone review/integration gates remain.

## Exact Next Unfinished Action
Execute Task 3 with strict TDD: add focused fixtures/tests for TXT, Markdown, HTML, CSV, JSON, and XML extractors, including malformed/empty/oversized-complexity failure paths and canonical metadata/text normalization; capture genuine behavioral RED before production extractors; then implement minimum bounded parsers with XML external-entity/network access disabled and explicit structural/resource limits, register them through `DocumentExtractorRegistry`, run full GREEN/security review, and require all permanent exact-head CI jobs green.

## Next Milestone
M06 — WooCommerce Knowledge Ingestion, only after genuine M05 completion and post-merge `main` verification.
