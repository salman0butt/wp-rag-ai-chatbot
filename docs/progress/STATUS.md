# Global Status

- Current milestone: **M05 — File/Document Ingestion — IN PROGRESS**.
- Current branch: `feat/m05-file-document-ingestion`.
- Completed milestones: **M00-M04** are integrated on `main`.
- M05 design/spec: `docs/superpowers/specs/2026-09-03-m05-file-document-ingestion-design.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- M05 implementation plan: `docs/superpowers/plans/2026-09-03-m05-file-document-ingestion.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- M05 Task 1 — **Extraction contracts and registry — COMPLETE pending final documentation-head CI confirmation**. Added `ValidatedFile`, `ExtractedDocument`, `DocumentExtractor`, `DocumentExtractorRegistry`, and `ExtractionException`.
- Task 1 behavioral RED: SHA `71bcc9dc5545575f410416d44a613c1b60ec5e44`, CI `33690105092`; PHPStan reached no errors and PHPUnit produced **172 tests / 872 assertions / 5 intentional missing-contract failures**. Earlier WPCS-only failures are not counted as behavioral RED.
- Task 1 implementation GREEN: SHA `73a442de5254074630273708504b088fe5e31bd1`, CI `33690461154`; PHPStan no errors, PHPUnit **172 / 883**, Composer audit clean, JS quality and package/artifact gates green.
- Task 1 independent review: **0 Critical**, **1 Important** coverage gap (invalid SHA-256 and empty/blank MIME ownership invariants lacked direct tests). Fixed at `7a1d1fc6e2c4386f93b4f547f5ba926d963ec150`; PHP verification then passed with **175 tests / 889 assertions**, PHPStan no errors, Composer audit clean. Remaining Critical/Important: **0 / 0**.
- M05 is **not merge-ready**: Tasks 2–7 remain. Do not merge the branch or advance to M06.
- Blocked items: none.
- Exact next unfinished action: execute **M05 Task 2 — File validation policy**. Add `MimeTypeDetector`/`FileValidationPolicy` tests for readable regular files, empty input, >10 MiB, unsupported extension, MIME spoofing, allowed-root traversal/symlink escape, and lowercase SHA-256; capture genuine behavioral RED before production code; then implement the minimum server-side `finfo` detector and explicit extension/MIME allow-list, followed by full GREEN, security review, and exact-head permanent CI.

## Previous milestone closeout

- M04 feature PR: #4 `feat: build M04 WordPress knowledge source framework`.
- M04 feature merge: `666bb02dc6780f2fb3c818bbbf4d3fe1a0778555`.
- M04 final reconciled feature head: `f09f293c913203bdc498e76a28413e3ab2614f5c`; exact-head CI `33687964210` passed `php-quality`, `js-quality`, `package`, and `wordpress-smoke`, including activation/database/provider/knowledge smoke. Artifact `9868887017`, 75,694 bytes, digest `sha256:0bb2f525d4ae4c37a0d69d7f9d02716b94868e5db9f07a0d8362918b6c47904e`.
- M04 post-merge `main` CI: run `33688297306` on `666bb02dc6780f2fb3c818bbbf4d3fe1a0778555`; all four permanent jobs passed, including `npm run test:wp:knowledge` in real WordPress. Post-merge artifact `9869009969`, 75,695 bytes, digest `sha256:5d5e69b131b33a87614dfb86ef228e5fd3c168065b077e1bfbaf2af16ec82590`.
- M04 durable closeout: `docs/progress/M04-CLOSEOUT.md`.
