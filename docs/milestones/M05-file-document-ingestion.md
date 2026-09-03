# M05 — File/Document Ingestion

Status: COMPLETE ON FEATURE BRANCH — pending exact-head CI and merge/post-merge verification.

## Goal
Safely validate, extract, and normalize supported local/WordPress files into deterministic canonical `DocumentRecord` instances.

## Dependencies
M04 document/source contracts.

## Design / Spec / Plan
- Design/spec: `docs/superpowers/specs/2026-09-03-m05-file-document-ingestion-design.md`.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m05-file-document-ingestion.md`.
- Approval status: `AUTO-APPROVED — SCHEDULED MODE` after repository-mandated self-review.

## Scope delivered
- Immutable extraction contracts and deterministic MIME extractor registry.
- File trust boundary with readable/regular/non-empty checks, default 10 MiB ceiling, server-side `finfo`, explicit extension/MIME agreement, canonical allowed-root/symlink containment, and lowercase SHA-256 metadata.
- Bounded TXT, Markdown, HTML, CSV, JSON, XML, PDF, and DOCX extractors.
- PDF/DOCX adapters isolated behind domain contracts, including PDF page/text/decode-memory ceilings and DOCX ZIP entry/uncompressed-byte ceilings.
- Native `file` knowledge source registered through `KnowledgeBootstrap`, preserving deterministic document key/version/hash and M04 extension semantics.
- Permanent real-WordPress file-ingestion smoke coverage.

## Acceptance criteria
- [x] Every supported format has deterministic success/failure coverage.
- [x] Oversized, unsupported, spoofed, malformed, encrypted/unsupported PDF, hostile XML/entity, and resource-limit cases fail closed.
- [x] Extraction preserves useful text/structure where supported.
- [x] Parser/resource limits are explicit and documented.
- [x] Real WordPress ingestion validates extraction, deterministic identity/hash/version, malformed/spoof rejection, and cleanup.
- [x] Final security/performance/whole-milestone review completed with 0 unresolved Critical / 0 unresolved Important findings.
- [x] Accessibility review is N/A because M05 introduces no UI.

## Tasks
- [x] Task 1 — Extraction contracts and registry.
- [x] Task 2 — File validation policy.
- [x] Task 3 — Core text extractors.
- [x] Task 4 — PDF and DOCX parser adapters.
- [x] Task 5 — File knowledge source and bootstrap.
- [x] Task 6 — Real WordPress file-ingestion smoke coverage.
- [x] Task 7 — Integration, security/performance review, and documentation closeout.

## TDD / review evidence
### Task 1
- RED `71bcc9dc5545575f410416d44a613c1b60ec5e44`, CI `33690105092`: 172 tests / 872 assertions / 5 expected failures.
- GREEN `73a442de5254074630273708504b088fe5e31bd1`, CI `33690461154`: 172 / 883, Composer audit clean.
- Review fix `7a1d1fc6e2c4386f93b4f547f5ba926d963ec150`; remaining Critical/Important 0/0.

### Task 2
- RED `874a2e5d2dc901d79175806f8b5c37a4c2a5ae73`, CI `33692375663`: 183 / 897 / 8 expected failures.
- Initial GREEN `0c844673f6c374161f8cd5634223520c249ea1b3`, CI `33692560859`.
- Review RED `6c2fe061dc6959001d91044f828113ec49de0c62`, CI `33692753424`; review-fix GREEN `1263be3a9e7e80688cccc7234b342ce97c67c24c`, CI `33692911228`.

### Task 3
- RED `5379d8b40341bbccbfbb2016d1e7386c0089bd77`, CI `33697098324`: 201 / 950 / 17 expected failures.
- PHP GREEN `87dd33bec3a319504c31749509c6edcf12b240e4`, CI `33697603796`.
- HTML review regression/fix converged at `383a5a25045bb6e25c50aac30383254c69766877`, CI `33698148849`.
- XML review RED `89d0eaf4864a8b07fb00fa6de9165d6b3dbef98c`, CI `33698299866`; GREEN `442063e463885cde958ceec47cd991c01ac4d917`, CI `33698452380`.

### Task 4
- Independent review found one Important parser-side PDF decode-memory gap.
- Review RED `15863a76e7d9358a486e310a6f60ef06a921467c`, CI `33708944268`: 216 / 1050 / 4 expected failures.
- Review-fix GREEN `0b5f99da94316a091e7e33711808bc774a7ad25f`, CI `33709090219`: 216 / 1053, Composer audit clean.
- Dependency licenses: PHPWord 1.4.0 LGPL-3.0-only; smalot/pdfparser 2.12.5 LGPL-3.0; phpoffice/math 0.3.0 MIT.

### Task 5
- RED `4ef502da17ccfc687348487c0864ae55e5b08470`, CI `33713028193`: 220 / 1056 / 5 expected failures.
- Initial GREEN `7a923d7bc9788e66cb8b7de7e6c9351659e05d45`, CI `33713283997`.
- Review RED `3b98b20df2ba64b5a4f24485265034086c8b2198`, CI `33713400467`; review-fix GREEN `c5de4345bad914786f2ed3ddd64651f8a5c2ec56`, CI `33713508805`.
- Final unsupported-file coverage `00b3b88ac6a9b57d964ba2ee33035a45439f6d69`, CI `33713788205`: 222 / 1102.

### Task 6
- Wiring RED `cfe94249c313405463019500be37204c95f41a03`, CI `33716566022`: only the intentionally absent new WordPress smoke step failed (exit 127).
- GREEN `903de635dac1c0a57ecd7325c9945f5fdd6abdd7`, CI `33716829864`: all permanent jobs green including real WordPress file-ingestion smoke.
- Exact artifact `9878819165`, 702,893 bytes, digest `sha256:35f201d0459a1ad35df5babc8c774ea23e93a5136db846b748fc52a9026d6a36`.

## Task 7 whole-milestone review
The final bounded second pass re-checked traversal/symlink containment, server-side MIME/extension agreement, executable-format exclusion, malformed/parser error normalization, XML `DOCTYPE` rejection and `LIBXML_NONET`, PDF page/text/decode-memory ceilings, DOCX ZIP entry/uncompressed-byte ceilings, structured text fallback dispatch, deterministic file identity/hash metadata, synchronous extraction limits, and production packaging.

Result: **0 Critical / 0 Important** unresolved findings. No Task 7 production behavior defect was found, so no new behavioral RED/GREEN cycle was required. Synchronous extraction remains intentionally bounded to the M05 limits; larger/background ingestion remains owned by later queue policy.

## Pre-merge verification
Runtime/documentation predecessor `0dda3fc090248f16d012361298266a1584648789`, CI `33717095588`:
- `php-quality` passed; PHPStan 0 errors; PHPUnit 222 tests / 1102 assertions; Composer audit no advisories.
- `js-quality` passed.
- `package` passed with artifact upload.
- `wordpress-smoke` passed activation, database, providers, knowledge, and file-ingestion smoke.

Final documentation-head CI and post-merge `main` CI are recorded in `docs/progress/M05-CLOSEOUT.md` / PR #6 once available.
