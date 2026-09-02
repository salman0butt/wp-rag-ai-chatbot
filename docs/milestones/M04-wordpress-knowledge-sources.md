# M04 — WordPress Knowledge Source Framework

Status: IN PROGRESS

## Goal
Normalize selected WordPress content into traceable knowledge Documents through extensible source contracts.

## Dependencies
M02. M03 is integrated and documentation-closed on current recovered `main` SHA `65c1aa9a7081ac0d831bb118a0cd445b4f5ba5e1`; its post-closeout CI is green. Re-check `main` before each new unit and before integration.

## In Scope
Pages/posts/public CPTs; selected taxonomy metadata; manual text; FAQ/Q&A; selected URLs/sitemap configuration foundation where source boundary fits; source versions/hashes/access metadata; source registry/hooks.

## Out of Scope
File parsing (M05), WooCommerce specialization (M06), chunking/embeddings (M07-M08), and outbound remote crawling in M04.

## Architecture
`KnowledgeSource` contract -> canonical M02 `DocumentRecord` model with identity/title/URL/content/metadata/version/hash/language/visibility. WordPress global APIs are isolated behind `WordPressContentGateway` so source normalization remains deterministic and unit-testable.

Design: `docs/superpowers/specs/2026-09-03-m04-wordpress-knowledge-sources-design.md`.

Implementation plan: `docs/superpowers/plans/2026-09-03-m04-wordpress-knowledge-sources.md`.

Both are `AUTO-APPROVED — SCHEDULED MODE` after self-review under the repository autonomous-development procedure.

## Acceptance Criteria
Supported WP sources normalize deterministically; draft/private/role access policy is explicit; changes produce stable version/hash signals; source extension contract tested.

## Tasks

- Task 1 — source contract, registry, deterministic hashing: complete and verified.
- Task 2 — manual text source: complete and verified.
- Task 3 — FAQ source: complete; Important partial-yield review finding fixed with regression coverage and verified.
- Task 4 — WordPress content gateway: complete, reviewed, and exact-head CI verified.
- Task 5 — WordPress post/page/public-CPT source: complete, reviewed, and exact-head CI verified.
- Task 6 — registry bootstrap/extension hook: next unfinished task.
- Task 7 — real WordPress knowledge smoke coverage: pending.
- Task 8 — full review, verification, durable evidence, PR integration: pending.

## TDD Evidence

### Task 1
- RED `5108cb315ead4e7213c033d0e094c49978e8da02`, CI `33671328652`: PHPCS/PHPStan passed; PHPUnit `142 tests / 755 assertions`, 8 expected missing-class failures.
- GREEN `5cdc7d105a3a80f601b826b6ccad135a862d1f61`, CI `33671611425`: PHP verification passed.

### Task 2
- RED `93e3be47058777c332cf3d31417b89c191f1020b`, CI `33671716477`: PHPCS/PHPStan passed; PHPUnit `148 tests / 763 assertions`, 6 expected missing-source failures.
- GREEN `a1813a695f59193cb6e48615aa9daf2915aafa8d`, CI `33672126600`: PHPUnit `148 tests / 777 assertions`; Composer audit clean.

### Task 3
- Test attempts `8176c4d13fb491ccc1f87e0986186cf4eca084e1` and `9e7a2cff7398216aea108d417a01da28c0df49b4` stopped at test-only WPCS issues and are not behavioral RED.
- Valid RED `551d1690b83b43cf0b6f8f589ce3f9d8ed3b8e25`, CI `33673099461`: WPCS/PHPStan clean; PHPUnit `153 tests / 782 assertions / 5 failures`, all missing `FaqSource`.
- Initial GREEN `96f44569d2478d6a6631ac44fde30026f657dc29`, CI `33673225337`: PHPUnit `153 tests / 798 assertions`; Composer audit clean.
- Review found one Important partial-yield integrity issue.
- Review-fix RED/GREEN `9417ad1d80f0855f5db4ff066eae4b9ac4caf474` / `918ca7558d100b2e5076924bc62937886b368ab2`; GREEN PHPUnit `154 tests / 800 assertions`; Composer audit clean.

### Task 4
- Test-only `a1f18f25f718361fa4ba829b82aa5ded649a2ff1` stopped at WPCS before PHPUnit and is not behavioral RED.
- Valid RED `0bf623faf5b4f00fde0aa3fd0ad31a7dfb50e6f3`, CI `33675981175`: WPCS/PHPStan clean; PHPUnit `158 tests / 804 assertions / 4 failures`, all missing `NativeWordPressContentGateway`.
- Exact implementation GREEN `8a9ceb2fb6021a5f87bba460ef43eca4bec10b81`, CI `33676782328`: PHPStan clean; PHPUnit `158 tests / 822 assertions`; Composer audit clean; all permanent jobs green.

### Task 5
- Test-only `6b7d9e565e39ddec8074bf3f404b898e332696ae` (CI `33681833226`) and `c17842542819efa294593f0fa894a6562a1ec9bf` (CI `33681998427`) stopped at test WPCS before PHPUnit and are not behavioral RED.
- Valid source RED `5c074d7701a3946099f9f7b9f4804b421f4405bc`, CI `33682245257`: WPCS/PHPStan clean; PHPUnit `164 tests / 828 assertions / 6 failures`, all expected because `WordPressPostSource` did not exist.
- Initial source implementation `3900eb204141df50008e1da02e725c4c47de08ae` reached quality tooling; a WPCS loop-condition issue was corrected in `6e89a54525f342bf0a9051453c93ebea592b0c08`, then PHPStan's persisted-source-ID narrowing finding was corrected in `2550b1e74a2c25b73dcb0bb189bbf9f867c73fc2` without changing approved behavior.
- Core source GREEN `2550b1e74a2c25b73dcb0bb189bbf9f867c73fc2`, CI `33682710773`: PHPStan clean; PHPUnit `164 tests / 858 assertions`; Composer audit clean.
- Adapter sanitization RED `0d5d9576fd794820ef7a9671d325e3134e21277d`, CI `33682842361`: WPCS/PHPStan clean; PHPUnit `165 tests / 860 assertions / 1 failure`, proving raw WordPress HTML was still crossing the native adapter boundary.
- Sanitization implementation `4dab1fe52720d6071d9065c431df68920d2ba5e5` applies `wp_strip_all_tags()` to title/excerpt/content at the native WordPress boundary. `e032f995697c7722b877dc97f4619f401af57d56` aligned the existing gateway mapping test; CI `33683081722` then identified only an over-specified mock expectation for a taxonomy call that correctly does not occur when no taxonomies exist. `5a00b73b3e01417986e02583351d21b907093490` removed that impossible expectation without changing runtime behavior.
- Exact Task 5 implementation GREEN `5a00b73b3e01417986e02583351d21b907093490`, CI `33683221417`: PHPStan `[OK] No errors`; PHPUnit `165 tests / 862 assertions`; Composer audit clean; `js-quality`, `package`, and `wordpress-smoke` all passed. WordPress activation/database/provider smoke passed. Package assertion/upload passed; artifact `9867077987`, 74,913 bytes, digest `sha256:2442a81d6114242c2ac233f3a11f815b4f940cbadc840f8a89cfdec2a5e3547d`.

## Task 4 Implementation Notes
- `WordPressContentGateway` isolates WordPress core APIs from source normalization.
- Native queries are deterministic, bounded to `1..100`, publish-only by default, add private only by explicit opt-in, exclude password-protected posts, map permalinks, and normalize taxonomy labels.
- No arbitrary post-meta ingestion or outbound HTTP is introduced.

## Task 5 Implementation Notes
- `WordPressPostSource` emits source type `wordpress_posts` and stable keys `wp-post:{post_type}:{post_id}`.
- Default selection is public `page`/`post`; explicitly configured public CPTs are allowed; unsupported/non-public configured types fail closed.
- Source consumption is bounded at 100 records per gateway page and continues until a short page.
- Draft, pending, trash, and password-protected records are excluded; private records require explicit `include_private=true` and retain `private` visibility.
- Canonical content combines normalized title, excerpt/body, and deterministic taxonomy labels. Metadata records source type, post ID/type/status, author, and taxonomy labels.
- Source version is `{modified_gmt}:{post_id}` and `DocumentHasher` captures the full canonical payload, including taxonomy/access metadata.
- Native WordPress title/excerpt/content HTML is converted to text with `wp_strip_all_tags()` before it crosses the adapter boundary.

## Integration Test Evidence
Task 7's real M04 WordPress knowledge lifecycle/source smoke remains pending. Existing permanent activation/database/provider smoke stayed green on Task 5 exact-head CI but does not replace the milestone-specific Task 7 knowledge smoke acceptance gate.

## E2E / Visual Verification
N/A; M04 introduces no UI.

## Security Review
Tasks 1-5 have been reviewed for deterministic hashing, fail-closed source validation, supported `public|private` visibility, no credential handling, no arbitrary outbound HTTP, no arbitrary post-meta ingestion, password-protected exclusion, explicit private-status opt-in, sanitization at the WordPress adapter boundary, and no partial FAQ emission after later-item validation failure. No unresolved Critical/Important finding exists in the completed slice. Full milestone security review remains pending in Task 8.

## Accessibility Review where UI exists
N/A.

## Performance Review where relevant
The native gateway and Task 5 source use 100-record bounded paging, stable ID ordering, `no_found_rows`, and no remote I/O. Taxonomy lookup remains per-post and is a known performance consideration for Task 7/Task 8 real-WordPress verification; no premature batching optimization was added without evidence.

## Code Review Findings
Completed-slice isolated second-pass review:
- Critical: none.
- Important fixed: one from Task 3 — FAQ validation originally allowed partial ingestion; fixed and regression-tested.
- Task 4 Critical/Important: none.
- Task 5 Critical/Important: none.
- Important unresolved: none.
- PR #4 has no submitted reviews and no unresolved inline review threads at Task 5 closeout.
- Minor follow-up for full Task 8 review: focused Task 5 tests do not separately isolate every defensive config/persistence guard even though production rejects invalid source type, unpersisted source IDs, malformed `include_private`, and unsupported post types. This is non-blocking for Task 5's planned behavior and should be reconsidered during milestone-wide gap review.

A separate reviewer/subagent is not available in this runtime; this was an isolated second-pass review against the approved Task 5 plan and diff, not represented as external human approval.

## Fixes
- Corrected test-only WPCS issues before accepting behavioral RED evidence.
- Preserved earlier Task 1-4 review fixes and narrow WPCS suppressions documented for approved camelCase domain contracts.
- Task 5: corrected WPCS loop-condition style and PHPStan source-ID narrowing without changing runtime behavior.
- Task 5: added an explicit adapter-boundary sanitization RED/GREEN regression and converted WordPress title/excerpt/body HTML to safe text.
- Task 5: aligned existing gateway expectations with the new text boundary and removed an impossible empty-taxonomy mock expectation discovered by exact-SHA CI.

## Fresh Verification Commands
Dependency-backed execution uses permanent GitHub Actions in this runtime. Exact commands represented by CI include `composer verify:php`, `composer audit`, `npm run verify:js`, provider live-gating/package assertions, WordPress activation/database/provider smoke commands, build/package generation, package assertions, and artifact upload.

## Fresh Verification Results
Task 5 exact implementation SHA `5a00b73b3e01417986e02583351d21b907093490` passed CI `33683221417` across all permanent jobs. PHPStan reported no errors; PHPUnit passed `165 tests / 862 assertions`; Composer audit was clean; JS quality, package generation/assertion/upload, and WordPress activation/database/provider smoke all passed.

This ledger update changes the branch head after implementation GREEN. The resulting documentation-only head must pass its own exact-SHA CI before this run is cleanly closed.

## Commits
- `ca7a9bfb828a817488f144f51a1dc250e3a89226` — M04 design.
- `8958372e1ef93cce090a14fb88e95b8e3f254fca` — M04 implementation plan.
- `5108cb315ead4e7213c033d0e094c49978e8da02` / `5cdc7d105a3a80f601b826b6ccad135a862d1f61` — Task 1 RED/GREEN.
- `93e3be47058777c332cf3d31417b89c191f1020b` / `a1813a695f59193cb6e48615aa9daf2915aafa8d` — Task 2 RED/GREEN.
- `551d1690b83b43cf0b6f8f589ce3f9d8ed3b8e25` / `96f44569d2478d6a6631ac44fde30026f657dc29` — Task 3 RED/initial GREEN.
- `9417ad1d80f0855f5db4ff066eae4b9ac4caf474` / `918ca7558d100b2e5076924bc62937886b368ab2` — Task 3 Important review-fix RED/GREEN.
- `0bf623faf5b4f00fde0aa3fd0ad31a7dfb50e6f3` / `8a9ceb2fb6021a5f87bba460ef43eca4bec10b81` — Task 4 valid RED/exact implementation GREEN.
- `6b7d9e565e39ddec8074bf3f404b898e332696ae`, `c17842542819efa294593f0fa894a6562a1ec9bf` — Task 5 pre-RED test quality corrections, not behavioral RED.
- `5c074d7701a3946099f9f7b9f4804b421f4405bc` — Task 5 valid source RED.
- `3900eb204141df50008e1da02e725c4c47de08ae`, `6e89a54525f342bf0a9051453c93ebea592b0c08`, `2550b1e74a2c25b73dcb0bb189bbf9f867c73fc2` — Task 5 source implementation/quality corrections/core GREEN.
- `0d5d9576fd794820ef7a9671d325e3134e21277d` / `4dab1fe52720d6071d9065c431df68920d2ba5e5` — Task 5 adapter sanitization RED/implementation.
- `e032f995697c7722b877dc97f4619f401af57d56`, `5a00b73b3e01417986e02583351d21b907093490` — Task 5 test-alignment correction and exact implementation GREEN.

## Files Changed
Current M04 slice includes design/plan documentation, source contract/exception/registry, deterministic hashing, manual text and FAQ sources, WordPress content gateway/value/native adapter, WordPress post/page/public-CPT source, fake gateway support, and focused unit tests.

## Known Limitations
Registry bootstrap/filter wiring, real M04 WordPress knowledge smoke, final security/performance review, branch reconciliation with current `main`, milestone completion evidence, and integration are unfinished. Taxonomy retrieval is currently per post. No remote URL fetch is implemented by design in M04.

## Documentation Updated
`docs/superpowers/specs/2026-09-03-m04-wordpress-knowledge-sources-design.md`, `docs/superpowers/plans/2026-09-03-m04-wordpress-knowledge-sources.md`, this milestone ledger, and `docs/progress/STATUS.md`.

## Completion Checklist
Not complete. M04 must not merge or transition to M05 until Tasks 6-8 pass TDD, review, exact-head CI, integration, and post-merge gates.

## Exact Next Action
Recover latest `main`, PR #4, branch head, exact-head CI, reviews, and concurrency. If safe, execute Task 6 from the auto-approved plan using strict TDD: create failing `KnowledgeBootstrapTest`/`BootstrapTest` coverage proving the plugins-loaded hook and native default sources, obtain behavioral RED, then implement `KnowledgeBootstrap::register()` and integrate it after database bootstrap, applying `wp_rag_ai_chatbot_knowledge_sources` only to additional `KnowledgeSource` instances and rejecting invalid filter values; require focused/full PHP GREEN before Task 7.

## Next Milestone
M05 — File/Document Ingestion, only after M04 is genuinely complete and post-merge `main` CI is green.
