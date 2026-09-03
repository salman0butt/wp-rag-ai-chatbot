# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing

Status: **IN PROGRESS — Tasks 1-3 complete; Task 4 review finding fixed and GREEN, pending fresh-session independent re-review.**

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
- [ ] Task 4 — Deliberate bounded overlap. **Review finding fixed and exact-SHA GREEN; fresh-session independent re-review remains required.**
- [ ] Task 5 — Compatibility-safe deduplication.
- [ ] Task 6 — Incremental index planning.
- [ ] Task 7 — Source-to-index-plan integration and milestone closeout.

## Task 1 durable evidence
- RED `3e34a6d1125592a256463c3e75ee9406fa3e5e3a`; CI `33775003445`: PHPStan clean; PHPUnit 263 tests / 1252 assertions / 7 intended missing-class failures.
- GREEN `de41fd281d95f2a367df163ea66d8713357b8a14`; CI `33775193798`: all permanent jobs passed; PHPUnit 263 / 1252; Composer audit clean.
- Independent fresh-session review `5104488263`: **0 Critical / 0 Important unresolved**.

## Task 2 durable evidence
- RED `220bffa181cb4a32490f6fa35b3be6904ae790d6`; CI `33780572899`: PHPStan clean; PHPUnit 272 tests / 1261 assertions / 9 intended missing-contract failures.
- GREEN `f802bed14cc1887c219b4ac1058236ded114224c`; CI `33780799386`: all permanent jobs passed; PHPUnit 272 / 1276; Composer audit clean.
- Artifact `9903483875`, digest `sha256:f06d0f169bf7fa74718f976a3d63f05b3285347e2a45c24245c74c5a6138d388`.
- Independent fresh-session review `5105069991`: **0 Critical / 0 Important unresolved**.

## Task 3 durable evidence
- Corrected genuine RED `7dc00a29dfa4db8a7f7f627cdd6fa9c1c587b442`; CI `33793246971`: PHPCS/PHPStan clean; PHPUnit 277 tests / 1281 assertions / 5 intended failures.
- Final Task 3-only code head `3b223cc22c41e35bbc3599f717606232ea976587`.
- Exact documentation head CI `33794954967`: all permanent jobs green.
- Independent fresh-session review `5105859046`: **0 Critical / 0 Important unresolved**.

Task 3 established immutable chunk records, deterministic heading/paragraph/sentence/lexical/code-point splitting, zero-based sequence, stable hashes/parent lineage, copied source metadata and visibility/language/URL/version/content lineage, and provider/network/persistence-free behavior.

## Task 4 durable evidence
### Original RED / GREEN
The first two Task 4 test-only attempts are deliberately not counted as valid RED:
- `ea9c68b850fb091dce6bf531bd068a5cdce2ea0a` — PHPCS stopped on one alignment warning.
- `8c44877fc1ac739a2bd0262fe69ec88eefd9397a` — PHPCS stopped on the remaining assignment alignment group.

No Task 4 production implementation existed at either invalid attempt.

Valid corrected RED:
- `49f423bcbdfd1458be31b4e376c55ef269e32a39` — `test: align M07 overlap RED fixtures`.
- CI `33796054973`: PHPCS clean; PHPStan **No errors**; PHPUnit **280 tests / 1334 assertions / 2 intended failures**.
- Intended failures were exactly same-parent overlap and budget-reduced overlap.

Original GREEN:
- `aae5ab3861928bbcc2370d72a1a550c6c6eb2745` — `feat: add bounded structural chunk overlap`.
- CI `33796230348`: all permanent jobs passed; PHPUnit **280/280 / 1337 assertions**, PHPStan clean, Composer audit clean.
- Same-session review `5105957190`: **0 Critical / 0 Important unresolved**, explicitly not independent.

### Fresh-session independent review finding
- Fresh-session review `5106144751`: **0 Critical / 1 Important**.
- Finding: overlap capacity was measured through the injected `TokenCounter`, but that capacity was then treated as a count of lexical units. A counter whose budget units are larger than lexical units could therefore receive too much copied overlap and trigger final budget validation instead of reducing overlap.
- This violated the approved Task 4 requirement to use injected counter/budget rules and preserve all original new content within `maxTokens`.

### Review-fix TDD
- First review-regression test commit `2daf879f9dca2c2545721ba7f260b2c632d664cb` is **not counted as valid RED** because CI `33798621426` stopped at PHPCS before PHPUnit.
- Corrected genuine review-fix RED: `4f5f04fc8a5e98ed34ba1806c19ed5839568159e` — `test: align injected overlap RED fixture`.
- RED CI `33798780250`: PHPStan **No errors**; PHPUnit reached the intended behavior and reported **281 tests / 1337 assertions / 1 error**, exactly `ChunkingException: Chunk content violates the configured token budget` for the injected counter fixture.
- GREEN fix: `47f991ba738359738156f93072a146bfbee785ad` — `fix: respect injected overlap token budgets`.
- GREEN CI `33799042113`: all permanent jobs passed (`php-quality`, `js-quality`, `package`, `wordpress-smoke`). PHPStan **No errors**; PHPUnit **281/281 tests / 1341 assertions**; Composer audit found no vulnerabilities.
- Artifact `9910333596`, digest `sha256:200d6898db93c963fcb150ea268e30cb2dca395d381e984c50db2b7600c2fd36`.

### Task 4 behavior after fix
`StructureAwareChunker` performs one bounded overlap pass after base splitting and before hashes/records are finalized. It:
- copies at most configured trailing Unicode lexical units from the prior **base** descriptor;
- applies overlap only when adjacent descriptors have identical heading paths;
- never crosses a section or separate document call;
- starts from the configured/remaining overlap bound and reduces the candidate until the injected `TokenCounter` confirms `candidate + new content <= maxTokens`;
- preserves all original new content;
- computes final chunk hashes and token counts only after overlap settles;
- does not feed already-overlapped output into the next overlap source, avoiding cascading growth;
- adds no provider, network, persistence, embedding, vector, queue, hook, REST, or WordPress execution behavior.

## Security Review
Tasks 1-4 remain pure PHP and WordPress-independent. They do not execute retrieved content, fetch URLs, call providers, touch credentials, persist data, write embeddings/vectors, alter visibility, or add queue/REST/hook execution paths. Unicode parsing remains fail-closed on invalid UTF-8.

## Performance Review
Task 4 retains a single descriptor pass. Review-fix candidate reduction is bounded by configured `overlapTokens` (itself bounded to at most 25% of `maxTokens`), uses only the prior base descriptor, and does not recursively propagate overlap. Large-document integration/performance evidence remains a Task 7 requirement.

## Code Review Findings
- Task 1 independent review `5104488263`: **0 Critical / 0 Important unresolved**.
- Task 2 independent review `5105069991`: **0 Critical / 0 Important unresolved**.
- Task 3 independent review `5105859046`: **0 Critical / 0 Important unresolved**.
- Task 4 same-session review `5105957190`: **0 Critical / 0 Important unresolved**, not independent.
- Task 4 fresh-session review `5106144751`: **0 Critical / 1 Important**; finding fixed via regression TDD on `47f991ba738359738156f93072a146bfbee785ad`.

## Active quality gate
Task 4 is still not complete until a **new fresh-session independent re-review** inspects fixed GREEN code `47f991ba738359738156f93072a146bfbee785ad` and records **0 unresolved Critical / Important findings**. This session became the implementer after fixing review `5106144751`, so it must not self-certify the post-fix independent gate.

The next independent reviewer must verify the injected-counter fix, overlap bounds, same-parent isolation, original new-content retention, deterministic hashes/token counts, termination/performance, Unicode lexical slicing/fail-closed behavior, and absence of M08/M09/provider/network/persistence scope leakage. Task 5 must not begin before this gate closes.

## Known Limitations
- Provider/model-exact tokenization remains intentionally deferred/injectable for M08.
- Deduplication, incremental planning, and end-to-end pipeline composition remain Tasks 5-7.

## Documentation Updated
This milestone ledger records the fresh-session Task 4 finding, its genuine regression RED, exact-SHA GREEN fix and remaining independent re-review gate. Draft PR #9 remains the active M07 integration PR.

## Exact next unfinished action
Perform a **fresh-session independent re-review of Task 4** anchored to fixed GREEN `47f991ba738359738156f93072a146bfbee785ad` / CI `33799042113`. If that review records 0 unresolved Critical/Important findings, mark Task 4 complete in both durable ledgers and only then begin **Task 5 — Compatibility-safe deduplication** with a genuine test-only RED.

## Completion Checklist
All remaining mandatory gates remain required before M07 completion: Task 4 independent re-review, Tasks 5-7 genuine TDD and independent reviews, whole-M07 review, exact-final-SHA full CI, durable docs, PR completion/merge, and fresh post-merge `main` CI.

## Next Milestone
M08 — Embeddings & Vector Stores.
