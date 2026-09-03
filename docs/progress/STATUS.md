# Global Status

- Current milestone: **M05 — File/Document Ingestion — IN PROGRESS**.
- Current branch: `feat/m05-file-document-ingestion`.
- Completed milestones: **M00-M04** are integrated on `main`.
- M05 design/spec: `docs/superpowers/specs/2026-09-03-m05-file-document-ingestion-design.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- M05 implementation plan: `docs/superpowers/plans/2026-09-03-m05-file-document-ingestion.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- M05 Tasks **1–3 are COMPLETE**; Tasks 4–7 remain.

## M05 Task 1 — Extraction contracts and registry
- Added `ValidatedFile`, `ExtractedDocument`, `DocumentExtractor`, `DocumentExtractorRegistry`, and `ExtractionException`.
- Behavioral RED: `71bcc9dc5545575f410416d44a613c1b60ec5e44`, CI `33690105092`; PHPStan no errors; PHPUnit **172 tests / 872 assertions / 5 intentional missing-contract failures**.
- Implementation GREEN: `73a442de5254074630273708504b088fe5e31bd1`, CI `33690461154`; PHPStan no errors, PHPUnit **172 / 883**, Composer audit clean, JS/package gates green.
- Independent review: **0 Critical / 1 Important** coverage gap; fixed at `7a1d1fc6e2c4386f93b4f547f5ba926d963ec150`, then **175 / 889** passing. Remaining Critical/Important: **0 / 0**.
- Final Task 1 handoff SHA `e5c3a8da89c09591a69c29da045723c982f4eb23`; CI `33690878698` passed all permanent jobs.

## M05 Task 2 — File validation policy
- Added `MimeTypeDetector`, `NativeMimeTypeDetector`, and `FileValidationPolicy`.
- Trust boundary canonicalizes paths, requires readable regular non-empty files, defaults to a 10 MiB ceiling, enforces extension/MIME agreement using server-side `finfo`, prevents canonical allowed-root/symlink escape, and records deterministic lowercase SHA-256.
- Valid RED: `874a2e5d2dc901d79175806f8b5c37a4c2a5ae73`, CI `33692375663`; PHPStan no errors; PHPUnit **183 / 897 / 8 failures** caused by absent Task 2 contracts.
- Initial GREEN: `0c844673f6c374161f8cd5634223520c249ea1b3`, CI `33692560859`; PHPStan no errors; PHPUnit **183 / 929**; Composer audit clean.
- Review found **0 Critical / 1 Important** named-argument API issue. Review RED `6c2fe061dc6959001d91044f828113ec49de0c62`, CI `33692753424`: **184 / 932 / 1 error**, exactly `Unknown named parameter $allowedRoot`.
- Review-fix GREEN: `1263be3a9e7e80688cccc7234b342ce97c67c24c`, CI `33692911228`; PHPStan no errors; PHPUnit **184 / 933**; Composer audit clean. Remaining Critical/Important: **0 / 0**.
- Documentation head `87e56c6fc76f829918c8bf3cef449a3c1c422343`, CI `33693060308`, passed all permanent jobs.

## M05 Task 3 — Core text extractors
- Added bounded deterministic TXT, Markdown, HTML, CSV, JSON, and XML extractors and MIME ownership through the existing registry.
- TXT/Markdown normalize UTF-8 text and reject null-byte/binary content. HTML strips scripts/styles/comments, retains visible generic-container content and caps DOM elements at 5,000. CSV streams with limits of 1,000 rows/100 columns. JSON caps depth at 64. XML rejects `DOCTYPE`, uses `LIBXML_NONET`, caps depth at 64, and preserves mixed visible content.
- Initial test SHA `c8af88a5...` stopped at WPCS and is **not** behavioral RED.
- Valid primary RED: `5379d8b40341bbccbfbb2016d1e7386c0089bd77`, CI `33697098324`; PHPStan no errors; PHPUnit **201 / 950 / 17 failures**, all exactly from the six absent Task 3 extractor contracts.
- Initial implementation needed only WPCS/static-analysis corrections. At `87dd33bec3a319504c31749509c6edcf12b240e4`, CI `33697603796` reached PHP GREEN: PHPStan no errors; PHPUnit **201 / 1001**; Composer audit clean; package passed.
- Independent requirements/security review found **0 Critical / 2 Important** issues: generic visible HTML container text was discarded and Markdown failure behavior lacked focused regression coverage.
- Review RED: `cd41be2cebe6e810f139aceefca12b568d5d6b12`, CI `33697784766`; PHPStan no errors; PHPUnit **203 / 1006 / 1 error**, exactly the HTML visible-text loss. Markdown binary rejection regression passed.
- HTML review fix converged at `383a5a25045bb6e25c50aac30383254c69766877`, CI `33698148849`; PHPStan no errors; PHPUnit **203 / 1007**; Composer audit clean.
- Final bounded second pass found **0 Critical / 1 Important** XML mixed-content issue. First test SHA `4f0adcd9...` stopped at WPCS and is not RED. Valid XML review RED: `89d0eaf4864a8b07fb00fa6de9165d6b3dbef98c`, CI `33698299866`; PHPStan no errors; PHPUnit **204 / 1009 / 1 failure**, exactly expected `Hello world!` vs actual `world`.
- XML review-fix GREEN: `442063e463885cde958ceec47cd991c01ac4d917`, CI `33698452380`; PHPStan no errors; PHPUnit **204 / 1009**; Composer audit clean.
- Final Task 3 review after fixes: **0 Critical / 0 Important** remaining.

## Current gates
- M05 is **not merge-ready**: Tasks 4–7 remain. Do not merge the branch or advance to M06.
- No known blockers.
- Draft feature PR: **#6 — `feat: build M05 file/document ingestion`**.
- Exact next unfinished action: execute **M05 Task 4 — PDF and DOCX parser adapters**. Add test fixtures first for valid extraction, malformed failure normalization, unsupported/password-protected PDF behavior, and DOCX archive entry/uncompressed-size resource limits; capture genuine behavioral RED before dependencies/adapters exist; then add PHP 8.2-compatible `smalot/pdfparser` and `phpoffice/phpword`, keep parser libraries behind adapters, inspect DOCX ZIP resource limits before PHPWord parsing, normalize parser exceptions without leaking paths, run Composer audit/full verification/package/permanent CI, and record dependency license/security evidence.

## Previous milestone closeout

- M04 feature PR: #4 `feat: build M04 WordPress knowledge source framework`.
- M04 feature merge: `666bb02dc6780f2fb3c818bbbf4d3fe1a0778555`.
- M04 final reconciled feature head: `f09f293c913203bdc498e76a28413e3ab2614f5c`; exact-head CI `33687964210` passed `php-quality`, `js-quality`, `package`, and `wordpress-smoke`, including activation/database/provider/knowledge smoke. Artifact `9868887017`, 75,694 bytes, digest `sha256:0bb2f525d4ae4c37a0d69d7f9d02716b94868e5db9f07a0d8362918b6c47904e`.
- M04 post-merge `main` CI: run `33688297306` on `666bb02dc6780f2fb3c818bbbf4d3fe1a0778555`; all four permanent jobs passed, including `npm run test:wp:knowledge` in real WordPress. Post-merge artifact `9869009969`, 75,695 bytes, digest `sha256:5d5e69b131b33a87614dfb86ef228e5fd3c168065b077e1bfbaf2af16ec82590`.
- M04 durable closeout: `docs/progress/M04-CLOSEOUT.md`.
