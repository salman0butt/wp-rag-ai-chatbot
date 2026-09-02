# M05 File/Document Ingestion Design

Status: AUTO-APPROVED — SCHEDULED MODE

## Goal

Safely validate, extract, and normalize supported uploaded/local files into deterministic `DocumentRecord` instances without adding chunking, embeddings, indexing, OCR, remote crawling, or admin upload UI.

## Context

M04 established `KnowledgeSource`, `KnowledgeSourceRegistry`, deterministic `DocumentRecord` normalization, and fail-closed source configuration. M05 extends that architecture with a file-ingestion boundary. Uploaded files are untrusted until their path, size, extension, MIME, and parser-specific constraints pass validation.

## Approaches considered

### 1. Extractor registry + validation policy + file source — selected

Introduce a small `DocumentExtractor` contract and deterministic `DocumentExtractorRegistry`. A `FileValidationPolicy` validates file metadata before any parser runs. A `FileDocumentSource` resolves an extractor by validated MIME/type and maps extracted text/metadata into `DocumentRecord`.

Pros: isolates parser libraries, keeps security policy independent from parsing, enables focused fixtures, follows M04 registry/source patterns, and prevents third-party parser APIs from leaking into indexing/domain code.

Cons: adds several small objects, but each has one testable responsibility.

### 2. One monolithic file source with switch statements

Rejected because validation, MIME dispatch, extraction, and normalization would become coupled and difficult to security-review or extend safely.

### 3. Store parser-specific document DTOs

Rejected because M02/M04 already define the canonical `DocumentRecord`; another persistence-shaped DTO would duplicate state without a concrete second consumer.

## Selected architecture

### `DocumentExtractor`

Under `src/Documents/Extraction/DocumentExtractor.php`:

- `supportedMimeTypes(): array` returns stable MIME identifiers.
- `extract(ValidatedFile $file): ExtractedDocument` returns normalized extraction output or throws a domain extraction exception.

Extractors receive only validated files. They never decide authorization or upload ownership.

### `DocumentExtractorRegistry`

A deterministic in-memory registry keyed by MIME type. It rejects empty MIME IDs, duplicate registrations, and ambiguous ownership of the same MIME type. Resolution is exact after MIME normalization.

### `FileValidationPolicy`

Validates a candidate file before extraction:

- path must refer to a regular readable local file;
- path traversal/symlink escape is rejected when an allowed-root is configured;
- size must be greater than zero and no larger than a configurable hard maximum; default M05 maximum is 10 MiB;
- extension must be on the supported allow-list;
- detected MIME must match an allowed MIME for that extension; client-provided MIME is never authoritative;
- executable/script MIME types are rejected;
- parser/resource limits are explicit and deterministic.

Validation returns an immutable `ValidatedFile` containing canonical path, basename, extension, detected MIME, size, and SHA-256 file hash.

### `ExtractedDocument`

Immutable parser output containing normalized UTF-8 text plus selected structured metadata such as page count, headings, sheet/row labels, or parser warnings when safe. Raw binary bytes are never stored in the canonical document content.

### Initial extractors

Core-PHP extractors:

1. TXT — UTF-8 text normalization; rejects binary/null-byte content.
2. Markdown — preserves readable heading/list/code structure as text while normalizing line endings.
3. HTML — DOM-based extraction; scripts/styles/comments removed; visible headings/paragraph/list/table text retained.
4. CSV — bounded row/column parsing using `fgetcsv`; emits deterministic tabular text and row/column metadata.
5. JSON — bounded decode with `JSON_THROW_ON_ERROR`; canonical pretty text/flattened readable structure; malformed/deep payloads fail safely.
6. XML — `DOMDocument`/libxml with network/entity expansion disabled; visible text and element structure extracted with bounded depth.

Library-backed extractors:

7. PDF — `smalot/pdfparser`, isolated behind `PdfDocumentExtractor`; parser exceptions normalize to domain errors. Password-protected/unsupported PDFs fail safely. OCR is out of scope.
8. DOCX — `phpoffice/phpword`, isolated behind `DocxDocumentExtractor`; only local validated files are loaded. Embedded remote resources/macros are not executed.

The chosen libraries are mature Composer packages compatible with the project PHP baseline. Their LGPL-3 licensing must remain documented in release dependency notices.

## MIME and extension policy

Supported M05 mappings:

- `.txt` -> `text/plain`
- `.md`, `.markdown` -> `text/markdown`, with `text/plain` accepted only when extension is Markdown and content passes text validation
- `.html`, `.htm` -> `text/html`
- `.csv` -> `text/csv`, with conservative `text/plain` fallback after CSV structure validation
- `.json` -> `application/json`, with conservative text fallback only after successful JSON parse
- `.xml` -> `application/xml`, `text/xml`
- `.pdf` -> `application/pdf`
- `.docx` -> `application/vnd.openxmlformats-officedocument.wordprocessingml.document`

MIME detection uses server-side `finfo`. Extension and MIME must agree through the explicit mapping; arbitrary `application/octet-stream` is rejected.

## File knowledge source

`FileDocumentSource` implements the M04 `KnowledgeSource` contract with stable type `file`. Its persisted source config identifies a local WordPress attachment/path reference plus optional title/language/visibility. It validates the source, obtains a validated file, resolves the extractor, extracts content, and yields one canonical `DocumentRecord` per file in M05.

Stable document key: `file:{sourceKey}`.

`externalId` prefers the configured attachment/media identifier when available. `sourceVersion` is the file SHA-256 plus size. `contentHash` uses the existing `DocumentHasher` over canonical identity/title/content/metadata/version/language/visibility. Extraction timestamps do not affect hashes.

## Security

- File bytes are untrusted input.
- Client MIME is ignored for trust decisions.
- Validation runs before parser dispatch.
- Traversal, unreadable paths, symlink escape, empty files, oversized files, unsupported extensions/MIMEs, MIME spoofing, malformed documents, and parser failures fail closed.
- XML external entities/network loading are disabled.
- No shell commands, LibreOffice subprocesses, OCR binaries, remote downloads, macro execution, arbitrary archive extraction, or executable formats are introduced.
- ZIP/archive bomb defense for DOCX relies on explicit compressed/uncompressed-entry limits before PHPWord parsing; suspicious archives fail before parser invocation.
- Parser errors exposed to UI/logging later must not include sensitive filesystem paths.

## Resource limits

Default maximum input size is 10 MiB. Extractors also enforce format-specific limits where needed: bounded CSV rows/columns, JSON/XML nesting/depth, HTML node/text limits, PDF page/text limits where the parser exposes them, and DOCX archive entry/uncompressed-size limits. Larger ingestion belongs to later background-job policy rather than allowing one request unlimited memory/time.

## Parser dependencies

M05 may add `smalot/pdfparser` and `phpoffice/phpword` as production Composer dependencies after contract/core-format behavior is green. Parser adapters must remain small enough that replacing either library does not affect `FileDocumentSource` or canonical document semantics.

## Testing

Unit fixtures cover success and failure for every supported format. Security fixtures cover oversized input, empty input, unsupported extension, MIME spoofing, traversal/root escape, null-byte/binary text, malformed JSON/XML/HTML/CSV where applicable, hostile XML entities, malformed PDF/DOCX, and DOCX archive resource limits.

Registry tests cover deterministic registration, duplicate MIME rejection, and exact resolution. Source tests cover stable key/version/hash, metadata traceability, language/visibility, parser failure propagation, and unsupported-file rejection.

Real WordPress smoke coverage creates/uploads representative small files through WordPress APIs and verifies extraction without external network/provider calls.

## Milestone boundaries

Out of scope remain OCR/image extraction, URL crawling, WooCommerce specialization (M06), chunking/dedup/indexing (M07), embeddings/vector stores (M08), background queue/recovery (M09), and upload/knowledge-manager UI (M13).

## Self-review

- Placeholder scan: no TODO/TBD placeholders.
- Scope: bounded to safe local file validation/extraction/normalization.
- Security: validation precedes parsing; no network/entity/shell execution path is introduced.
- Compatibility: PHP-first WordPress runtime preserved; parser libraries isolated.
- Testability: every contract and supported format has deterministic fixture coverage.
- YAGNI: one document per file; OCR, crawling, queues, chunking, and UI remain deferred.
