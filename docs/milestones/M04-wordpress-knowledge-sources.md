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
`KnowledgeSource` contract -> canonical M02 `DocumentRecord` model with identity/title/URL/content/metadata/version/hash/language/visibility. WordPress global APIs will be isolated behind a gateway so source normalization remains deterministic and unit-testable.

Design: `docs/superpowers/specs/2026-09-03-m04-wordpress-knowledge-sources-design.md`.

Implementation plan: `docs/superpowers/plans/2026-09-03-m04-wordpress-knowledge-sources.md`.

Both are `AUTO-APPROVED — SCHEDULED MODE` after self-review.

## Acceptance Criteria
Supported WP sources normalize deterministically; draft/private/role access policy is explicit; changes produce stable version/hash signals; source extension contract tested.

## Tasks

- Task 1 — source contract, registry, deterministic hashing: implemented and PHP-verified.
- Task 2 — manual text source: implemented and PHP-verified.
- Task 3 — FAQ source: implemented, reviewed, Important partial-yield finding fixed with regression, and PHP-verified.
- Task 4 — WordPress content gateway: next unfinished task.
- Task 5 — WordPress post/page/public-CPT source: pending.
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
- Review-fix GREEN result: WPCS/PHPStan clean; PHPUnit `154 tests / 800 assertions`; Composer audit clean. The implementation validates and normalizes the complete FAQ item list before yielding any document.

## Integration Test Evidence
Real WordPress M04 knowledge lifecycle/source smoke remains pending. Existing activation/database/provider smoke is permanent CI coverage but does not satisfy M04's new knowledge-source smoke acceptance gate.

## E2E / Visual Verification
N/A; M04 introduces no UI.

## Security Review
Tasks 1-3 reviewed for deterministic hashing, fail-closed source validation, supported `public|private` visibility, no credential handling, no arbitrary outbound HTTP, and no partial FAQ emission after later-item validation failure. No unresolved Critical/Important finding in the completed slice. Full milestone security review remains pending.

## Accessibility Review where UI exists
N/A.

## Performance Review where relevant
Current registry/hash/manual/FAQ normalization is bounded and contains no network/database enumeration. WordPress pagination/N+1 review remains pending for Tasks 4-5.

## Code Review Findings

Completed-slice review:

- Critical: none.
- Important: one — FAQ validation originally occurred item-by-item during yielding, so a malformed later item could permit partial ingestion. Fixed by validating the complete item list before the first yield; regression RED/GREEN recorded above.
- Important unresolved: none.
- Minor: WPCS interprets pre-existing M02 camelCase domain properties and exception-cause chaining conservatively; suppressions remain narrowly scoped and documented.

Full milestone review remains required before integration.

## Fixes

- Corrected test-only WPCS issues before accepting behavioral RED evidence.
- Added explicit throws documentation and generic registry exception messages instead of exposing dynamic source identifiers.
- Preserved native `json_encode(... JSON_THROW_ON_ERROR)` in the WordPress-independent deterministic hasher with a narrow documented WPCS suppression.
- Isolated pre-existing M02 camelCase property accesses with narrow naming suppressions in source normalizers.
- Translated `JsonException` into `KnowledgeSourceException` while preserving the previous Throwable with a narrow false-positive output-escaping suppression.
- Prevalidated and normalized every FAQ item before the first `yield`, preventing partial document emission when a later item is malformed.

## Fresh Verification Commands
Dependency-backed execution uses permanent GitHub Actions in this runtime. Exact commands represented by CI include `composer verify:php`, `composer audit`, `npm run verify:js`, WordPress smoke commands, build/package generation, and package assertions.

## Fresh Verification Results
For review-fix GREEN SHA `918ca7558d100b2e5076924bc62937886b368ab2`, CI run `33673592282` records PHP verification green: WPCS/PHPStan clean, PHPUnit `154 tests / 800 assertions`, Composer audit clean. The remaining permanent jobs must be re-checked before treating that exact SHA as fully green.

## Commits

- `ca7a9bfb828a817488f144f51a1dc250e3a89226` — M04 design.
- `8958372e1ef93cce090a14fb88e95b8e3f254fca` — M04 implementation plan.
- `5108cb315ead4e7213c033d0e094c49978e8da02` / `5cdc7d105a3a80f601b826b6ccad135a862d1f61` — Task 1 RED/GREEN.
- `93e3be47058777c332cf3d31417b89c191f1020b` / `a1813a695f59193cb6e48615aa9daf2915aafa8d` — Task 2 RED/GREEN.
- `8176c4d13fb491ccc1f87e0986186cf4eca084e1`, `9e7a2cff7398216aea108d417a01da28c0df49b4` — Task 3 test-quality attempts, not behavioral RED.
- `551d1690b83b43cf0b6f8f589ce3f9d8ed3b8e25` — Task 3 valid RED.
- `96f44569d2478d6a6631ac44fde30026f657dc29` — Task 3 initial GREEN implementation.
- `9417ad1d80f0855f5db4ff066eae4b9ac4caf474` / `918ca7558d100b2e5076924bc62937886b368ab2` — Important review-fix RED/GREEN.

## Files Changed
Current M04 slice adds design/plan documentation, knowledge-source contract/exception/registry, deterministic document hashing, manual-text normalization, FAQ normalization, and unit tests for those behaviors.

## Known Limitations
WordPress gateway/source normalization, source bootstrap/filter extension wiring, real WordPress source smoke coverage, final security/performance review, branch reconciliation with current `main`, milestone evidence, and integration are unfinished. No remote URL fetch is implemented by design in M04.

## Documentation Updated
`docs/superpowers/specs/2026-09-03-m04-wordpress-knowledge-sources-design.md`, `docs/superpowers/plans/2026-09-03-m04-wordpress-knowledge-sources.md`, this milestone ledger, and `docs/progress/STATUS.md`.

## Completion Checklist
Not complete. M04 must not merge or transition to M05 until Tasks 4-8 pass their TDD, review, exact-head CI, integration, and post-merge gates.

## Exact Next Action
Recover latest `main`, PR #4, branch head, CI, and concurrency. If safe, execute Task 4 from the auto-approved plan using strict TDD: add the WordPress content gateway contract/data behavior tests first, obtain expected RED, then implement the minimum gateway boundary and obtain GREEN before starting WordPress post normalization.

## Next Milestone
M05 — File/Document Ingestion, only after M04 is genuinely complete and post-merge `main` CI is green.
