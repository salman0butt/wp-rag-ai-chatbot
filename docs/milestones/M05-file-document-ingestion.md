# M05 — File/Document Ingestion

Status: IN PROGRESS — Tasks 1–5 complete; Tasks 6–7 remain.

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
Extractor registry isolates parser libraries from knowledge/indexing domains; uploads are untrusted until validated. M05 uses an immutable validated-file boundary, deterministic MIME-to-extractor registry, validation before parser dispatch, and canonical `DocumentRecord` normalization. PDF/DOCX parser libraries remain isolated behind adapters. `FileDocumentSource` composes the validator and extractor registry without moving parser logic into the knowledge-source domain.

## Acceptance Criteria
Each supported format has success/failure fixtures; spoofed/oversized/malformed cases fail safely; extraction preserves useful structure where possible; resource limits documented.

## Tasks
- [x] Task 1 — Extraction contracts and registry.
- [x] Task 2 — File validation policy.
- [x] Task 3 — Core text extractors.
- [x] Task 4 — PDF and DOCX parser adapters.
- [x] Task 5 — File knowledge source and bootstrap.
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
Task 2 added `MimeTypeDetector`, `NativeMimeTypeDetector`, and `FileValidationPolicy`. Validation canonicalizes paths, requires readable regular non-empty files, applies the default 10 MiB ceiling, uses server-side `finfo`, enforces explicit extension/MIME agreement, rejects canonical allowed-root and symlink escape, and produces deterministic lowercase SHA-256 metadata. Client-provided MIME is never accepted or trusted.

The explicit validation allow-list is:
- `txt` → `text/plain`
- `md`, `markdown` → `text/markdown` or `text/plain`
- `html`, `htm` → `text/html`
- `csv` → `text/csv` or `text/plain`
- `json` → `application/json` or `text/plain`
- `xml` → `application/xml` or `text/xml`
- `pdf` → `application/pdf`
- `docx` → `application/vnd.openxmlformats-officedocument.wordprocessingml.document`

### Task 2 TDD / Review Evidence
- First test-only SHA `bb7e15fcb444da38c713bd9181d37200c0f42089`, CI `33692300301`, failed WPCS before PHPUnit and is explicitly not behavioral RED.
- Valid RED: `874a2e5d2dc901d79175806f8b5c37a4c2a5ae73`, CI `33692375663`; PHPStan no errors; PHPUnit **183 / 897 / 8 failures**, all exactly because Task 2 contracts were absent.
- Initial GREEN: `0c844673f6c374161f8cd5634223520c249ea1b3`, CI `33692560859`; PHPStan no errors; PHPUnit **183 / 929**; Composer audit clean; PHP/JS/package green.
- Independent review found **0 Critical / 1 Important** API issue: approved public signature requires named parameter `$allowedRoot`, while initial implementation exposed `$allowed_root`.
- Review RED: `6c2fe061dc6959001d91044f828113ec49de0c62`, CI `33692753424`; PHPStan no errors; PHPUnit **184 / 932 / 1 error**, exactly `Unknown named parameter $allowedRoot`.
- Review-fix GREEN: `1263be3a9e7e80688cccc7234b342ce97c67c24c`, CI `33692911228`; PHPStan no errors; PHPUnit **184 / 933**; Composer audit clean. Remaining Critical/Important: **0 / 0**.
- SHA `c06ae273...` failed WPCS before PHPUnit due suppression placement and is explicitly not GREEN evidence.
- Documentation head `87e56c6fc76f829918c8bf3cef449a3c1c422343`, CI `33693060308`, passed all permanent jobs.

## Task 3 Implementation
Task 3 added bounded deterministic core extractors:
- `TextDocumentExtractor` owns `text/plain`, normalizes UTF-8 line endings/outer whitespace, and rejects binary/null-byte or empty content.
- `MarkdownDocumentExtractor` owns `text/markdown`, preserves readable Markdown structure while normalizing line endings, and rejects binary/null-byte or empty content.
- `HtmlDocumentExtractor` owns `text/html`, parses with libxml network access disabled, strips scripts/styles/comments, caps DOM size at 5,000 elements, and traverses visible text across generic block/inline containers without discarding readable content.
- `CsvDocumentExtractor` owns `text/csv`, streams deterministic tabular text with a 1,000-row and 100-column ceiling.
- `JsonDocumentExtractor` owns `application/json`, parses at maximum depth 64 and emits stable pretty-printed UTF-8 JSON; malformed/deep payloads fail through `ExtractionException`.
- `XmlDocumentExtractor` owns `application/xml` and `text/xml`, rejects `DOCTYPE`, parses with `LIBXML_NONET`, caps element nesting at depth 64, preserves mixed visible content, and normalizes parser failures through `ExtractionException`.
- `DocumentExtractorRegistry` resolves all six MIME ownership mappings without changing its Task 1 contract.

### Task 3 Primary TDD Evidence
- Initial test-only SHA `c8af88a5e2d3415316fecfc2005cf74df8bde6a1`, CI `33697012194`, stopped at WPCS and is explicitly **not** behavioral RED.
- Valid behavioral RED: `5379d8b40341bbccbfbb2016d1e7386c0089bd77`, CI `33697098324`; PHPCS passed, PHPStan reported no errors, then PHPUnit executed **201 tests / 950 assertions / 17 failures**, all caused by the intentionally absent six Task 3 extractor contracts.
- Initial implementation SHA `3490f0214b8f2d6a805a2c08655373066c7f63d5` failed WPCS before PHPUnit and is not GREEN.
- Quality/static-analysis corrections `1878c288...` and `87dd33be...` addressed only repository coding-standard/static-type issues. At `87dd33bec3a319504c31749509c6edcf12b240e4`, CI `33697603796` reached PHP GREEN: PHPStan no errors, PHPUnit **201 / 1001**, Composer audit clean; package also passed.

### Task 3 Independent Review / Review-Fix TDD
A distinct requirements/security second pass found two Important issues: generic visible HTML wrapped only in containers such as `div/span` was discarded, and Markdown failure behavior lacked a focused regression. No Critical findings were found.

- Review regression SHA `cd41be2cebe6e810f139aceefca12b568d5d6b12`, CI `33697784766`: PHPStan no errors; PHPUnit **203 / 1006 / 1 error**, exactly the generic-visible-HTML loss. The Markdown binary regression already passed.
- HTML behavior fix SHA `97d6bd397ebcf46959f19cdb5b7fb9e5047f3458`; subsequent `350079a2...` and `383a5a25...` were coding-standard/static-type corrections only. CI `33698148849` at `383a5a25045bb6e25c50aac30383254c69766877` reached PHP GREEN: PHPStan no errors; PHPUnit **203 / 1007**; Composer audit clean.

A final bounded second pass then found one additional Important XML correctness issue: mixed content such as `Hello <em>world</em>!` emitted only the leaf text `world`.

- First XML review test SHA `4f0adcd904911ffc3cbe90b06c9a409610857069`, CI `33698248309`, stopped at WPCS and is explicitly not behavioral RED.
- Valid XML review RED: `89d0eaf4864a8b07fb00fa6de9165d6b3dbef98c`, CI `33698299866`; PHPStan no errors; PHPUnit **204 / 1009 / 1 failure**, exactly expected `Hello world!` versus actual `world`.
- XML review-fix GREEN: `442063e463885cde958ceec47cd991c01ac4d917`, CI `33698452380`; PHPStan no errors; PHPUnit **204 / 1009**, all passing; Composer audit clean.
- Final Task 3 second pass: **0 Critical / 0 Important** remaining findings.

## Task 4 Implementation
Task 4 adds isolated `smalot/pdfparser` and `phpoffice/phpword` adapters plus parser-specific resource guards:
- `PdfDocumentExtractor` owns `application/pdf`, extracts deterministic visible text, rejects malformed/encrypted PDFs through the stable `ExtractionException` boundary, caps parsed pages at 200 and normalized output at 2 MiB by default, caps compressed-stream decode memory at 8 MiB by default, and disables retained image content to reduce parser memory pressure.
- `DocxArchiveInspector` inspects the ZIP container before PHPWord sees it, with defaults of 1,000 entries and 20 MiB aggregate uncompressed bytes.
- `DocxDocumentExtractor` owns the DOCX MIME type, runs the archive inspector first, loads through PHPWord's Word2007 reader, recursively collects visible text, and normalizes malformed/parser failures without leaking file paths.
- Package assertions require parser runtime files while pruning development-only dependency files from the distributable plugin archive.

### Task 4 TDD / Review Evidence
- The recovered Task 4 history contains test-first parser/resource regressions and implementation/package commits before this closeout. The PDF resource regression commit `12a1c5718e6b7568bdf3f7ffce9e7e12ecb69c45` added page/text ceiling tests before the corresponding bounded constructor behavior; subsequent quality-only test commits converged before implementation commits `070dd5dc...` / `bcc4417b...`. Earlier adapter tests also cover valid/malformed/encrypted PDF, valid/malformed DOCX, MIME ownership, and DOCX ZIP entry/uncompressed-size limits.
- Independent Task 4 security/performance second pass found **0 Critical / 1 Important** issue: page/text ceilings were checked only after `parseFile()`/text decoding, so a hostile compressed stream could consume parser-side decode memory before those post-parse checks applied.
- Review-fix behavioral RED: `15863a76e7d9358a486e310a6f60ef06a921467c`, CI `33708944268`; PHPStan reported no errors, then PHPUnit executed **216 tests / 1050 assertions / 4 failures**, all exactly caused by the intentionally missing public `maxDecodeBytes` parser-limit contract.
- Review-fix GREEN: `0b5f99da94316a091e7e33711808bc774a7ad25f`, CI `33709090219`; PHPStan no errors; PHPUnit **216 / 1053**, Composer audit reported no security advisories, JS quality passed, package/assertion/artifact upload passed, and WordPress activation/database/provider/knowledge smoke passed.
- Green artifact: `9876293491`, **700,875 bytes**, digest `sha256:4288efcad7b7bbaffbcc5a0f5731734992cee6437c3bc8e47cca08dd0f8957cf`.
- Dependency review from the committed lock file: `phpoffice/phpword 1.4.0` is `LGPL-3.0-only`; `smalot/pdfparser v2.12.5` is `LGPL-3.0`; transitive `phpoffice/math 0.3.0` is MIT. Composer audit is clean at the Task 4 GREEN SHA.
- Final Task 4 second pass after the decode-memory fix: **0 Critical / 0 Important** remaining findings.

## Task 5 Implementation
Task 5 added `FileDocumentSource` and wired it into `KnowledgeBootstrap` without changing the M04 third-party source-extension filter contract. The native bootstrap now composes all eight M05 extractors behind `DocumentExtractorRegistry` and registers stable source type `file`.

`FileDocumentSource`:
- requires a persisted `file` source and validates its configured local path before parser dispatch;
- uses `FileValidationPolicy` plus `DocumentExtractorRegistry` rather than duplicating validation/parser logic;
- emits stable document key `file:{sourceKey}`;
- derives `sourceVersion` from validated lowercase SHA-256 plus file size;
- uses the existing `DocumentHasher` for deterministic canonical content hashes;
- records traceable filename, extension, detected MIME, size, file SHA-256, parser metadata, language, and visibility;
- fails closed for invalid/unsupported files and propagates extraction-domain failures;
- preserves format-aware JSON/CSV/Markdown parsing when `finfo` legitimately reports approved `text/plain` fallback MIME, so structured parser validation/resource limits cannot be bypassed by generic text dispatch.

### Task 5 TDD / Review Evidence
- Initial test-only SHA `2980f97f8e90b338b45ac10de47c94efb43c4363`, CI `33712943957`, stopped in PHPCS and is explicitly **not** behavioral RED.
- Valid primary RED: `4ef502da17ccfc687348487c0864ae55e5b08470`, CI `33713028193`; PHPCS and PHPStan passed, then PHPUnit executed **220 tests / 1056 assertions / 5 failures**, exactly for the absent `FileDocumentSource` contract and missing bootstrap registration.
- Initial implementation SHA `8d6bdac20e6d59417ecd62d5313ae2af0f56f4cb`, CI `33713219503`, stopped at one WPCS assignment-alignment warning before PHPUnit and is explicitly not GREEN.
- Initial behavioral GREEN: `7a923d7bc9788e66cb8b7de7e6c9351659e05d45`, CI `33713283997`; PHPStan no errors; PHPUnit **220 / 1092**, all passing; Composer audit clean.
- Independent requirements/security review found **0 Critical / 1 Important** issue: validated `.json`, `.csv`, and Markdown files may legitimately use approved `text/plain` MIME fallback, but MIME-only registry lookup routed them through generic text extraction and bypassed the format-specific validation/resource boundary.
- Review-fix RED: `3b98b20df2ba64b5a4f24485265034086c8b2198`, CI `33713400467`; PHPStan no errors; PHPUnit **221 / 1096 / 1 failure**, exactly expected structured JSON parser output versus actual generic text output.
- Review-fix GREEN: `c5de4345bad914786f2ed3ddd64651f8a5c2ec56`, CI `33713508805`; PHPStan no errors; PHPUnit **221 / 1097**, Composer audit clean; `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed.
- Final plan-required unsupported-file coverage first landed at `264a13c1d603cf78d39f1159653ea7a5cb5a4f84`; CI `33713639102` correctly exposed only an assertion-message mismatch against the already-fail-closed validator and did not identify a runtime defect.
- Coverage expectation correction: `00b3b88ac6a9b57d964ba2ee33035a45439f6d69`, CI `33713788205`; PHPStan no errors; PHPUnit **222 / 1102**, Composer audit clean; all four permanent jobs passed, including WordPress activation/database/provider/knowledge smoke and package artifact upload.
- Exact Task 5 artifact at `00b3b88a...`: `9877799511`, **702,888 bytes**, digest `sha256:f8d00eeffd5c1a828e3763508973c5bef5a9e9bbe777d3984340f34498f89983`.
- Final Task 5 independent second pass after the structured-fallback fix and unsupported-input coverage: **0 Critical / 0 Important** remaining findings. PR #6 has no submitted reviews and no unresolved review threads at this closeout point.

## Integration Test Evidence
Tasks 1–5 remain primarily PHP-domain/filesystem/parser/knowledge-source boundaries. Existing WordPress activation/database/provider/knowledge smoke is green on the exact Task 5 closeout SHA `00b3b88a...`. Dedicated real WordPress file-ingestion smoke is Task 6.

## E2E / Visual Verification
No M05 upload UI exists through Task 5; visual verification is not applicable.

## Security Review
Through Task 5, the boundary fails closed for nonexistent/non-regular/unreadable candidates, empty files, files over the configured/default 10 MiB ceiling, unsupported extensions, extension/MIME disagreement, invalid allowed roots, canonical path/symlink escape, failed MIME detection/hashing, binary/null-byte text, malformed/deep JSON, malformed/deep XML, XML `DOCTYPE`/external entity declarations, excessive HTML/CSV structure, malformed/encrypted PDF, excessive PDF page/text/decode-memory use, malformed DOCX, and excessive DOCX ZIP entry/uncompressed-byte counts. File-source wiring preserves validation before parser dispatch and now preserves structured parsing for approved text/plain fallback cases. HTML/XML use libxml network-disabled parsing. No shell execution, remote fetch, OCR, macro execution, or arbitrary archive extraction is introduced.

## Accessibility Review where UI exists
No UI exists in Tasks 1–5; not applicable.

## Performance Review where relevant
Validation constrains files to <=10 MiB by default before parser dispatch. Core parser ceilings remain HTML <=5,000 elements, CSV <=1,000 rows and <=100 columns, JSON depth <=64, XML depth <=64. PDF adds <=200 pages, <=2 MiB normalized text, and <=8 MiB compressed-stream decode memory by default with image-content retention disabled. DOCX adds <=1,000 ZIP entries and <=20 MiB aggregate uncompressed bytes before PHPWord parsing.

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

## Key Task 5 Commits
- `2980f97f...` — initial test-only iteration; WPCS stop, not RED.
- `4ef502da...` — valid Task 5 behavioral RED.
- `8d6bdac2...` — source/bootstrap implementation; WPCS stop, not GREEN.
- `7a923d7b...` — initial Task 5 GREEN.
- `3b98b20d...` — structured text fallback review RED.
- `c5de4345...` — structured fallback review-fix GREEN.
- `264a13c1...` — unsupported-input coverage with expectation mismatch.
- `00b3b88a...` — corrected coverage and exact Task 5 code/test closeout.

## Known Limitations
Tasks 1–5 intentionally do not yet provide real WordPress file-ingestion smoke coverage. Task 6 owns representative media/upload fixtures through WordPress APIs and CI wiring; Task 7 owns whole-milestone reconciliation/security/performance/review/documentation/merge. These remain mandatory before M05 can merge.

## Documentation Updated
- `docs/superpowers/specs/2026-09-03-m05-file-document-ingestion-design.md`
- `docs/superpowers/plans/2026-09-03-m05-file-document-ingestion.md`
- `docs/milestones/M05-file-document-ingestion.md`
- `docs/progress/STATUS.md` is the global handoff ledger.

## Completion Checklist
M05 is not complete. Tasks 1–5 are complete; Tasks 6–7 and final milestone review/integration gates remain.

## Exact Next Unfinished Action
Execute **Task 6 — Real WordPress file-ingestion smoke coverage**. Add `scripts/test-wp-file-ingestion.php` and `.sh`, wire `npm run test:wp:file-ingestion` into `package.json` and the `wordpress-smoke` CI job, create representative small supported file/media fixtures through WordPress APIs, verify server-side validation/extraction/stable hash-version plus malformed/spoofed rejection and cleanup, route any production defect through dedicated unit RED/GREEN before its fix, and require all permanent jobs green on the exact Task 6 SHA before Task 7.

## Next Milestone
M06 — WooCommerce Knowledge Ingestion, only after genuine M05 completion and post-merge `main` verification.
