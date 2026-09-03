# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing

Status: **IN PROGRESS — Tasks 1-3 complete; Task 4 third independent-review finding fixed and exact-SHA GREEN, pending fresh-session independent re-review.**

## Goal
Create deterministic normalized content/chunks with traceability, deduplication, hashes, and incremental reindex decisions.

## Dependencies
M04-M06 source/document contracts.

## Design / plan
- Design/spec: `docs/superpowers/specs/2026-09-03-m07-chunking-dedup-indexing-design.md`.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m07-chunking-dedup-indexing.md`.
- Status: **AUTO-APPROVED — SCHEDULED MODE** after repository-mandated self-review.
- Architecture: pure-PHP deterministic `DocumentRecord -> normalize -> structure-aware chunk -> hash/lineage -> compatibility-safe dedup -> incremental index plan`.
- M08 retains embedding/vector-store ownership; M09 retains background queue/synchronization ownership.

## In Scope
Structure-aware recursive chunking; heading/paragraph/sentence/token limits; configurable overlap; parent-child/sequence metadata; hashes; dedup; source/index versions; affected-chunk decisions.

## Out of Scope
Actual embeddings/vector upserts (M08), async execution engine (M09).

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
- [ ] Task 4 — Deliberate bounded overlap. **Third independent-review finding fixed and exact-SHA GREEN; a new fresh-session independent re-review remains required.**
- [ ] Task 5 — Compatibility-safe deduplication.
- [ ] Task 6 — Incremental index planning.
- [ ] Task 7 — Source-to-index-plan integration and milestone closeout.

## Task 1 durable evidence
- RED `3e34a6d1125592a256463c3e75ee9406fa3e5e3a`; CI `33775003445`.
- GREEN `de41fd281d95f2a367df163ea66d8713357b8a14`; CI `33775193798`.
- Independent fresh-session review `5104488263`: **0 Critical / 0 Important unresolved**.

## Task 2 durable evidence
- RED `220bffa181cb4a32490f6fa35b3be6904ae790d6`; CI `33780572899`.
- GREEN `f802bed14cc1887c219b4ac1058236ded114224c`; CI `33780799386`; PHPUnit 272/272 / 1276 assertions.
- Artifact `9903483875`, digest `sha256:f06d0f169bf7fa74718f976a3d63f05b3285347e2a45c24245c74c5a6138d388`.
- Independent fresh-session review `5105069991`: **0 Critical / 0 Important unresolved**.

## Task 3 durable evidence
- Corrected genuine RED `7dc00a29dfa4db8a7f7f627cdd6fa9c1c587b442`; CI `33793246971`.
- Final Task 3-only code `3b223cc22c41e35bbc3599f717606232ea976587`.
- Documentation-head CI `33794954967`: all permanent jobs green.
- Independent fresh-session review `5105859046`: **0 Critical / 0 Important unresolved**.

## Task 4 durable evidence

### Original RED / GREEN
- Early test-only attempts `ea9c68b850fb091dce6bf531bd068a5cdce2ea0a` and `8c44877fc1ac739a2bd0262fe69ec88eefd9397a` are not valid RED because PHPCS stopped before PHPUnit.
- Genuine RED `49f423bcbdfd1458be31b4e376c55ef269e32a39`; CI `33796054973`: PHPStan clean; PHPUnit 280 tests / 1334 assertions / 2 intended failures.
- Original GREEN `aae5ab3861928bbcc2370d72a1a550c6c6eb2745`; CI `33796230348`: all permanent jobs green; PHPUnit 280/280 / 1337 assertions.
- Same-session review `5105957190`: clean, explicitly not independent.

### First fresh-session independent review finding
- Review `5106144751`: **0 Critical / 1 Important**.
- Finding: remaining capacity was measured through injected `TokenCounter`, then incorrectly treated as a lexical-unit count.
- Invalid first review-fix RED `2daf879f9dca2c2545721ba7f260b2c632d664cb` / CI `33798621426` stopped before PHPUnit.
- Corrected genuine RED `4f5f04fc8a5e98ed34ba1806c19ed5839568159e`; CI `33798780250`: PHPUnit 281 tests / 1337 assertions / 1 intended error.
- GREEN `47f991ba738359738156f93072a146bfbee785ad`; CI `33799042113`: all permanent jobs green; PHPUnit 281/281 / 1341 assertions.
- Artifact `9910333596`, digest `sha256:200d6898db93c963fcb150ea268e30cb2dca395d381e984c50db2b7600c2fd36`.

### Second fresh-session independent review finding
- Review `5106687305`: **0 Critical / 1 Important**.
- Finding: configured `overlapTokens` was still interpreted as lexical units rather than injected-counter budget units when destination capacity was not limiting.
- Invalid fixture `a135dd8eb361b1c95722a927ba2a5b6ef7b6c258` / CI `33804508511`; premature fix `56b03b248e164ed3c118181c730bcbae7a47f74a` is not GREEN evidence.
- Corrected genuine RED `817839912b46022020e5019fb2d771d054799b83`; CI `33804895198`: PHPUnit 282 tests / 1343 assertions / 1 intended failure.
- GREEN `68af1fc882ecee55d9e7c6282353a48ded3f3fc1`; CI `33805024423`: all permanent jobs green; PHPUnit 282/282 / 1344 assertions.
- Artifact `9912573130`, digest `sha256:4d72466e2e2e754dd8de975cda84dfb9897b1f0d704850993c69ac7c0a570af3`.
- Same-session post-fix review `5106763646`: clean, explicitly not independent.

### Third fresh-session independent review finding
- Fresh-session re-review `5107172233`: **0 Critical / 1 Important**.
- Finding: `apply_overlap()` compared only `heading_path`, so two distinct adjacent sections with the same ATX heading text (for example `# Same ... # Same`) were indistinguishable and overlap crossed the actual section boundary.
- This violated the approved Task 4 rule that overlap never crosses section/section-parent boundaries.
- Root cause: internal chunk descriptors preserved heading labels but had no deterministic section-instance identity.

### Third review-fix TDD
- Genuine test-only RED `4cc8105c19843d6ca689124612eaaabcc2e5e138` — `test: cover repeated heading overlap boundary`.
- RED CI `33810138595`: PHPStan **No errors**; PHPUnit **283 tests / 1346 assertions / 1 intended failure**. Expected second repeated-heading section `one two three four five.`; old behavior produced `beta gamma delta. one two three four five.`.
- GREEN `ba4b3e28cde2eee2ea273d3d8546a7bad0a109b0` — `fix: isolate overlap by section instance`.
- Implementation adds an internal deterministic `section_id` incremented on every ATX heading, propagates it through paragraph/base descriptors, and requires both matching heading path and section id before overlap. Public `ChunkRecord`, heading-path, parent-key, chunk-key/content-hash, and token-count contracts are unchanged.
- GREEN CI `33810450726`: all permanent jobs passed (`php-quality`, `js-quality`, `package`, `wordpress-smoke`); PHPStan **No errors**; PHPUnit **283/283 tests / 1346 assertions**; Composer audit found no vulnerabilities.
- Artifact `9914592158`, digest `sha256:8b8e68e38d8026c5eb433bc46962b3ab5932368af6339c3f1548cc096c008157`.
- Same-session post-fix review `5107220713`: **0 Critical / 0 Important unresolved**, explicitly not independent.

## Task 4 behavior after third fix
`StructureAwareChunker` performs one bounded overlap pass after base splitting and before final hashes/records. It:
- identifies every ATX-heading occurrence as a deterministic internal section instance, including repeated identical heading labels;
- applies overlap only when adjacent base descriptors have both the same section instance and same heading path;
- rejects/shrinks overlap whose injected `TokenCounter` cost exceeds configured `overlapTokens`;
- independently rejects/shrinks overlap + untouched new content whose injected-counter cost exceeds `maxTokens`;
- preserves all original new content;
- uses the previous base descriptor rather than already-overlapped output, avoiding cascading growth;
- computes final public hashes and token counts after overlap settles;
- does not alter public chunk lineage/hash contracts and adds no provider, network, persistence, embedding, vector, queue, hook, REST, or WordPress execution behavior.

## Security Review
Tasks 1-4 remain pure PHP and WordPress-independent. They do not execute retrieved content, fetch URLs, call providers, touch credentials, persist data, write embeddings/vectors, alter visibility, or add queue/REST/hook execution paths. Unicode parsing remains fail-closed on invalid UTF-8.

## Performance Review
Task 4 remains a single descriptor pass. Candidate reduction is bounded by configured overlap. Section ids are monotonically incremented during the existing linear heading parse and add O(1) comparison per descriptor. No whole-document search or recursive overlap propagation was introduced.

## Code Review Findings
- Task 1 independent review `5104488263`: **0 Critical / 0 Important unresolved**.
- Task 2 independent review `5105069991`: **0 Critical / 0 Important unresolved**.
- Task 3 independent review `5105859046`: **0 Critical / 0 Important unresolved**.
- Task 4 first independent review `5106144751`: **0 Critical / 1 Important**, fixed via TDD.
- Task 4 second independent review `5106687305`: **0 Critical / 1 Important**, fixed via TDD.
- Task 4 third independent review `5107172233`: **0 Critical / 1 Important**, fixed via TDD at `ba4b3e28cde2eee2ea273d3d8546a7bad0a109b0`.
- Task 4 same-session post-fix review `5107220713`: **0 Critical / 0 Important unresolved**, not independent.

## Active quality gate
Task 4 is still not complete until a **new fresh-session independent re-review** inspects GREEN `ba4b3e28cde2eee2ea273d3d8546a7bad0a109b0` / CI `33810450726` and records **0 unresolved Critical / Important findings**. This session found and implemented review `5107172233`, so it cannot self-certify the post-fix independent gate.

The next independent reviewer must verify repeated-identical-heading section isolation; same-section overlap remains intact; both injected-counter bounds (`overlapTokens` and combined `maxTokens`); original new-content retention; deterministic public hashes/token counts/lineage; bounded termination/performance; Unicode lexical slicing/fail-closed behavior; and absence of M08/M09/provider/network/persistence scope leakage. Task 5 must not begin before this gate closes.

## Known Limitations
- Provider/model-exact tokenization remains intentionally deferred/injectable for M08.
- Deduplication, incremental planning, and end-to-end pipeline composition remain Tasks 5-7.

## Exact next unfinished action
Perform a **fresh-session independent re-review of Task 4** anchored to GREEN `ba4b3e28cde2eee2ea273d3d8546a7bad0a109b0` / CI `33810450726`. If that review records 0 unresolved Critical/Important findings, mark Task 4 complete in both durable ledgers and only then begin **Task 5 — Compatibility-safe deduplication** with a genuine test-only RED.

## Completion Checklist
All remaining mandatory gates remain required before M07 completion: Task 4 independent re-review, Tasks 5-7 genuine TDD and independent reviews, whole-M07 review, exact-final-SHA full CI, durable docs, PR completion/merge, and fresh post-merge `main` CI.

## Next Milestone
M08 — Embeddings & Vector Stores.
