# Global Status

- Current milestone: **M05 — File/Document Ingestion — IN PROGRESS**.
- Current branch: `feat/m05-file-document-ingestion`.
- Completed milestones: **M00-M04** are integrated on `main`.
- M05 design/spec: `docs/superpowers/specs/2026-09-03-m05-file-document-ingestion-design.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- M05 implementation plan: `docs/superpowers/plans/2026-09-03-m05-file-document-ingestion.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- M05 Tasks **1–6 are COMPLETE**; Task 7 remains.

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
- Valid primary RED: `5379d8b40341bbccbfbb2016d1e7386c0089bd77`, CI `33697098324`; PHPStan no errors; PHPUnit **201 / 950 / 17 failures**, all exactly from the six absent Task 3 extractor contracts.
- Initial PHP GREEN: `87dd33bec3a319504c31749509c6edcf12b240e4`, CI `33697603796`; PHPStan no errors; PHPUnit **201 / 1001**; Composer audit clean; package passed.
- Requirements/security review found generic visible HTML loss and a Markdown failure-coverage gap. Review RED `cd41be2cebe6e810f139aceefca12b568d5d6b12`, CI `33697784766`, reproduced the HTML defect; HTML fix converged at `383a5a25045bb6e25c50aac30383254c69766877`, CI `33698148849`, with **203 / 1007** passing.
- Final bounded second pass found XML mixed-content loss. Valid XML review RED `89d0eaf4864a8b07fb00fa6de9165d6b3dbef98c`, CI `33698299866`; review-fix GREEN `442063e463885cde958ceec47cd991c01ac4d917`, CI `33698452380`; PHPUnit **204 / 1009**, Composer audit clean.
- Final Task 3 review: **0 Critical / 0 Important** remaining.

## M05 Task 4 — PDF and DOCX parser adapters
- Added isolated `smalot/pdfparser` and `phpoffice/phpword` adapters, `DocxArchiveInspector`, package/runtime assertions, and parser-specific resource ceilings.
- PDF defaults: <=200 pages, <=2 MiB normalized text, <=8 MiB compressed-stream decode memory, image retention disabled. DOCX defaults: <=1,000 ZIP entries and <=20 MiB aggregate uncompressed bytes before PHPWord parsing.
- Independent Task 4 review found **0 Critical / 1 Important** parser-side decode-memory gap.
- Review-fix RED: `15863a76e7d9358a486e310a6f60ef06a921467c`, CI `33708944268`; PHPStan no errors; PHPUnit **216 / 1050 / 4 failures**, all caused by missing `maxDecodeBytes` behavior.
- Review-fix GREEN: `0b5f99da94316a091e7e33711808bc774a7ad25f`, CI `33709090219`; PHPStan no errors; PHPUnit **216 / 1053**; Composer audit clean; all permanent jobs green.
- Artifact `9876293491`, 700,875 bytes, digest `sha256:4288efcad7b7bbaffbcc5a0f5731734992cee6437c3bc8e47cca08dd0f8957cf`.
- Dependency review: `phpoffice/phpword 1.4.0` LGPL-3.0-only; `smalot/pdfparser v2.12.5` LGPL-3.0; transitive `phpoffice/math 0.3.0` MIT.
- Final Task 4 review: **0 Critical / 0 Important** remaining.

## M05 Task 5 — File knowledge source and bootstrap
- Added `FileDocumentSource` and registered native stable source type `file` in `KnowledgeBootstrap` while preserving the M04 `wp_rag_ai_chatbot_knowledge_sources` extension semantics.
- File sources validate before parser dispatch, normalize to stable key `file:{sourceKey}`, derive source version from file SHA-256 plus size, hash canonical content with `DocumentHasher`, preserve language/visibility, and emit traceable file/parser metadata.
- Valid primary RED: `4ef502da17ccfc687348487c0864ae55e5b08470`, CI `33713028193`; PHPStan clean; PHPUnit **220 / 1056 / 5 failures**, exactly for absent file-source/bootstrap behavior.
- Initial behavioral GREEN: `7a923d7bc9788e66cb8b7de7e6c9351659e05d45`, CI `33713283997`; PHPStan clean; PHPUnit **220 / 1092**; Composer audit clean.
- Independent review found **0 Critical / 1 Important** structured text fallback-dispatch issue: approved `text/plain` fallback for JSON/CSV/Markdown could route through the generic text parser and bypass format-specific validation/resource controls.
- Review RED: `3b98b20df2ba64b5a4f24485265034086c8b2198`, CI `33713400467`; PHPStan clean; PHPUnit **221 / 1096 / 1 failure**, exactly generic text output instead of JSON parser output.
- Review-fix GREEN: `c5de4345bad914786f2ed3ddd64651f8a5c2ec56`, CI `33713508805`; PHPStan clean; PHPUnit **221 / 1097**; Composer audit clean; all permanent jobs passed.
- Plan-required unsupported-file source coverage was added; the first coverage SHA `264a13c1...` found only an assertion-message mismatch against the already-fail-closed validator. Corrected coverage head `00b3b88ac6a9b57d964ba2ee33035a45439f6d69`, CI `33713788205`: PHPStan clean; PHPUnit **222 / 1102**; Composer audit clean; `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed.
- Artifact `9877799511`, 702,888 bytes, digest `sha256:f8d00eeffd5c1a828e3763508973c5bef5a9e9bbe777d3984340f34498f89983`.
- Final Task 5 review: **0 Critical / 0 Important** remaining. PR #6 has no submitted reviews or unresolved review threads at Task 5 closeout.

## M05 Task 6 — Real WordPress file-ingestion smoke coverage
- Added `scripts/test-wp-file-ingestion.php` and `.sh`, exposed `npm run test:wp:file-ingestion`, and wired it into the permanent `wordpress-smoke` job after the existing activation/database/provider/knowledge smokes.
- The real WordPress smoke creates TXT/JSON fixtures via `wp_upload_bits`, persists attachment records with `wp_insert_attachment`, resolves the native `file` source from `KnowledgeBootstrap`, and verifies extraction, stable document key/source version/content hash, validated file SHA-256/filename metadata, malformed JSON rejection, server-side MIME spoof rejection, and cleanup.
- Task-level wiring RED: `cfe94249c313405463019500be37204c95f41a03`, CI `33716566022`; `php-quality`, `js-quality`, and `package` passed, all pre-existing WordPress smokes passed, and `wordpress-smoke` failed only at `npm run test:wp:file-ingestion` with exit 127 because `scripts/test-wp-file-ingestion.sh` was intentionally absent.
- Smoke GREEN: `903de635dac1c0a57ecd7325c9945f5fdd6abdd7`, CI `33716829864`; `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed, including the new `test:wp:file-ingestion` step.
- Exact GREEN artifact: `9878819165`, 702,893 bytes, digest `sha256:35f201d0459a1ad35df5babc8c774ea23e93a5136db846b748fc52a9026d6a36`.
- No production defect was discovered by the real WordPress smoke, so no additional production regression/fix cycle was required.
- Bounded requirements/security cleanup review found **0 Critical / 0 Important** Task 6 findings.

## Current gates
- M05 is **not merge-ready** until Task 7 integration/security/performance/whole-milestone review and exact-head merge gates are complete. Do not advance to M06 yet.
- No known technical blocker.
- Draft feature PR: **#6 — `feat: build M05 file/document ingestion`**.
- Exact next unfinished action: execute **M05 Task 7 — Integration, security review, documentation, and merge**. Reconcile the branch with latest `main`, perform final traversal/symlink/MIME/resource/XML/parser-path/executable-content security review and synchronous extraction performance review, perform an independent whole-milestone review, fix all Critical/Important findings with regression TDD where behavior changes, synchronize milestone/test/security/progress docs and PR #6, require all permanent CI jobs green on the exact final SHA, merge only that tested head, then verify fresh post-merge `main` CI before marking M05 complete.

## Previous milestone closeout
- M04 feature PR: #4 `feat: build M04 WordPress knowledge source framework`.
- M04 feature merge: `666bb02dc6780f2fb3c818bbbf4d3fe1a0778555`.
- M04 final reconciled feature head: `f09f293c913203bdc498e76a28413e3ab2614f5c`; exact-head CI `33687964210` passed `php-quality`, `js-quality`, `package`, and `wordpress-smoke`, including activation/database/provider/knowledge smoke. Artifact `9868887017`, 75,694 bytes, digest `sha256:0bb2f525d4ae4c37a0d69d7f9d02716b94868e5db9f07a0d8362918b6c47904e`.
- M04 post-merge `main` CI: run `33688297306` on `666bb02dc6780f2fb3c818bbbf4d3fe1a0778555`; all four permanent jobs passed, including `npm run test:wp:knowledge` in real WordPress. Post-merge artifact `9869009969`, 75,695 bytes, digest `sha256:5d5e69b131b33a87614dfb86ef228e5fd3c168065b077e1bfbaf2af16ec82590`.
- M04 durable closeout: `docs/progress/M04-CLOSEOUT.md`.
