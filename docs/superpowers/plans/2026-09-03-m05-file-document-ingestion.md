# M05 File/Document Ingestion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Safely validate, extract, and normalize supported local/WordPress files into deterministic `DocumentRecord` instances.

**Architecture:** Build an extraction boundary under `src/Documents/Extraction`, validate untrusted files before parser dispatch, resolve exact MIME types through an extractor registry, then connect extraction to the existing M04 `KnowledgeSource`/`DocumentRecord` pipeline through a `FileDocumentSource`. Keep PDF/DOCX parser libraries behind adapters and keep chunking/indexing/UI out of M05.

**Tech Stack:** PHP 8.2+, WordPress 6.9+, PHPUnit 10, Brain Monkey, server-side `finfo`, DOM/libxml, core CSV/JSON functions, Composer parser adapters for PDF/DOCX.

**Spec:** `docs/superpowers/specs/2026-09-03-m05-file-document-ingestion-design.md`

## Global Constraints

- Status is `AUTO-APPROVED — SCHEDULED MODE`.
- Uploaded/local files are untrusted until validation completes.
- Default maximum file size is 10 MiB.
- Client-provided MIME is never authoritative; server-side MIME detection and extension mapping must agree.
- No OCR, shell commands, remote downloads, macro execution, arbitrary archive extraction, chunking, embeddings, indexing, queues, or admin UI in M05.
- Every meaningful behavior follows observable RED -> GREEN evidence on an exact SHA.
- GitHub Actions is the authoritative dependency-backed runner in this runtime per ADR-018.

---

### Task 1: Extraction contracts and registry

**Files:**
- Create: `src/Documents/Extraction/ValidatedFile.php`
- Create: `src/Documents/Extraction/ExtractedDocument.php`
- Create: `src/Documents/Extraction/DocumentExtractor.php`
- Create: `src/Documents/Extraction/DocumentExtractorRegistry.php`
- Create: `src/Documents/Extraction/ExtractionException.php`
- Test: `tests/Unit/Documents/Extraction/DocumentExtractorRegistryTest.php`
- Test: `tests/Unit/Documents/Extraction/ValidatedFileTest.php`
- Test: `tests/Unit/Documents/Extraction/ExtractedDocumentTest.php`

**Interfaces:**
- `DocumentExtractor::supportedMimeTypes(): array`
- `DocumentExtractor::extract(ValidatedFile $file): ExtractedDocument`
- `DocumentExtractorRegistry::register(DocumentExtractor $extractor): void`
- `DocumentExtractorRegistry::get(string $mimeType): DocumentExtractor`

- [ ] Write tests proving immutable value invariants, duplicate/blank MIME rejection, exact lookup, unsupported lookup failure, and extractor MIME ownership.
- [ ] Push the test-only commit and record CI RED caused by absent contracts/registry.
- [ ] Implement the minimum immutable values, exception, contract, and registry.
- [ ] Verify focused and full PHP GREEN; run all permanent CI jobs.
- [ ] Independent review Task 1 and fix Critical/Important findings before continuing.

### Task 2: File validation policy

**Files:**
- Create: `src/Documents/Extraction/FileValidationPolicy.php`
- Create: `src/Documents/Extraction/MimeTypeDetector.php`
- Create: `src/Documents/Extraction/NativeMimeTypeDetector.php`
- Test: `tests/Unit/Documents/Extraction/FileValidationPolicyTest.php`

**Interfaces:**
- `FileValidationPolicy::validate(string $path, ?string $allowedRoot = null): ValidatedFile`
- `MimeTypeDetector::detect(string $path): string`

- [ ] Write fixtures/tests for readable regular file, empty file, >10 MiB, unsupported extension, MIME spoof, traversal/root escape, symlink escape, and lowercase SHA-256 file hash.
- [ ] Capture behavioral RED.
- [ ] Implement explicit extension-to-MIME allow-list and `finfo` production detector; client MIME is not consumed.
- [ ] Verify GREEN, static analysis, coding standards, security review, and permanent CI.

### Task 3: Core text extractors

**Files:**
- Create: `src/Documents/Extraction/TextDocumentExtractor.php`
- Create: `src/Documents/Extraction/MarkdownDocumentExtractor.php`
- Create: `src/Documents/Extraction/HtmlDocumentExtractor.php`
- Create: `src/Documents/Extraction/CsvDocumentExtractor.php`
- Create: `src/Documents/Extraction/JsonDocumentExtractor.php`
- Create: `src/Documents/Extraction/XmlDocumentExtractor.php`
- Test/fixtures under: `tests/Unit/Documents/Extraction/fixtures/`

- [ ] Add success/failure fixture tests for TXT/Markdown/HTML/CSV/JSON/XML.
- [ ] Include binary/null-byte text, script/style stripping, bounded CSV rows/columns, malformed/deep JSON, malformed XML, and hostile XML entity fixtures.
- [ ] Capture RED, then implement one extractor at a time with focused GREEN after each.
- [ ] Verify XML external entity/network loading remains disabled and output is deterministic UTF-8 text.
- [ ] Run full PHP/permanent CI and independent review.

### Task 4: PDF and DOCX parser adapters

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Create: `src/Documents/Extraction/PdfDocumentExtractor.php`
- Create: `src/Documents/Extraction/DocxDocumentExtractor.php`
- Create: `src/Documents/Extraction/DocxArchiveInspector.php`
- Test/fixtures: valid/malformed/password-protected PDF and valid/malformed/resource-bomb-like DOCX fixtures.

- [ ] Add tests first for parser success, malformed failure normalization, unsupported/password-protected PDF behavior, and DOCX archive entry/uncompressed-size limits.
- [ ] Capture RED before production dependencies/adapters exist.
- [ ] Add `smalot/pdfparser` and `phpoffice/phpword` with versions compatible with PHP >=8.2.
- [ ] Implement small adapters and pre-PHPWord ZIP resource inspection; do not expose parser exceptions/paths directly.
- [ ] Run Composer audit, full PHP verification, package build, and permanent CI.
- [ ] Review dependency licenses/security and record them in milestone docs.

### Task 5: File knowledge source and bootstrap

**Files:**
- Create: `src/Knowledge/Sources/FileDocumentSource.php`
- Modify: `src/Knowledge/KnowledgeBootstrap.php`
- Test: `tests/Unit/Knowledge/Sources/FileDocumentSourceTest.php`
- Test: `tests/Unit/Knowledge/KnowledgeBootstrapTest.php`

**Interfaces:**
- source type: `file`
- stable document key: `file:{sourceKey}`

- [ ] Add tests for source-type/config validation, persisted source requirement, stable key/version/hash, metadata traceability, language/visibility, unsupported file, and extractor failure propagation.
- [ ] Capture RED.
- [ ] Implement minimal source using `FileValidationPolicy`, registry, existing `DocumentHasher`, and existing `DocumentRecord`.
- [ ] Register the source without changing M04 extension semantics.
- [ ] Verify focused/full GREEN and independent review.

### Task 6: Real WordPress file-ingestion smoke coverage

**Files:**
- Create: `scripts/test-wp-file-ingestion.php`
- Create: `scripts/test-wp-file-ingestion.sh`
- Modify: `package.json`
- Modify: `.github/workflows/ci.yml`

- [ ] Create representative small media/upload fixtures through WordPress APIs for supported safe formats.
- [ ] Verify server-side validation, extraction, stable hash/version, malformed/spoofed rejection, and cleanup.
- [ ] Wire `npm run test:wp:file-ingestion` into `wordpress-smoke`.
- [ ] Any production defect discovered receives a dedicated unit RED/GREEN regression before its fix.
- [ ] Require exact-head permanent CI green.

### Task 7: M05 integration, security review, documentation, and merge

**Files:**
- Modify: `docs/milestones/M05-file-document-ingestion.md`
- Modify: `docs/progress/STATUS.md`
- Modify: `docs/progress/TEST-MATRIX.md` / `SECURITY.md` / `KNOWN-ISSUES.md` / `TECH-DEBT.md` when evidence changes.
- Modify: `docs/DECISIONS.md` only for material architectural/process decisions.

- [ ] Reconcile branch with latest `main` and resolve conflicts without dropping unrelated work.
- [ ] Perform final security review: traversal/symlink, MIME spoof, resource bombs, XML entities, parser errors, sensitive paths, executable content.
- [ ] Perform performance review for configured limits and synchronous extraction boundaries.
- [ ] Independent whole-milestone review; fix all Critical/Important findings and re-review.
- [ ] Run exact-head CI and inspect package artifact.
- [ ] Mark M05 complete only after all acceptance criteria/evidence are durable.
- [ ] Open/update PR, merge only exact tested SHA, then verify fresh post-merge `main` CI before closing milestone.

## Self-review

- Spec coverage: validation, registry, all eight formats, canonical source mapping, WordPress smoke, security/resource limits, review, docs, merge/post-merge verification are assigned to tasks.
- Placeholder scan: no TODO/TBD or unspecified implementation steps remain.
- Type consistency: extractor, registry, validated-file, and file-source interfaces are stable across tasks.
- Scope: OCR, URL crawling, chunking/indexing, embeddings, queueing, and admin UI remain deferred.
