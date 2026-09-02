# Global Status

- Current milestone: **M05 — File/Document Ingestion — IN PROGRESS**.
- Current branch: `feat/m05-file-document-ingestion`.
- Completed milestones: **M00-M04** are integrated on `main`.
- M05 design/spec: `docs/superpowers/specs/2026-09-03-m05-file-document-ingestion-design.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- M05 implementation plan: `docs/superpowers/plans/2026-09-03-m05-file-document-ingestion.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- M05 Tasks **1–2 are COMPLETE**; Tasks 3–7 remain.

## M05 Task 1 — Extraction contracts and registry
- Added `ValidatedFile`, `ExtractedDocument`, `DocumentExtractor`, `DocumentExtractorRegistry`, and `ExtractionException`.
- Behavioral RED: `71bcc9dc5545575f410416d44a613c1b60ec5e44`, CI `33690105092`; PHPStan no errors; PHPUnit **172 tests / 872 assertions / 5 intentional missing-contract failures**.
- Implementation GREEN: `73a442de5254074630273708504b088fe5e31bd1`, CI `33690461154`; PHPStan no errors, PHPUnit **172 / 883**, Composer audit clean, JS/package gates green.
- Independent review: **0 Critical / 1 Important** coverage gap; fixed at `7a1d1fc6e2c4386f93b4f547f5ba926d963ec150`, then **175 / 889** passing. Remaining Critical/Important: **0 / 0**.
- Final Task 1 handoff SHA `e5c3a8da89c09591a69c29da045723c982f4eb23`; CI `33690878698` passed all permanent jobs.

## M05 Task 2 — File validation policy
- Added `MimeTypeDetector`, `NativeMimeTypeDetector`, and `FileValidationPolicy`.
- Trust boundary now canonicalizes local paths, requires readable regular non-empty files, defaults to a 10 MiB ceiling, enforces explicit extension/MIME agreement using server-side `finfo`, prevents canonical allowed-root and symlink escape, and records deterministic lowercase SHA-256 in `ValidatedFile`.
- Client MIME is not an input and is never trusted. Unsupported extensions/MIME pairs fail closed.
- First test SHA `bb7e15f...` failed WPCS before PHPUnit and is **not** behavioral RED.
- Valid Task 2 RED: `874a2e5d2dc901d79175806f8b5c37a4c2a5ae73`, CI `33692375663`; PHPStan no errors; PHPUnit **183 / 897 / 8 failures**, all exactly because Task 2 contracts were absent.
- Initial behavioral GREEN: `0c844673f6c374161f8cd5634223520c249ea1b3`, CI `33692560859`; PHPStan no errors; PHPUnit **183 / 929**, Composer audit clean; PHP/JS/package green. The later review-fix head supersedes it for final Task 2 verification.
- Distinct second-pass review found **0 Critical / 1 Important** API issue: approved public signature requires named parameter `$allowedRoot`, while the initial implementation exposed `$allowed_root`.
- Review regression RED: `6c2fe061dc6959001d91044f828113ec49de0c62`, CI `33692753424`; PHPStan no errors; PHPUnit **184 / 932 / 1 error**, exactly `Unknown named parameter $allowedRoot`.
- Review-fix code GREEN: `1263be3a9e7e80688cccc7234b342ce97c67c24c`, CI `33692911228`; PHPStan no errors; PHPUnit **184 / 933**, Composer audit clean. Remaining Critical/Important: **0 / 0**.
- SHA `c06ae273...` failed WPCS before PHPUnit due suppression placement and is explicitly not GREEN evidence.
- Documentation head `87e56c6fc76f829918c8bf3cef449a3c1c422343`, CI `33693060308`, passed **php-quality, js-quality, package/artifact upload, and wordpress-smoke**, including activation/database/provider/knowledge smoke. This closes the Task 2 handoff gate.

## Current gates
- M05 is **not merge-ready**: Tasks 3–7 remain. Do not merge the branch or advance to M06.
- No known blockers.
- Draft feature PR: **#6 — `feat: build M05 file/document ingestion`**.
- Exact next unfinished action: execute **M05 Task 3 — Core text extractors**. Add focused fixtures/tests for TXT, Markdown, HTML, CSV, JSON, and XML including malformed/empty/complexity failure paths and deterministic normalized output; capture genuine behavioral RED before production code; implement minimum bounded parsers, disable XML external-entity/network access, register supported MIME ownership, run full GREEN/security review, update durable docs, and require all permanent exact-head CI jobs green.

## Previous milestone closeout

- M04 feature PR: #4 `feat: build M04 WordPress knowledge source framework`.
- M04 feature merge: `666bb02dc6780f2fb3c818bbbf4d3fe1a0778555`.
- M04 final reconciled feature head: `f09f293c913203bdc498e76a28413e3ab2614f5c`; exact-head CI `33687964210` passed `php-quality`, `js-quality`, `package`, and `wordpress-smoke`, including activation/database/provider/knowledge smoke. Artifact `9868887017`, 75,694 bytes, digest `sha256:0bb2f525d4ae4c37a0d69d7f9d02716b94868e5db9f07a0d8362918b6c47904e`.
- M04 post-merge `main` CI: run `33688297306` on `666bb02dc6780f2fb3c818bbbf4d3fe1a0778555`; all four permanent jobs passed, including `npm run test:wp:knowledge` in real WordPress. Post-merge artifact `9869009969`, 75,695 bytes, digest `sha256:5d5e69b131b33a87614dfb86ef228e5fd3c168065b077e1bfbaf2af16ec82590`.
- M04 durable closeout: `docs/progress/M04-CLOSEOUT.md`.
