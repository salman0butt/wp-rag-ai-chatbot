# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing

Status: **IN PROGRESS — Tasks 1-4 complete; Task 5 implementation GREEN after independent-review fix, pending fresh-session independent re-review.**

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
- [x] Task 4 — Deliberate bounded overlap. **Complete after three independent-review fixes and final clean fresh-session re-review `5107540703`.**
- [ ] Task 5 — Compatibility-safe deduplication. **Implementation GREEN after fresh independent-review finding; new fresh-session independent re-review pending.**
- [ ] Task 6 — Incremental index planning.
- [ ] Task 7 — Source-to-index-plan integration and milestone closeout.

## Completed task evidence

### Task 1
- RED `3e34a6d1125592a256463c3e75ee9406fa3e5e3a`; CI `33775003445`.
- GREEN `de41fd281d95f2a367df163ea66d8713357b8a14`; CI `33775193798`.
- Independent fresh-session review `5104488263`: **0 Critical / 0 Important unresolved**.

### Task 2
- RED `220bffa181cb4a32490f6fa35b3be6904ae790d6`; CI `33780572899`.
- GREEN `f802bed14cc1887c219b4ac1058236ded114224c`; CI `33780799386`; PHPUnit 272/272 / 1276 assertions.
- Artifact `9903483875`, digest `sha256:f06d0f169bf7fa74718f976a3d63f05b3285347e2a45c24245c74c5a6138d388`.
- Independent fresh-session review `5105069991`: **0 Critical / 0 Important unresolved**.

### Task 3
- Corrected genuine RED `7dc00a29dfa4db8a7f7f627cdd6fa9c1c587b442`; CI `33793246971`.
- Final Task 3 code `3b223cc22c41e35bbc3599f717606232ea976587`.
- Documentation-head CI `33794954967`: all permanent jobs green.
- Independent fresh-session review `5105859046`: **0 Critical / 0 Important unresolved**.

### Task 4
- Original genuine RED `49f423bcbdfd1458be31b4e376c55ef269e32a39`; CI `33796054973`; original GREEN `aae5ab3861928bbcc2370d72a1a550c6c6eb2745`; CI `33796230348`.
- Independent review `5106144751`: **0 Critical / 1 Important**, injected-counter remaining-capacity bug. Fixed via genuine RED `4f5f04fc8a5e98ed34ba1806c19ed5839568159e` / CI `33798780250` and GREEN `47f991ba738359738156f93072a146bfbee785ad` / CI `33799042113`.
- Independent review `5106687305`: **0 Critical / 1 Important**, configured overlap budget was still interpreted as lexical units. Fixed via corrected genuine RED `817839912b46022020e5019fb2d771d054799b83` / CI `33804895198` and GREEN `68af1fc882ecee55d9e7c6282353a48ded3f3fc1` / CI `33805024423`.
- Independent review `5107172233`: **0 Critical / 1 Important**, repeated identical headings could cross section boundaries. Fixed via genuine RED `4cc8105c19843d6ca689124612eaaabcc2e5e138` / CI `33810138595` and GREEN `ba4b3e28cde2eee2ea273d3d8546a7bad0a109b0` / CI `33810450726`.
- Final independent fresh-session re-review `5107540703` at durable head `ba1a14f11338bbecc83d660dc208ebcc1267b553` / CI `33810787511`: **0 Critical / 0 Important unresolved**.
- Task 4 is **COMPLETE**.

## Task 5 — Compatibility-safe deduplication

### Contract
`ChunkDeduplicator` is a pure-PHP deterministic stage returning immutable `ChunkDeduplicationResult` with ordered canonical chunks and duplicate -> canonical aliases. Compatibility fingerprints include normalized content, language, visibility, and embedding-compatibility identity. Public/private, language, and incompatible embedding spaces are hard dedup boundaries. Caller-owned `ChunkRecord` objects remain immutable.

### Initial Task 5 implementation evidence
- Same-session review found canonical selection could follow encounter order rather than deterministic `ChunkRecord::sequence`.
- Genuine regression RED `cffd2a65731dc67e83f71af4ee8d3ee40de0646e`; CI `33816189082`: PHPStan clean; PHPUnit **290 tests / 1361 assertions / 1 intended failure**.
- First production fix `f5e924f03ce50ed9163ab59d10dce4e0b201dc18` is not accepted as GREEN evidence because PHPCS stopped before PHPUnit.
- Candidate `51efdfc4facc94a56852a64a82a80211ae78753d` corrected canonical selection to lowest sequence, with stable `chunkKey` tie-break.

### Fresh independent Task 5 review finding
- Fresh-session independent review `5107854511` at candidate `51efdfc4facc94a56852a64a82a80211ae78753d`: **0 Critical / 1 Important**.
- Finding: canonical selection was deterministic within each fingerprint, but `canonicalChunks` were emitted when a fingerprint was first encountered; reversing distinct input groups therefore reversed canonical output. Duplicate aliases were likewise inserted in caller encounter order. This violated M07's byte-identical deterministic-output contract.

### Review-fix TDD
- First valid regression RED `b13475f36718a9d4e8dc605d825b47e0474b9e98`; CI `33819241603`: PHPStan **No errors**; PHPUnit **291 tests / 1363 assertions / exactly 1 intended failure**, proving distinct canonical groups followed caller encounter order.
- Consolidated test-only RED `c7991f59a7f00982ae78fbcc5987198155323471`; CI `33819403618`: PHPStan **No errors**; PHPUnit **292 tests / 1364 assertions / exactly 2 intended failures**, proving both canonical result order and duplicate-alias map order were encounter-dependent.
- GREEN `9e6c7cb9cfbecb6ec7a3a746dc6c7332d0d20f25` — `fix: make dedup output ordering deterministic`.
- GREEN behavior: compatibility grouping and lowest-sequence/stable-key canonical selection remain unchanged; emitted canonical chunks are deterministically ordered by `sequence` then `chunkKey`; duplicate aliases are deterministically ordered by duplicate `chunkKey`.
- Exact-SHA GREEN CI `33819541096`: all permanent jobs passed (`php-quality`, `js-quality`, `package`, `wordpress-smoke`); PHPStan **No errors**; PHPUnit **292/292 tests / 1365 assertions**; Composer audit clean.
- Artifact `9917817913`, digest `sha256:074cdc75d55d554950fc2620ab2b5b4441459aa2bcfd7784af4644df57ff91e1`.
- Same-session post-fix review `5107905687`: **0 Critical / 0 Important unresolved**, explicitly not independent.

## Security Review
Tasks 1-5 remain pure PHP and WordPress-independent. They do not execute retrieved content, fetch URLs, call providers, touch credentials, persist data, write embeddings/vectors, alter visibility, or add queue/REST/hook execution paths. Dedup compatibility explicitly includes visibility and embedding-compatibility identity, preventing cross-privacy/cross-space canonical sharing.

## Performance Review
Task 5 retains expected O(n) compatibility fingerprinting/grouping. Enforcing byte-identical presentation order adds sorting of canonical and alias outputs; this is bounded to the emitted result sets and is required to remove caller encounter order from observable output. No whole-document quadratic duplicate comparison was introduced.

## Code Review Findings
- Task 1 independent review `5104488263`: **0 Critical / 0 Important unresolved**.
- Task 2 independent review `5105069991`: **0 Critical / 0 Important unresolved**.
- Task 3 independent review `5105859046`: **0 Critical / 0 Important unresolved**.
- Task 4 final independent re-review `5107540703`: **0 Critical / 0 Important unresolved**; Task 4 complete.
- Task 5 fresh independent review `5107854511`: **0 Critical / 1 Important**, deterministic result-order defect fixed through strict RED/GREEN at `9e6c7cb9cfbecb6ec7a3a746dc6c7332d0d20f25`.
- Task 5 same-session post-fix review `5107905687`: **0 Critical / 0 Important unresolved**, not independent.

## Active quality gate
Task 5 is not complete until a **new fresh-session independent re-review** inspects GREEN `9e6c7cb9cfbecb6ec7a3a746dc6c7332d0d20f25` / CI `33819541096` and records **0 unresolved Critical / Important findings**. This session found and implemented review `5107854511`, so it cannot self-certify the post-fix independent gate.

The next independent reviewer must verify deterministic lowest-sequence canonical selection and stable tie-breaks; canonical output order independent of input encounter order; deterministic duplicate-alias key order and alias direction; normalized-content behavior; visibility/language/embedding-compatibility boundaries; caller immutability; bounded performance; and absence of M08/M09/provider/network/persistence/vector/WordPress execution scope leakage. Task 6 must not begin before this gate closes.

## Known Limitations
- Provider/model-exact tokenization remains intentionally deferred/injectable for M08.
- Incremental planning and end-to-end pipeline composition remain Tasks 6-7.

## Exact next unfinished action
Perform a **fresh-session independent re-review of Task 5** anchored to GREEN `9e6c7cb9cfbecb6ec7a3a746dc6c7332d0d20f25` / CI `33819541096`. If that review records 0 unresolved Critical/Important findings, mark Task 5 complete in both durable ledgers and only then begin **Task 6 — Incremental index planning** with a genuine test-only RED.

## Completion Checklist
All remaining mandatory gates remain required before M07 completion: Task 5 independent re-review, Tasks 6-7 genuine TDD and independent reviews, whole-M07 review, exact-final-SHA full CI, durable docs, PR completion/merge, and fresh post-merge `main` CI.

## Next Milestone
M08 — Embeddings & Vector Stores.
