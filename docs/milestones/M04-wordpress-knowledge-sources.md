# M04 — WordPress Knowledge Source Framework

Status: IN PROGRESS

## Goal
Normalize selected WordPress content into traceable knowledge Documents through extensible source contracts.

## Dependencies
M02. M03 is integrated and documentation-closed on current `main` SHA `65c1aa9a7081ac0d831bb118a0cd445b4f5ba5e1`; its post-closeout CI is green.

## In Scope
Pages/posts/public CPTs; selected taxonomy metadata; manual text; FAQ/Q&A; selected URLs/sitemap configuration foundation where source boundary fits; source versions/hashes/access metadata; source registry/hooks.

## Out of Scope
File parsing (M05), WooCommerce specialization (M06), chunking/embeddings (M07-M08), and outbound remote crawling in M04.

## Architecture
`KnowledgeSource` contract -> canonical M02 `DocumentRecord` model with identity/title/URL/content/metadata/version/hash/language/visibility. WordPress global APIs are isolated behind a gateway so source normalization remains deterministic and unit-testable.

Design: `docs/superpowers/specs/2026-09-03-m04-wordpress-knowledge-sources-design.md`.

Implementation plan: `docs/superpowers/plans/2026-09-03-m04-wordpress-knowledge-sources.md`.

Both are `AUTO-APPROVED — SCHEDULED MODE` after self-review under the repository autonomous-development procedure.

## Acceptance Criteria
Supported WP sources normalize deterministically; draft/private/role access policy is explicit; changes produce stable version/hash signals; source extension contract tested.

## Tasks

- Task 1 — source contract, registry, deterministic hashing: complete and verified.
- Task 2 — manual text source: complete and verified.
- Task 3 — FAQ source: complete; Important partial-yield review finding fixed with regression coverage and verified.
- Task 4 — WordPress content gateway: complete, independently reviewed, and exact-head CI verified.
- Task 5 — WordPress post/page/public-CPT source: next unfinished task.
- Task 6 — registry bootstrap/extension hook: pending.
- Task 7 — real WordPress knowledge smoke coverage: pending.
- Task 8 — full review, verification, durable evidence, PR integration: pending.

## TDD Evidence

### Task 1

- RED SHA: `5108cb315ead4e7213c033d0e094c49978e8da02`; CI `33671328652`.
- RED result: PHPCS/PHPStan passed; PHPUnit `142 tests / 755 assertions`, 8 expected missing-class failures.
- GREEN implementation SHA: `5cdc7d105a3a80f601b826b6ccad135a862d1f61`; PHP verification passed in CI `33671611425`.

### Task 2

- RED SHA: `93e3be47058777c332cf3d31417b89c191f1020b`; CI `33671716477`.
- RED result: PHPCS/PHPStan passed; PHPUnit `148 tests / 763 assertions`, 6 expected failures because `ManualTextSource` did not yet exist.
- GREEN SHA: `a1813a695f59193cb6e48615aa9daf2915aafa8d`; CI `33672126600`.
- GREEN result: PHPCS/PHPStan clean; PHPUnit `148 tests / 777 assertions`; Composer audit clean.

### Task 3

- Initial test attempts `8176c4d13fb491ccc1f87e0986186cf4eca084e1` and `9e7a2cff7398216aea108d417a01da28c0df49b4` stopped at test-only WPCS issues before PHPUnit and are **not** counted as behavioral RED.
- Valid RED SHA: `551d1690b83b43cf0b6f8f589ce3f9d8ed3b8e25`; CI `33673099461`.
- Valid RED result: WPCS/PHPStan clean; PHPUnit `153 tests / 782 assertions / 5 failures`, all because `FaqSource` did not exist.
- Initial GREEN SHA: `96f44569d2478d6a6631ac44fde30026f657dc29`; CI `33673225337`.
- Initial GREEN result: WPCS/PHPStan clean; PHPUnit `153 tests / 798 assertions`; Composer audit clean.
- Independent review found one Important data-integrity issue: a malformed later FAQ item could be discovered only after an earlier document had already been yielded, allowing a streaming consumer to persist partial normalized output.
- Review-fix RED SHA: `9417ad1d80f0855f5db4ff066eae4b9ac4caf474`; CI `33673441211`.
- Review-fix RED result: WPCS/PHPStan clean; PHPUnit `154 tests / 800 assertions / 1 failure`, exactly because `KnowledgeSourceException` was not thrown before the first yield.
- Review-fix GREEN SHA: `918ca7558d100b2e5076924bc62937886b368ab2`; CI `33673592282`.
- Review-fix GREEN result: WPCS/PHPStan clean; PHPUnit `154 tests / 800 assertions`; Composer audit clean.

### Task 4

- Test-only SHA `a1f18f25f718361fa4ba829b82aa5ded649a2ff1` stopped at WPCS assignment alignment before PHPUnit and is **not** counted as behavioral RED.
- Valid RED SHA: `0bf623faf5b4f00fde0aa3fd0ad31a7dfb50e6f3`; CI `33675981175`.
- Valid RED result: WPCS/PHPStan clean; PHPUnit reached `158 tests / 804 assertions / 4 failures`, all expected because `NativeWordPressContentGateway` did not yet exist.
- Production implementation commits introduced the gateway contract, immutable post value, and native WordPress adapter, followed by narrow quality/tooling corrections without changing the approved behavior.
- Exact-head GREEN SHA: `8a9ceb2fb6021a5f87bba460ef43eca4bec10b81`; CI `33676782328`.
- GREEN PHP result: PHPStan `[OK] No errors`; PHPUnit `158 tests / 822 assertions`; Composer audit reported no security vulnerability advisories.
- GREEN permanent CI result: `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all completed successfully on the exact SHA. Package generation/assertion and artifact upload succeeded; WordPress activation, database, and provider smoke tests succeeded.

## Task 4 Implementation Notes

- `WordPressContentGateway` isolates WordPress core APIs from source normalization.
- `publicPostTypes()` reads public post types and returns deterministic sorted names.
- `posts()` deduplicates/sorts requested types, clamps page size to `1..100`, pages deterministically by ID ascending, defaults to `publish`, adds `private` only when explicitly enabled, excludes password-protected posts, ignores sticky ordering, and disables unnecessary found-row counting.
- `WordPressPost` is an immutable value containing post ID/type/status/title/excerpt/content/permalink/modified GMT/language/password flag/author ID/taxonomy labels.
- The native adapter maps permalink through WordPress core and normalizes taxonomy labels into deterministic taxonomy/name/slug ordering. It does not read arbitrary post meta and does not perform outbound HTTP.

## Integration Test Evidence
Real WordPress M04 knowledge lifecycle/source smoke remains pending for Task 7. Existing permanent activation/database/provider smoke stayed green on Task 4 exact-head CI but does not replace the milestone-specific source lifecycle smoke acceptance gate.

## E2E / Visual Verification
N/A; M04 introduces no UI.

## Security Review
Tasks 1-4 reviewed for deterministic hashing, fail-closed source validation, supported `public|private` visibility, no credential handling, no arbitrary outbound HTTP, no arbitrary post-meta ingestion, password-protected post exclusion, explicit private-status opt-in, and no partial FAQ emission after later-item validation failure. No unresolved Critical/Important finding exists in the completed slice. Full milestone security review remains pending.

## Accessibility Review where UI exists
N/A.

## Performance Review where relevant
The gateway uses bounded paging (`1..100` posts per request), stable ID ordering, `no_found_rows`, and no remote I/O. Taxonomy lookup remains per-post at this boundary and must be evaluated with Task 5 source batching/lifecycle behavior before final M04 performance sign-off.

## Code Review Findings

Completed-slice independent review:

- Critical: none.
- Important fixed: one from Task 3 — FAQ validation originally occurred item-by-item during yielding, allowing partial ingestion; fixed and regression-tested.
- Task 4 Critical: none.
- Task 4 Important: none.
- Important unresolved: none.
- PR #4 review state at Task 4 closeout: no submitted reviews and no unresolved review threads.
- Minor: WPCS requires narrow documented naming suppressions for approved camelCase domain/gateway contract members; these suppressions are local to the relevant contract/value surface.

Full milestone review remains required before integration.

## Fixes

- Corrected test-only WPCS issues before accepting behavioral RED evidence.
- Added explicit throws documentation and generic registry exception messages instead of exposing dynamic source identifiers.
- Preserved native `json_encode(... JSON_THROW_ON_ERROR)` in the WordPress-independent deterministic hasher with a narrow documented WPCS suppression.
- Isolated pre-existing M02 camelCase property accesses with narrow naming suppressions in source normalizers.
- Translated `JsonException` into `KnowledgeSourceException` while preserving the previous Throwable with a narrow false-positive output-escaping suppression.
- Prevalidated and normalized every FAQ item before the first `yield`, preventing partial document emission when a later item is malformed.
- For Task 4, corrected only test/quality-tooling issues after behavioral RED: WPCS alignment/documentation, PHPStan-redundant WordPress type guards, and taxonomy-label PHPDoc type alignment. Runtime behavior remained within the approved plan.

## Fresh Verification Commands
Dependency-backed execution uses permanent GitHub Actions in this runtime. Exact commands represented by CI include `composer verify:php`, `composer audit`, `npm run verify:js`, provider live-gating/package assertions, WordPress smoke commands, build/package generation, and package assertions.

## Fresh Verification Results
Task 4 exact-head implementation SHA `8a9ceb2fb6021a5f87bba460ef43eca4bec10b81` passed CI run `33676782328` across all permanent jobs. PHP verification recorded PHPStan clean and PHPUnit `158 tests / 822 assertions`; Composer audit was clean. JS verification, package artifact generation/assertion, and WordPress activation/database/provider smoke also passed.

This ledger update changes the branch head after that implementation verification. The documentation-only resulting head must pass its own exact-SHA CI before the run is closed.

## Commits

- `ca7a9bfb828a817488f144f51a1dc250e3a89226` — M04 design.
- `8958372e1ef93cce090a14fb88e95b8e3f254fca` — M04 implementation plan.
- `5108cb315ead4e7213c033d0e094c49978e8da02` / `5cdc7d105a3a80f601b826b6ccad135a862d1f61` — Task 1 RED/GREEN.
- `93e3be47058777c332cf3d31417b89c191f1020b` / `a1813a695f59193cb6e48615aa9daf2915aafa8d` — Task 2 RED/GREEN.
- `551d1690b83b43cf0b6f8f589ce3f9d8ed3b8e25` / `96f44569d2478d6a6631ac44fde30026f657dc29` — Task 3 valid RED/initial GREEN.
- `9417ad1d80f0855f5db4ff066eae4b9ac4caf474` / `918ca7558d100b2e5076924bc62937886b368ab2` — Task 3 Important review-fix RED/GREEN.
- `a1f18f25f718361fa4ba829b82aa5ded649a2ff1` — Task 4 test-only formatting attempt, not behavioral RED.
- `0bf623faf5b4f00fde0aa3fd0ad31a7dfb50e6f3` — Task 4 valid RED.
- `30f44ee685a047810d55bb5d22ce94ffe756ffaa`, `2f8544736cd316f6d7d31606686d41f14cd62b1c`, `4013050eb69f187e18dfc5a2a894c7c79e171cfb` — Task 4 contract/value/native adapter implementation.
- `2365fe392127391520ae01cdd895f9aa2a1dd45a`, `ade96ae0416acc4cb490638f7c62bbadfcf1b043`, `1da12892c75be0ade7d42a8bcffd9b679b181f67`, `a8806d8015bbfe0a894aea0bc72cb2484df62198`, `014f7809fbc942fe0099d7e2708fd12b2fe06436`, `8a9ceb2fb6021a5f87bba460ef43eca4bec10b81` — Task 4 quality/tooling corrections and exact-head GREEN.

## Files Changed
Current M04 slice includes design/plan documentation, knowledge-source contract/exception/registry, deterministic document hashing, manual-text normalization, FAQ normalization, the WordPress content gateway contract/value/native adapter, and unit tests for those behaviors.

## Known Limitations
WordPress post/page/public-CPT normalization, source bootstrap/filter extension wiring, real WordPress source smoke coverage, final security/performance review, branch reconciliation with current `main`, milestone completion evidence, and integration are unfinished. No remote URL fetch is implemented by design in M04.

## Documentation Updated
`docs/superpowers/specs/2026-09-03-m04-wordpress-knowledge-sources-design.md`, `docs/superpowers/plans/2026-09-03-m04-wordpress-knowledge-sources.md`, this milestone ledger, and `docs/progress/STATUS.md`.

## Completion Checklist
Not complete. M04 must not merge or transition to M05 until Tasks 5-8 pass their TDD, review, exact-head CI, integration, and post-merge gates.

## Exact Next Action
Recover latest `main`, PR #4, branch head, exact-head CI, reviews, and concurrency. If safe, execute Task 5 from the auto-approved plan using strict TDD: add `WordPressPostSource` behavior tests first for selection/filtering/content normalization/metadata/version-hash/access semantics, obtain behavioral RED, then implement the minimum post/page/public-CPT source against `WordPressContentGateway` and obtain GREEN before Task 6.

## Next Milestone
M05 — File/Document Ingestion, only after M04 is genuinely complete and post-merge `main` CI is green.
