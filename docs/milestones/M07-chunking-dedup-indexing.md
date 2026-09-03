# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing

Status: **IN PROGRESS — Tasks 1-3 complete; Task 4 next.**

## Goal
Create deterministic normalized content/chunks with traceability, deduplication, hashes, and incremental reindex decisions.

## Dependencies
M04-M06 source/document contracts.

## Design / plan
- Design/spec: `docs/superpowers/specs/2026-09-03-m07-chunking-dedup-indexing-design.md`.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m07-chunking-dedup-indexing.md`.
- Status: **AUTO-APPROVED — SCHEDULED MODE** after repository-mandated self-review.
- Selected architecture: pure-PHP deterministic `DocumentRecord -> normalize -> structure-aware chunk -> hash/lineage -> compatibility-safe dedup -> incremental index plan`.
- M08 retains embedding/vector-store ownership; M09 retains background queue/synchronization ownership.

## In Scope
Structure-aware recursive chunking; heading/paragraph/sentence/token limits; configurable overlap; parent-child/sequence metadata; hashes; dedup; source/index versions; affected-chunk decisions.

## Out of Scope
Actual embeddings/vector upserts (M08), async execution engine (M09).

## Architecture
`DocumentRecord -> ContentNormalizer -> StructureAwareChunker -> ChunkDeduplicator -> IncrementalIndexPlanner`.

Chunk records retain citation/source metadata, sequence/structural parent lineage, deterministic hashes, chunking-version identity, and a nullable embedding compatibility key for M08. M07 performs no persistence, embedding, vector, network, queue, hook, or REST side effects.

## Acceptance Criteria
- Boundary fixtures are deterministic.
- Tiny and huge sections are handled without infinite loops.
- Metadata, language, visibility, URL, document/source lineage, heading and sequence metadata are preserved.
- Unchanged content produces zero unnecessary re-embed work in the index plan.
- Changed sections produce bounded affected work.
- Dedup never crosses visibility/language/embedding-compatibility boundaries.
- Retrieved/document content remains untrusted literal data and is never interpreted as executable instructions.

## Tasks
- [x] Task 1 — Deterministic content normalization. **Complete: strict RED/GREEN, exact-head CI, independent fresh-session review clean.**
- [x] Task 2 — Token budget/configuration contracts. **Complete: strict RED/GREEN, exact-head CI, independent fresh-session review clean.**
- [x] Task 3 — Immutable chunk records and structure-aware splitting. **Complete: corrected genuine RED/GREEN, exact-head CI, independent fresh-session review clean.**
- [ ] Task 4 — Deliberate bounded overlap.
- [ ] Task 5 — Compatibility-safe deduplication.
- [ ] Task 6 — Incremental index planning.
- [ ] Task 7 — Source-to-index-plan integration and milestone closeout.

## Task 1 durable evidence
- RED `3e34a6d1125592a256463c3e75ee9406fa3e5e3a`; CI `33775003445`: PHPStan clean; PHPUnit 263 tests / 1252 assertions / 7 intended missing-class failures.
- GREEN `de41fd281d95f2a367df163ea66d8713357b8a14`; CI `33775193798`: all permanent jobs passed; PHPUnit 263 / 1252; Composer audit clean.
- Independent fresh-session review `5104488263`: **0 Critical / 0 Important unresolved**.

Task 1 introduces only `ContentNormalizer`, a WordPress-independent canonical whitespace normalizer. It converts CRLF/lone CR to LF, removes only a leading UTF-8 BOM, strips trailing horizontal spaces/tabs per line, collapses runs of three or more line feeds to two, trims document-edge whitespace, and otherwise preserves literal untrusted text.

## Task 2 durable evidence
- RED `220bffa181cb4a32490f6fa35b3be6904ae790d6`; CI `33780572899`: PHPStan clean; PHPUnit 272 tests / 1261 assertions / 9 intended missing-contract failures.
- GREEN `f802bed14cc1887c219b4ac1058236ded114224c`; CI `33780799386`: all permanent jobs passed; PHPUnit 272 / 1276; Composer audit clean.
- Artifact `9903483875`, digest `sha256:f06d0f169bf7fa74718f976a3d63f05b3285347e2a45c24245c74c5a6138d388`.
- Independent fresh-session review `5105069991`: **0 Critical / 0 Important unresolved**.

Task 2 adds provider-independent `TokenCounter`, deterministic Unicode `LexicalTokenCounter`, and immutable validated `ChunkingConfig`. It introduces no provider, network, persistence, embedding, vector, queue, hook, REST, or WordPress execution behavior.

## Task 3 durable evidence
### Corrected RED
The first Task 3 test attempt is deliberately not counted as valid RED because PHPCS stopped before PHPUnit. All prematurely added Task 3 production files were removed before the corrected test-only RED.

- Valid corrected RED: `7dc00a29dfa4db8a7f7f627cdd6fa9c1c587b442` — `test: make M07 Task 3 RED fixtures quality-clean`.
- RED CI `33793246971`: PHPCS clean; PHPStan clean; PHPUnit **277 tests / 1281 assertions / 5 intended failures**, solely because `ChunkRecord` / `StructureAwareChunker` did not exist.

### GREEN lineage
- `6cb199122e788d574e2014d7e8d00b1166694ea1` — immutable `ChunkRecord`.
- `6e8ba2a82248519d4fc4fbe0aa1ca36d9f52d622` — `ChunkingException`.
- `068f16a54b75d52835a6d7ab334ab00d1b4ea083` — `StructureAwareChunker`.
- `e1dba40c194a4f7204cbaec7042ca4896b42b755` — PHPCS-only alignment correction.
- `ea025c5038857a1c43ff549fe0b5980fa79243b2` — statically explicit paragraph parser after PHPStan identified the by-reference closure dataflow problem.
- Final Task 3-only code head: `3b223cc22c41e35bbc3599f717606232ea976587`.

A same-session review briefly explored configured overlap. Re-reading the approved plan confirmed overlap belongs to Task 4 and Task 4 may not begin before Task 3 independent review. That exploratory overlap test/implementation was fully reverted; current Task 3 behavior contains no Task 4 overlap implementation.

### Verification / review
- Pre-review documentation head: `d227e804971e6a5cc40e5fdba8174e0d6a463614`.
- Exact-head CI `33794954967`: `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed.
- Same-session review `5105805077`: **0 Critical / 0 Important unresolved**, explicitly not independent.
- Independent fresh-session review `5105859046`: **0 Critical / 0 Important unresolved**.

The independent review covered readonly chunk state, exact source/document lineage fields, empty/tiny inputs, ATX heading lineage, blank-line paragraphs, sentence fallback, Unicode lexical fallback, code-point-safe final fallback, deterministic zero-based sequence, deterministic parent/chunk/content hashing via `DocumentHasher`, copied source metadata/language/visibility/URL/version/content lineage, configured token bounds, repeated-call determinism, untrusted-content non-execution, and absence of M08/M09/provider/network/persistence scope leakage.

## Security Review
Tasks 1-3 remain pure PHP and WordPress-independent. They do not execute retrieved content, fetch URLs, call providers, touch credentials, persist data, write embeddings/vectors, alter visibility, or add queue/REST/hook execution paths. Invalid UTF-8 token input fails closed through the token-counting contract.

## Performance Review
Task 1 performs bounded linear normalization passes. Task 2 token counting performs deterministic Unicode regex scanning. Task 3 parses lines and bounded recursive fallbacks without whole-document search or external calls. Large-document integration/performance evidence remains a Task 7 requirement.

## Code Review Findings
- Task 1 independent review `5104488263`: **0 Critical / 0 Important unresolved**.
- Task 2 independent review `5105069991`: **0 Critical / 0 Important unresolved**.
- Task 3 independent review `5105859046`: **0 Critical / 0 Important unresolved**.

## Active quality gate
Tasks 1-3 are complete. Task 4 may begin only with a genuine test-only RED on the active branch.

Task 4 RED must prove:
- configured overlap is bounded by `overlapTokens`;
- total output remains within `maxTokens`;
- overlap occurs only between adjacent chunks sharing the same structural parent;
- overlap never crosses section/document boundaries;
- every overlapped chunk contains new content and the algorithm terminates deterministically.

Only after the test-only SHA reaches intended PHPUnit failures after clean style/static-analysis gates may `StructureAwareChunker` be modified for overlap.

## Known Limitations
- Provider/model-exact tokenization remains intentionally deferred/injectable for M08.
- Overlap, deduplication, incremental planning, and end-to-end pipeline composition remain Tasks 4-7.

## Documentation Updated
This milestone ledger and `docs/progress/STATUS.md` carry the durable fresh-session handoff. Draft PR #9 tracks the active milestone branch.

## Exact next unfinished action
Begin **Task 4 — Deliberate bounded overlap** by adding only the approved overlap regression fixtures to `tests/Unit/Indexing/Chunking/StructureAwareChunkerTest.php`, commit that test-only state, and verify exact-SHA RED. Do not modify production chunker behavior before that RED evidence exists.

## Completion Checklist
All remaining mandatory gates remain required before M07 completion: Tasks 4-7 genuine TDD, independent review after each behavior task, whole-M07 review, exact-final-SHA full CI, durable docs, PR completion/merge, and fresh post-merge `main` CI.

## Next Milestone
M08 — Embeddings & Vector Stores.
