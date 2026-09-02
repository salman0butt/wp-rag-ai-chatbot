# M04 — WordPress Knowledge Source Framework

Status: IN PROGRESS

## Goal
Normalize selected WordPress content into traceable knowledge Documents through extensible source contracts.

## Dependencies
M02. M03 is also integrated on `main` and its post-merge CI is green.

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
- Task 3 — FAQ source: next unfinished task.
- Task 4 — WordPress content gateway: pending.
- Task 5 — WordPress post/page/public-CPT source: pending.
- Task 6 — registry bootstrap/extension hook: pending.
- Task 7 — real WordPress knowledge smoke coverage: pending.
- Task 8 — full review, verification, durable evidence, PR integration: pending.

## TDD Evidence

### Task 1

- RED SHA: `5108cb315ead4e7213c033d0e094c49978e8da02`.
- RED CI: `33671328652`.
- RED result: PHPCS/PHPStan passed; PHPUnit `142 tests / 755 assertions`, 8 expected failures because `DocumentHasher`, `KnowledgeSource`, `KnowledgeSourceRegistry`, and `KnowledgeSourceException` did not yet exist.
- GREEN implementation SHA: `5cdc7d105a3a80f601b826b6ccad135a862d1f61` after the initial implementation and WPCS-quality correction.
- GREEN evidence: `php-quality` passed on CI run `33671611425`.

### Task 2

- RED SHA: `93e3be47058777c332cf3d31417b89c191f1020b`.
- RED CI: `33671716477`.
- RED result: PHPCS/PHPStan passed; PHPUnit `148 tests / 763 assertions`, 6 expected failures because `ManualTextSource` did not yet exist.
- Production commits: `1b190f7a6f812db15a8ff301fcf8fbd7997df290`, `72776a22e64c3841d86e9da36decaaf1696fd197`, `a1813a695f59193cb6e48615aa9daf2915aafa8d`.
- GREEN SHA: `a1813a695f59193cb6e48615aa9daf2915aafa8d`.
- GREEN CI: `33672126600`.
- GREEN PHP result: PHPCS/PHPStan clean; PHPUnit `148 tests / 777 assertions`; Composer audit clean.

## Integration Test Evidence
Real WordPress M04 knowledge lifecycle/source smoke remains pending. Existing activation/database/provider smoke continues to run in permanent CI, but does not satisfy M04's new knowledge-source smoke acceptance gate.

## E2E / Visual Verification
N/A; M04 introduces no UI.

## Security Review
Current slice reviewed for deterministic hashing, fail-closed source validation, supported `public|private` manual visibility, no credentials, no arbitrary metadata, and no outbound HTTP. No unresolved Critical/Important findings in Tasks 1-2. Full milestone security review remains pending.

## Accessibility Review where UI exists
N/A.

## Performance Review where relevant
Current source registry/hash/manual normalization is bounded and contains no network/database enumeration. WordPress pagination/N+1 review remains pending for Tasks 4-5.

## Code Review Findings

Current slice independent review:

- Critical: none.
- Important: none unresolved.
- Minor: WPCS interprets pre-existing M02 camelCase domain properties and exception-cause chaining conservatively; suppressions were kept narrowly scoped and documented rather than weakening global CI.

Full milestone review remains required before integration.

## Fixes

- Corrected test-only PHPCS issues before accepting Task 1 RED; the invalid lint failure was not counted as behavioral RED.
- Added explicit throws documentation and generic registry exception messages instead of exposing dynamic source identifiers.
- Preserved native `json_encode(... JSON_THROW_ON_ERROR)` in the WordPress-independent deterministic hasher with a narrow documented WPCS suppression.
- Isolated pre-existing M02 camelCase property accesses with a narrow naming suppression in `ManualTextSource`.
- Translated `JsonException` into `KnowledgeSourceException` while preserving the previous Throwable, with a single false-positive output-escaping suppression on exception chaining.

## Fresh Verification Commands
Dependency-backed execution uses permanent GitHub Actions in this runtime. Exact commands represented by the current CI include `composer verify:php`, `composer audit`, `npm run verify:js`, existing WordPress smoke commands, build/package generation, and package assertions.

## Fresh Verification Results
For `a1813a695f59193cb6e48615aa9daf2915aafa8d`, CI run `33672126600` recorded:

- `php-quality`: SUCCESS — PHP verification and Composer audit green, PHPUnit `148 tests / 777 assertions`.
- `js-quality`: SUCCESS.
- `package`: SUCCESS.
- `wordpress-smoke`: still running when this milestone entry was written. A later run must re-check exact-SHA state; this entry intentionally does not claim the full run is green.

## Commits

- `ca7a9bfb828a817488f144f51a1dc250e3a89226` — M04 design.
- `8958372e1ef93cce090a14fb88e95b8e3f254fca` — M04 implementation plan.
- `dd4861d2561148850e62adbc61e23a0e931d0bc2` / `5ef5d301fb0ca328f435c56af8a5e718c156f4e7` — initial Task 1 tests.
- `6dc4219ad17f4838e7e2dbe59041325e26e14866` / `5108cb315ead4e7213c033d0e094c49978e8da02` — test-quality corrections and valid Task 1 RED.
- `d7ded3ab94df65627256bbca340618c21b01db0b` / `5cdc7d105a3a80f601b826b6ccad135a862d1f61` — Task 1 implementation/quality fix.
- `93e3be47058777c332cf3d31417b89c191f1020b` — Task 2 RED tests.
- `1b190f7a6f812db15a8ff301fcf8fbd7997df290` / `72776a22e64c3841d86e9da36decaaf1696fd197` / `a1813a695f59193cb6e48615aa9daf2915aafa8d` — Task 2 implementation and focused quality corrections.

## Files Changed
Current slice adds M04 design/plan documentation, knowledge-source contract/exception/registry, deterministic document hashing, manual-text normalization, and unit tests for those behaviors. The remaining M04 files are intentionally not started yet.

## Known Limitations
FAQ normalization, WordPress gateway/source normalization, source bootstrap/filter extension wiring, real WordPress source smoke coverage, final review/evidence, and integration are unfinished. No remote URL fetch is implemented by design in M04.

## Documentation Updated
`docs/superpowers/specs/2026-09-03-m04-wordpress-knowledge-sources-design.md`, `docs/superpowers/plans/2026-09-03-m04-wordpress-knowledge-sources.md`, this milestone ledger, and `docs/progress/STATUS.md`.

## Completion Checklist
Not complete. M04 must not merge or transition to M05 until Tasks 3-8 pass their TDD, review, exact-head CI, integration, and post-merge gates.

## Exact Next Action
Recover latest branch/CI state and concurrency first. If safe, execute Task 3 using strict TDD: add FAQ source tests, capture expected RED, implement the minimum `FaqSource`, capture GREEN, then continue only if the next coherent unit can be completed safely.

## Next Milestone
M05 — File/Document Ingestion, only after M04 is genuinely complete and post-merge `main` CI is green.
