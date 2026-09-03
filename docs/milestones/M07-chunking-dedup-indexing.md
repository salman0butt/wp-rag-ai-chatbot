# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing

Status: **IN PROGRESS — design/spec and implementation plan auto-approved; Task 1 implementation/exact-code-head CI green with independent review pending.**

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
- [ ] Task 1 — Deterministic content normalization. **Implementation + exact-code-head CI green; independent fresh-session review pending.**
- [ ] Task 2 — Token budget/configuration contracts.
- [ ] Task 3 — Immutable chunk records and structure-aware splitting.
- [ ] Task 4 — Deliberate bounded overlap.
- [ ] Task 5 — Compatibility-safe deduplication.
- [ ] Task 6 — Incremental index planning.
- [ ] Task 7 — Source-to-index-plan integration and milestone closeout.

## Task 1 durable evidence
Task 1 introduces only `ContentNormalizer`, a WordPress-independent canonical whitespace normalizer. It converts CRLF/lone CR to LF, removes only a leading UTF-8 BOM, strips trailing horizontal spaces/tabs per line, collapses runs of three or more line feeds to two, trims document-edge spaces/tabs/newlines, and otherwise preserves literal content including prompt-like/HTML-like/shortcode/PHP-like text.

### RED
- Test-only commit: `3e34a6d1125592a256463c3e75ee9406fa3e5e3a` — `test: define M07 content normalization behavior`.
- CI: `33775003445`.
- PHPStan: **clean** before PHPUnit.
- PHPUnit: **263 tests / 1252 assertions / 7 expected failures**.
- Every failure was a new `ContentNormalizerTest` behavior and failed exactly with `ContentNormalizer class does not exist yet.`
- The RED covers CRLF/CR, leading BOM, trailing horizontal whitespace, blank-run collapse, edge trimming while preserving internal spacing, instruction/markup-like literal preservation, embedded-BOM preservation, idempotence, and whitespace-only content.

### GREEN
- Minimal production commit: `de41fd281d95f2a367df163ea66d8713357b8a14` — `feat: add deterministic content normalizer`.
- Exact-code-head CI: `33775193798` — **all permanent jobs passed**.
- PHPStan: clean.
- PHPUnit: **263 tests / 1252 assertions**, all passed.
- Composer audit: no security vulnerability advisories.
- `php-quality`, `js-quality`, `package`, `wordpress-smoke`: all passed, including existing WordPress/file/WooCommerce smoke coverage.
- Artifact: `9901292022`, 712652 bytes, digest `sha256:1457cf8e2c625978959806ceb0e75c2409460a2ac3db4ad03e738de78873c552`.
- Same-session review `5104040492`: **0 Critical / 0 Important unresolved in the Task 1 code/test unit**, explicitly **not independent**.

## Active quality gate
Task 1 is **not complete** until a genuinely independent fresh-session review reports **0 Critical / 0 Important unresolved**. Task 2 must not begin before that gate.

## Security Review
Task 1 does not parse or execute content. Prompt-like strings, HTML-like markup, shortcodes, and PHP-like snippets are preserved as literal untrusted data. No provider, credential, tool, URL-fetch, WordPress execution, or visibility change path is introduced.

## Performance Review
Task 1 performs bounded linear passes over the input string/lines. No whole-document nested search or network/runtime dependency is introduced.

## Code Review Findings
Same-session Task 1 review: **0 Critical / 0 Important unresolved**, but independent review is pending by policy.

## Fresh Verification Results
- RED CI `33775003445`: expected seven behavior failures after clean PHPStan.
- GREEN CI `33775193798`: all four permanent jobs passed; PHPUnit 263/263.

## Commits
- `3f74b5564303b08caedb112a5c275614ce2413bd` — M07 design/spec.
- `6e9cc106bc26963073ba7b02867bda4f5e1af231` — M07 implementation plan.
- `3e34a6d1125592a256463c3e75ee9406fa3e5e3a` — Task 1 behavioral RED tests.
- `de41fd281d95f2a367df163ea66d8713357b8a14` — Task 1 minimal GREEN implementation.

## Files Changed so far
- `docs/superpowers/specs/2026-09-03-m07-chunking-dedup-indexing-design.md`
- `docs/superpowers/plans/2026-09-03-m07-chunking-dedup-indexing.md`
- `tests/Unit/Indexing/Normalization/ContentNormalizerTest.php`
- `src/Indexing/Normalization/ContentNormalizer.php`

## Known Limitations
- Provider/model-exact tokenization remains intentionally deferred/injectable for M08.
- Tasks 2–7 are not implemented.

## Documentation Updated
This ledger and `docs/progress/STATUS.md` carry the durable fresh-session handoff. Draft PR #9 tracks the active milestone branch.

## Exact next unfinished action
Independently review Task 1 from RED `3e34a6d1125592a256463c3e75ee9406fa3e5e3a` through GREEN `de41fd281d95f2a367df163ea66d8713357b8a14`, focused on meaning preservation, leading-BOM-only behavior, line-ending normalization, trailing horizontal whitespace, blank-run collapse, idempotence, literal untrusted-text preservation, and M04-M06 canonical-content compatibility. Route any Critical/Important behavioral finding through regression TDD and exact-head GREEN CI. Only after a clean independent review may Task 1 be checked complete and Task 2 begin.

## Completion Checklist
All mandatory gates remain required before M07 completion: every task TDD, independent review, full exact-final-SHA CI, durable docs, PR merge, and fresh post-merge `main` CI.

## Next Milestone
M08 — Embeddings & Vector Stores.
