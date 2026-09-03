# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing

Status: **IN PROGRESS — Tasks 1 complete; Task 2 implementation/exact-code-head CI green with independent fresh-session review pending.**

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
- [x] Task 1 — Deterministic content normalization. **Complete: strict RED/GREEN, exact-head CI, and independent fresh-session review clean.**
- [ ] Task 2 — Token budget/configuration contracts. **Implementation + exact-code-head CI green; independent fresh-session review pending.**
- [ ] Task 3 — Immutable chunk records and structure-aware splitting.
- [ ] Task 4 — Deliberate bounded overlap.
- [ ] Task 5 — Compatibility-safe deduplication.
- [ ] Task 6 — Incremental index planning.
- [ ] Task 7 — Source-to-index-plan integration and milestone closeout.

## Task 1 durable evidence
Task 1 introduces only `ContentNormalizer`, a WordPress-independent canonical whitespace normalizer. It converts CRLF/lone CR to LF, removes only a leading UTF-8 BOM, strips trailing horizontal spaces/tabs per line, collapses runs of three or more line feeds to two, trims document-edge spaces/tabs/newlines, and otherwise preserves literal content including prompt-like/HTML-like/shortcode/PHP-like text.

### Task 1 RED
- Test-only commit: `3e34a6d1125592a256463c3e75ee9406fa3e5e3a` — `test: define M07 content normalization behavior`.
- CI: `33775003445`.
- PHPStan: **clean** before PHPUnit.
- PHPUnit: **263 tests / 1252 assertions / 7 expected failures**.
- Every failure was a new `ContentNormalizerTest` behavior and failed exactly with `ContentNormalizer class does not exist yet.`

### Task 1 GREEN / review
- Minimal production commit: `de41fd281d95f2a367df163ea66d8713357b8a14` — `feat: add deterministic content normalizer`.
- Exact-code-head CI: `33775193798` — all permanent jobs passed.
- PHPStan clean; PHPUnit **263 tests / 1252 assertions**, all passed; Composer audit clean.
- Artifact: `9901292022`, 712652 bytes, digest `sha256:1457cf8e2c625978959806ceb0e75c2409460a2ac3db4ad03e738de78873c552`.
- Same-session review `5104040492`: **0 Critical / 0 Important unresolved**, explicitly not independent.
- Independent fresh-session review `5104488263`: **0 Critical / 0 Important unresolved**. It re-reviewed normalization semantics and exact-head GitHub evidence from repository state rather than relying on conversational state.
- Fresh documentation-head CI observed during that review: `33775697649` on `8493eec0843b3b85058ffa73f3300fee770eb1c5`, all permanent jobs green; artifact `9901483331`, digest `sha256:b8ea69a97149a7554809f4d83e1df8bc4ffb8d5b8fee88546d7b042067a5aabe`.

## Task 2 durable evidence
Task 2 introduces provider-independent chunk-budget contracts only:
- `TokenCounter` exposes deterministic `count(string): int` behavior.
- `LexicalTokenCounter` counts Unicode letter/number runs plus individual non-whitespace punctuation/symbol units and fails closed on invalid UTF-8.
- immutable `ChunkingConfig` validates `maxTokens` 32..4096, overlap >=0 and <=25% of max, non-blank chunking version, and a null-or-non-blank embedding compatibility key.
- `ChunkingConfig::fingerprint()` reuses canonical `DocumentHasher` SHA-256 hashing and includes every compatibility input.

### Task 2 RED
- Test-only commit: `220bffa181cb4a32490f6fa35b3be6904ae790d6` — `test: define M07 token budget contracts`.
- PR CI: `33780572899`.
- PHPStan: **clean** before PHPUnit.
- PHPUnit: **272 tests / 1261 assertions / 9 expected failures**.
- Five failures were exactly `ChunkingConfig class does not exist yet.` and four were exactly `TokenCounter/LexicalTokenCounter contracts do not exist yet.`
- No Task 2 production file existed in the RED commit.

### Task 2 GREEN
- Minimal production commit: `f802bed14cc1887c219b4ac1058236ded114224c` — `feat: add M07 token budget contracts`.
- Exact-code-head PR CI: `33780799386` — **all permanent jobs passed**.
- PHPStan: clean across 106 analyzed files.
- PHPUnit: **272 / 272 tests, 1276 assertions**, all passed.
- Composer audit: no security vulnerability advisories.
- `php-quality`, `js-quality`, `package`, and `wordpress-smoke`: all passed, including activation, database, providers, knowledge, file-ingestion, and WooCommerce knowledge smoke coverage.
- Artifact: `9903483875`, 714589 bytes, digest `sha256:f06d0f169bf7fa74718f976a3d63f05b3285347e2a45c24245c74c5a6138d388`.
- Same-session Task 2 review `5104539329`: **0 Critical / 0 Important unresolved**, explicitly **not independent**.

## Active quality gate
Task 2 is **not complete** until a genuinely independent fresh-session review reports **0 Critical / 0 Important unresolved**. Task 3 must not begin before that gate.

## Security Review
Tasks 1-2 remain pure PHP and WordPress-independent. They do not parse or execute retrieved content, make provider/network calls, touch credentials, fetch URLs, persist data, write vectors, alter visibility, or add queue/REST/hook execution paths. Invalid UTF-8 token input fails closed with `DomainException`.

## Performance Review
Task 1 performs bounded linear normalization passes. Task 2 token counting performs one Unicode regex pass. Configuration validation and fingerprinting are bounded constant-size operations. No network/runtime dependency is introduced.

## Code Review Findings
- Task 1 independent review `5104488263`: **0 Critical / 0 Important unresolved** — Task 1 complete.
- Task 2 same-session review `5104539329`: **0 Critical / 0 Important unresolved**, but independent review remains pending by policy.

## Fresh Verification Results
- Task 1 RED CI `33775003445`; GREEN CI `33775193798`; independent-review documentation-head CI `33775697649` green.
- Task 2 RED CI `33780572899`: intended nine missing-contract behavior failures after clean PHPStan.
- Task 2 GREEN CI `33780799386`: all four permanent jobs passed; PHPUnit 272/272 and Composer audit clean.

## Commits
- `3f74b5564303b08caedb112a5c275614ce2413bd` — M07 design/spec.
- `6e9cc106bc26963073ba7b02867bda4f5e1af231` — M07 implementation plan.
- `3e34a6d1125592a256463c3e75ee9406fa3e5e3a` — Task 1 behavioral RED tests.
- `de41fd281d95f2a367df163ea66d8713357b8a14` — Task 1 minimal GREEN implementation.
- `220bffa181cb4a32490f6fa35b3be6904ae790d6` — Task 2 behavioral RED tests.
- `f802bed14cc1887c219b4ac1058236ded114224c` — Task 2 minimal GREEN implementation.

## Files Changed so far
- `docs/superpowers/specs/2026-09-03-m07-chunking-dedup-indexing-design.md`
- `docs/superpowers/plans/2026-09-03-m07-chunking-dedup-indexing.md`
- `tests/Unit/Indexing/Normalization/ContentNormalizerTest.php`
- `src/Indexing/Normalization/ContentNormalizer.php`
- `tests/Unit/Indexing/Chunking/LexicalTokenCounterTest.php`
- `tests/Unit/Indexing/Chunking/ChunkingConfigTest.php`
- `src/Indexing/Chunking/TokenCounter.php`
- `src/Indexing/Chunking/LexicalTokenCounter.php`
- `src/Indexing/Chunking/ChunkingConfig.php`

## Known Limitations
- Provider/model-exact tokenization remains intentionally deferred/injectable for M08.
- Tasks 3-7 are not implemented.

## Documentation Updated
This ledger and `docs/progress/STATUS.md` carry the durable fresh-session handoff. Draft PR #9 tracks the active milestone branch.

## Exact next unfinished action
Independently review Task 2 from RED `220bffa181cb4a32490f6fa35b3be6904ae790d6` through GREEN `f802bed14cc1887c219b4ac1058236ded114224c` and the following documentation head. Focus on Unicode lexical-unit semantics, invalid-UTF-8 failure behavior, config bounds, exact 25% overlap edge behavior, immutability, deterministic/canonical fingerprinting, embedding compatibility identity, and absence of M08/M09/provider/network/persistence leakage. Route any Critical/Important behavioral finding through regression TDD and exact-head GREEN CI. Only after a clean independent review may Task 2 be checked complete and Task 3 begin with a genuine test-only RED commit.

## Completion Checklist
All mandatory gates remain required before M07 completion: every task TDD, independent review, full exact-final-SHA CI, durable docs, PR merge, and fresh post-merge `main` CI.

## Next Milestone
M08 — Embeddings & Vector Stores.
