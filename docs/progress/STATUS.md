# Global Status

- Current milestone: **M05 — File/Document Ingestion — COMPLETE ON FEATURE BRANCH; pending exact-head merge/post-merge verification**.
- Current branch: `feat/m05-file-document-ingestion`.
- Completed milestones on `main`: **M00-M04**.
- M05 design/spec: `docs/superpowers/specs/2026-09-03-m05-file-document-ingestion-design.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- M05 implementation plan: `docs/superpowers/plans/2026-09-03-m05-file-document-ingestion.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- M05 Tasks **1–7 are complete on the feature branch**; merge/post-merge CI is the remaining integration gate.

## M05 delivered scope
- Deterministic extraction contracts/registry and immutable validated/extracted values.
- File validation boundary: readable regular non-empty files, default 10 MiB limit, server-side `finfo`, extension/MIME agreement, canonical allowed-root/symlink containment, SHA-256 metadata.
- Bounded TXT/Markdown/HTML/CSV/JSON/XML/PDF/DOCX extractors.
- PDF decode/page/text ceilings and DOCX ZIP entry/uncompressed-byte ceilings.
- Native `file` knowledge source through `KnowledgeBootstrap` with stable key/version/hash and traceable metadata.
- Permanent real WordPress file-ingestion smoke in CI.

## TDD and review summary
- Task 1 RED `71bcc9dc...` / GREEN `73a442de...`; review coverage fix `7a1d1fc...`.
- Task 2 RED `874a2e5d...` / GREEN `0c844673...`; named-argument review RED `6c2fe061...` / GREEN `1263be3a...`.
- Task 3 RED `5379d8b4...` / GREEN `87dd33be...`; HTML and XML review regressions fixed through `383a5a25...` and `442063e4...`.
- Task 4 PDF decode-memory review RED `15863a76...` / GREEN `0b5f99da...`.
- Task 5 RED `4ef502da...` / GREEN `7a923d7b...`; structured fallback review RED `3b98b20d...` / GREEN `c5de4345...`; final unsupported-file coverage `00b3b88a...`.
- Task 6 wiring RED `cfe94249...` / GREEN `903de635...` with real WordPress file-ingestion smoke.
- Task 7 whole-milestone security/performance/review second pass: **0 Critical / 0 Important** unresolved findings; no production behavior defect found, so no new Task 7 behavioral RED/GREEN cycle was required.

## Pre-merge evidence
Pre-documentation candidate `0dda3fc090248f16d012361298266a1584648789`, CI `33717095588`:
- PHPStan: **0 errors**.
- PHPUnit: **222 tests / 1102 assertions**.
- Composer audit: **no advisories**.
- Permanent jobs: `php-quality`, `js-quality`, `package`, `wordpress-smoke` all passed.
- WordPress smoke includes activation, database, providers, knowledge, and file ingestion.

Detailed milestone evidence: `docs/milestones/M05-file-document-ingestion.md`.
Final integration evidence: `docs/progress/M05-CLOSEOUT.md`.

## Current gates
- PR #6 — `feat: build M05 file/document ingestion`.
- Exact remaining action: require all permanent jobs green on the new documentation head, inspect its package artifact, merge only that exact tested SHA, then verify fresh post-merge `main` CI. After successful post-merge verification, mark M05 integrated and begin M06 in a later coherent unit.

## Previous milestone closeout
- M04 feature merge: `666bb02dc6780f2fb3c818bbbf4d3fe1a0778555`.
- M04 post-merge CI: `33688297306`, all permanent jobs passed.
- M04 durable closeout: `docs/progress/M04-CLOSEOUT.md`.
