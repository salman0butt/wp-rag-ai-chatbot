# M04 — WordPress Knowledge Source Framework

Status: IMPLEMENTATION COMPLETE — final reconciled exact-head CI, PR merge, and post-merge `main` verification pending.

## Goal
Normalize selected WordPress content into traceable knowledge Documents through extensible source contracts.

## Dependencies
M02. M03 is complete, integrated, and documentation-closed on recovered `main` SHA `65c1aa9a7081ac0d831bb118a0cd445b4f5ba5e1`.

## Scope Delivered
- `KnowledgeSource` contract, fail-closed registry, and deterministic `DocumentHasher`.
- Deterministic manual-text and FAQ sources.
- WordPress global API isolation through `WordPressContentGateway` and immutable `WordPressPost` values.
- Native WordPress adapter for public post types, bounded pages, canonical permalinks, status/access data, sanitized text, author data, and taxonomy labels.
- `WordPressPostSource` for posts/pages/configured public CPTs with stable key/version/hash/access metadata.
- `KnowledgeBootstrap` native registry composition and validated `wp_rag_ai_chatbot_knowledge_sources` extension hook.
- Permanent real-WordPress knowledge lifecycle smoke in CI.

## Out of Scope Preserved
No file parsing (M05), WooCommerce specialization (M06), chunking/indexing (M07), embeddings/vector stores (M08), public knowledge REST/UI, outbound remote crawling, or model invocation was introduced.

## Design / Plan
- Design: `docs/superpowers/specs/2026-09-03-m04-wordpress-knowledge-sources-design.md`.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m04-wordpress-knowledge-sources.md`.
- Both were self-reviewed and marked `AUTO-APPROVED — SCHEDULED MODE` under repository autonomous-development policy.

## Task Status
- Task 1 — source contract, registry, deterministic hashing: complete.
- Task 2 — manual text source: complete.
- Task 3 — FAQ source: complete; Important partial-yield finding fixed with regression.
- Task 4 — WordPress content gateway: complete.
- Task 5 — WordPress post/page/public-CPT source: complete; adapter sanitization regression fixed.
- Task 6 — registry bootstrap/extension hook: complete.
- Task 7 — real WordPress knowledge smoke: complete and exact-SHA green.
- Task 8 — full review/security/performance/durable evidence: complete; final reconciled exact-head CI/PR integration/post-merge verification remain as evidence gates.

## TDD Evidence

### Task 1
- RED `5108cb315ead4e7213c033d0e094c49978e8da02`, CI `33671328652`: PHP quality reached PHPUnit with expected missing-class failures.
- GREEN `5cdc7d105a3a80f601b826b6ccad135a862d1f61`, CI `33671611425`.

### Task 2
- RED `93e3be47058777c332cf3d31417b89c191f1020b`.
- GREEN `a1813a695f59193cb6e48615aa9daf2915aafa8d`, CI `33672126600`: PHPUnit `148 tests / 777 assertions`; Composer audit clean.

### Task 3
- Valid RED `551d1690b83b43cf0b6f8f589ce3f9d8ed3b8e25`, CI `33673099461`: `153 tests / 782 assertions / 5 failures`, all expected because `FaqSource` was missing.
- Initial GREEN `96f44569d2478d6a6631ac44fde30026f657dc29`, CI `33673225337`: `153 tests / 798 assertions`.
- Review found an Important partial-yield integrity issue: an earlier valid FAQ could be consumed before a later malformed item threw.
- Review-fix RED/GREEN `9417ad1d80f0855f5db4ff066eae4b9ac4caf474` / `918ca7558d100b2e5076924bc62937886b368ab2`: validation now completes before first yield; GREEN `154 tests / 800 assertions`.

### Task 4
- Valid RED `0bf623faf5b4f00fde0aa3fd0ad31a7dfb50e6f3`, CI `33675981175`: `158 tests / 804 assertions / 4 failures`, all expected because `NativeWordPressContentGateway` was missing.
- GREEN `8a9ceb2fb6021a5f87bba460ef43eca4bec10b81`, CI `33676782328`: `158 tests / 822 assertions`; all permanent jobs green.

### Task 5
- Valid source RED `5c074d7701a3946099f9f7b9f4804b421f4405bc`, CI `33682245257`: `164 tests / 828 assertions / 6 failures`, all expected because `WordPressPostSource` was missing.
- Core source GREEN `2550b1e74a2c25b73dcb0bb189bbf9f867c73fc2`, CI `33682710773`: `164 tests / 858 assertions`.
- Adapter sanitization RED `0d5d9576fd794820ef7a9671d325e3134e21277d`, CI `33682842361`: `165 tests / 860 assertions / 1 failure`, proving raw WordPress HTML crossed the native adapter boundary.
- Final Task 5 GREEN `5a00b73b3e01417986e02583351d21b907093490`, CI `33683221417`: PHPStan clean, PHPUnit `165 tests / 862 assertions`, Composer audit clean, all permanent jobs green. Artifact `9867077987`, 74,913 bytes, digest `sha256:2442a81d6114242c2ac233f3a11f815b4f940cbadc840f8a89cfdec2a5e3547d`.

### Task 6
- Valid RED `8b5076468f9b5f2d490c97aa44edc884f9a3d269`, CI `33684412647`: PHPUnit `167 tests / 861 assertions` with one error and two failures caused by intentionally missing `KnowledgeBootstrap` behavior.
- GREEN `5289e17f299af079de7d3ffdcfcfc42088c6c159`, CI `33684501524`: PHPStan no errors, PHPUnit `167 tests / 867 assertions`, Composer audit clean, all permanent jobs green.
- Documentation head `c3e3612ea0112ab543e14395f0db127739701e26`, CI `33685034733`, passed all permanent jobs.

### Task 7
Task 7 is an integration-only acceptance slice after Tasks 1-6 production behavior. The approved plan explicitly permits its real-WordPress smoke to be GREEN immediately; only a production defect discovered by integration would require a new unit RED first. No defect was discovered, so no RED is fabricated.

- Commit `aa246186a218efa7208403c36fecd051c6c143ee` — `test: cover WordPress knowledge normalization`.
- CI `33687296386`: `php-quality`, `js-quality`, `wordpress-smoke`, and `package` all passed.
- `wordpress-smoke` passed activation, database, provider, and new `npm run test:wp:knowledge` steps.
- Artifact `9868623773`, 75,709 bytes, digest `sha256:41f285035d298187635bfbfd9d9f8aff828003439384979503ba37e84b8b3fbf`.

## Real WordPress Knowledge Smoke
The permanent smoke creates a published page, published post, private post, draft, and password-protected published post and verifies:
- published post/page normalization;
- private exclusion by default and inclusion only by explicit opt-in, retaining private visibility;
- draft and password-protected exclusion even with private opt-in;
- WordPress HTML converted to normalized text;
- canonical URL equals WordPress permalink;
- external ID is traceable to WordPress post ID;
- stable document key and content hash across equivalent repeated reads;
- fixture cleanup on success and failure.

## Architecture / Security Review
Final milestone-wide second pass covered source contracts, deterministic hashing, extension boundaries, WordPress visibility/status behavior, sanitization, canonical URLs, arbitrary metadata exposure, partial emission, pagination, package/CI integration, and scope leakage.

Findings:
- Critical: none.
- Important fixed: FAQ partial-yield integrity (Task 3).
- Important fixed: raw WordPress markup crossing the adapter boundary (Task 5).
- Important unresolved: none.
- PR #4 has no submitted reviews and no unresolved inline review threads at Task 8 review.

A separate reviewer/subagent is not available in this runtime; the review was performed as an isolated second pass against the approved design/plan, implementation, tests, real WordPress behavior, exact-SHA CI, and security/performance boundaries. It is not represented as external human approval.

## Security Boundaries
- No credentials, public REST endpoint, external model/vector call, file parser, remote crawl, or arbitrary post-meta ingestion added.
- Public WordPress post types only; published by default; private only by explicit opt-in; draft/pending/trash/password-protected content excluded.
- WordPress title/excerpt/content markup is stripped at the native adapter boundary.
- Extension hook accepts only `KnowledgeSource` implementations and registry state is published only after complete valid composition.
- Document hash includes canonical content, identity, URL, taxonomy/access metadata, version, language, and visibility.

## Performance Review
- WordPress enumeration uses bounded 100-record pages, stable ID ordering, and `no_found_rows`.
- No remote I/O is introduced by M04.
- Bootstrap performs one in-memory composition pass at plugin load.
- Taxonomy retrieval remains per-post. This is a documented Minor performance consideration; no batching complexity is introduced without evidence of a material issue.

## Accessibility Review
N/A; M04 introduces no UI.

## Minor Non-Blocking Follow-ups
- Some defensive invalid-source/config guards are enforced by production fail-closed logic/static review but do not each have a dedicated focused unit case.
- The non-array knowledge-extension filter guard is not separately isolated by a dedicated focused test; valid extension registration and invalid extension-item rejection are covered.
- Taxonomy term lookup may become an N+1 optimization target at larger scales; current source paging remains bounded.

## Branch Reconciliation
Before Task 8 reconciliation, the M04 branch was 60 commits ahead / 5 commits behind `main`. The five behind commits contain only M03 closeout documentation (`M03` milestone, `STATUS`, `SECURITY`, `TEST-MATRIX`) and no runtime code. The final Task 8 integration commit preserves current `main` M03 closeout evidence and combines it with M04 durable records through a two-parent merge state rather than rewriting history.

## Final Gate
M04 must not be marked fully complete until the reconciled documentation/integration head passes all permanent CI jobs, PR #4 is merged with exact-head protection, and fresh post-merge `main` CI passes. Post-merge evidence must then be recorded durably before M05 starts.
