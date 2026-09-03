# M05 — File/Document Ingestion

Status: COMPLETE — integrated on `main` at `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6`; post-merge CI `33721014064` passed.

## Goal
Safely validate, extract, and normalize supported local/WordPress files into deterministic canonical `DocumentRecord` instances.

## Dependencies
M04 document/source contracts.

## Design / Spec / Plan
- Design/spec: `docs/superpowers/specs/2026-09-03-m05-file-document-ingestion-design.md`.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m05-file-document-ingestion.md`.
- Approval status: `AUTO-APPROVED — SCHEDULED MODE` after repository-mandated self-review.

## Delivered scope
- Immutable extraction contracts and deterministic MIME extractor registry.
- File validation boundary with readable/regular/non-empty checks, default 10 MiB ceiling, server-side `finfo`, explicit extension/MIME agreement, canonical allowed-root/symlink containment, and lowercase SHA-256 metadata.
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
- [x] Exact feature-head CI passed before merge.
- [x] Fresh post-merge `main` CI passed.

## Tasks
- [x] Task 1 — Extraction contracts and registry.
- [x] Task 2 — File validation policy.
- [x] Task 3 — Core text extractors.
- [x] Task 4 — PDF and DOCX parser adapters.
- [x] Task 5 — File knowledge source and bootstrap.
- [x] Task 6 — Real WordPress file-ingestion smoke coverage.
- [x] Task 7 — Integration, security/performance review, documentation, and merge.

## TDD / review evidence
- Task 1: RED `71bcc9dc5545575f410416d44a613c1b60ec5e44` / CI `33690105092`; GREEN `73a442de5254074630273708504b088fe5e31bd1` / CI `33690461154`; review coverage fix `7a1d1fc6...`.
- Task 2: RED `874a2e5d2dc901d79175806f8b5c37a4c2a5ae73` / CI `33692375663`; review RED `6c2fe061...`; review-fix GREEN `1263be3a...` / CI `33692911228`.
- Task 3: RED `5379d8b40341bbccbfbb2016d1e7386c0089bd77` / CI `33697098324`; core GREEN `87dd33be...`; HTML/XML review regressions closed through `383a5a25...` and `442063e4...`.
- Task 4: PDF decode-memory review RED `15863a76e7d9358a486e310a6f60ef06a921467c` / CI `33708944268`; GREEN `0b5f99da94316a091e7e33711808bc774a7ad25f` / CI `33709090219`.
- Task 5: RED `4ef502da17ccfc687348487c0864ae55e5b08470` / CI `33713028193`; initial GREEN `7a923d7b...`; structured-fallback review RED `3b98b20d...`; GREEN `c5de4345...`; final coverage `00b3b88a...` / CI `33713788205`.
- Task 6: wiring RED `cfe94249c313405463019500be37204c95f41a03` / CI `33716566022`; GREEN `903de635dac1c0a57ecd7325c9945f5fdd6abdd7` / CI `33716829864`.
- Task 7 found no new behavior defect and therefore did not manufacture a redundant RED/GREEN cycle. Whole-milestone review finished at **0 Critical / 0 Important** unresolved findings.

## Integration verification
Documentation-complete feature head `7b360ec11eeeafe5ccbd9b3036695e489b038178`, CI `33720708730`:
- `php-quality` ✅
- `js-quality` ✅
- `package` ✅
- `wordpress-smoke` ✅, including activation/database/providers/knowledge/file-ingestion
- Package artifact `9880129959`, 702,886 bytes, digest `sha256:8ed015801f93525f52377d94358a4a24347a1ceacd371f3595fd9243092c8bc5`.

PR #6 merged that exact tested head as `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6`.

Fresh post-merge `main` CI `33721014064` passed all four permanent jobs, including the full real WordPress smoke. Post-merge artifact `9880230645`, 702,891 bytes, digest `sha256:13e6a28f3f779c9cb4a44fe7f2c9e79e8d9ee81e7cda2905d7ea47336ba6ea89`.

## Final review
Final traversal/symlink/MIME/resource/XML/parser-path/executable-content and synchronous-extraction review: **0 Critical / 0 Important** unresolved. Synchronous ingestion is intentionally bounded; larger/background ingestion remains owned by later queue policy.

## Next milestone
M06 — WooCommerce Knowledge Ingestion. Exact next action: recover latest `main`, read M06 roadmap/dependencies and existing WooCommerce/runtime contracts, then create and auto-review the M06 design/spec and executable implementation plan before starting its first strict-TDD task.
