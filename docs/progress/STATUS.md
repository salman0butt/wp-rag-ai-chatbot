# Global Status

- Completed milestones on `main`: **M00-M06**.
- Latest integrated milestone: **M06 — WooCommerce Knowledge Ingestion**.
- Current milestone: **M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing — IN PROGRESS (Tasks 1-3 complete; Task 4 third independent-review finding fixed and exact-SHA GREEN, pending fresh-session independent re-review)**.
- Current verified `main`: `747733a92c23d411ccba2592d5cb8c7858b95a03`.
- Latest verified `main` CI: `33770388757` — all permanent jobs passed.
- M06 integration merge: `356f419ea6df23e68d89a13ee322ca50585ed74b` via PR #8.

## M07 active state
- Feature branch: `feat/m07-chunking-dedup-indexing`.
- Draft PR: **#9 — `feat: build M07 chunking dedup incremental indexing`**.
- Milestone ledger: `docs/milestones/M07-chunking-dedup-indexing.md`.
- Design/spec: `docs/superpowers/specs/2026-09-03-m07-chunking-dedup-indexing-design.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m07-chunking-dedup-indexing.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- Architecture: canonical `DocumentRecord` -> deterministic normalization -> structure-aware bounded chunking -> stable chunk hashes/lineage -> compatibility-safe dedup -> pure incremental index plan.
- M08 owns embedding generation/vector stores/provider-exact compatibility; M09 owns queue/synchronization execution.
- Task 1 — Deterministic content normalization: **COMPLETE**. Independent review `5104488263`: 0 Critical / 0 Important unresolved.
- Task 2 — Token budget/configuration contracts: **COMPLETE**. Independent review `5105069991`: 0 Critical / 0 Important unresolved.
- Task 3 — Immutable chunk records and structure-aware splitting: **COMPLETE**. Independent review `5105859046`: 0 Critical / 0 Important unresolved.
- Task 4 — Deliberate bounded overlap: **THIRD INDEPENDENT-REVIEW FINDING FIXED + EXACT-SHA GREEN; PENDING NEW FRESH-SESSION INDEPENDENT RE-REVIEW**.
- Tasks 5–7 remain unstarted. Do not begin Task 5 until Task 4 independent re-review has 0 unresolved Critical/Important findings.

## M07 task evidence

### Task 1
- RED `3e34a6d1125592a256463c3e75ee9406fa3e5e3a`; CI `33775003445`.
- GREEN `de41fd281d95f2a367df163ea66d8713357b8a14`; CI `33775193798`.
- Independent review `5104488263`: **0 Critical / 0 Important unresolved**.

### Task 2
- RED `220bffa181cb4a32490f6fa35b3be6904ae790d6`; CI `33780572899`.
- GREEN `f802bed14cc1887c219b4ac1058236ded114224c`; CI `33780799386`; PHPUnit 272/272 / 1276 assertions.
- Artifact `9903483875`, digest `sha256:f06d0f169bf7fa74718f976a3d63f05b3285347e2a45c24245c74c5a6138d388`.
- Independent review `5105069991`: **0 Critical / 0 Important unresolved**.

### Task 3
- Corrected genuine RED `7dc00a29dfa4db8a7f7f627cdd6fa9c1c587b442`; CI `33793246971`.
- Final Task 3-only code `3b223cc22c41e35bbc3599f717606232ea976587`.
- Documentation-head CI `33794954967`: all permanent jobs green.
- Independent review `5105859046`: **0 Critical / 0 Important unresolved**.

### Task 4 original TDD
- Invalid early RED attempts: `ea9c68b850fb091dce6bf531bd068a5cdce2ea0a`, `8c44877fc1ac739a2bd0262fe69ec88eefd9397a` (PHPCS stopped before PHPUnit).
- Genuine RED `49f423bcbdfd1458be31b4e376c55ef269e32a39`; CI `33796054973`: PHPUnit 280 tests / 1334 assertions / 2 intended failures.
- Original GREEN `aae5ab3861928bbcc2370d72a1a550c6c6eb2745`; CI `33796230348`: all permanent jobs green, PHPUnit 280/280 / 1337 assertions.
- Same-session review `5105957190`: clean, not independent.

### Task 4 first independent finding and fix
- Independent review `5106144751`: **0 Critical / 1 Important** — remaining capacity was measured by injected `TokenCounter` but used as a lexical-unit count.
- Invalid first review-fix attempt `2daf879f9dca2c2545721ba7f260b2c632d664cb` / CI `33798621426` stopped at PHPCS.
- Genuine RED `4f5f04fc8a5e98ed34ba1806c19ed5839568159e`; CI `33798780250`: PHPUnit 281 tests / 1337 assertions / 1 intended error.
- GREEN `47f991ba738359738156f93072a146bfbee785ad`; CI `33799042113`: all permanent jobs green, PHPUnit 281/281 / 1341 assertions.
- Artifact `9910333596`, digest `sha256:200d6898db93c963fcb150ea268e30cb2dca395d381e984c50db2b7600c2fd36`.

### Task 4 second independent finding and fix
- Independent re-review `5106687305`: **0 Critical / 1 Important** — `overlapTokens` itself was still interpreted as lexical units under a non-lexical injected counter.
- Invalid fixture `a135dd8eb361b1c95722a927ba2a5b6ef7b6c258` / CI `33804508511`; premature fix `56b03b248e164ed3c118181c730bcbae7a47f74a` is not GREEN evidence.
- Corrected genuine RED `817839912b46022020e5019fb2d771d054799b83`; CI `33804895198`: PHPUnit 282 tests / 1343 assertions / 1 intended failure.
- GREEN `68af1fc882ecee55d9e7c6282353a48ded3f3fc1`; CI `33805024423`: all permanent jobs green, PHPUnit 282/282 / 1344 assertions.
- Artifact `9912573130`, digest `sha256:4d72466e2e2e754dd8de975cda84dfb9897b1f0d704850993c69ac7c0a570af3`.
- Same-session review `5106763646`: clean, not independent.

### Task 4 third independent finding and fix
- Fresh-session independent re-review `5107172233`: **0 Critical / 1 Important**.
- Finding: `apply_overlap()` compared only `heading_path`; adjacent distinct ATX sections with identical heading labels (for example `# Same ... # Same`) therefore received overlap across the real section boundary, violating the approved never-cross-sections requirement.
- Root cause: internal descriptors retained heading labels but no section-instance identity.
- Genuine test-only RED `4cc8105c19843d6ca689124612eaaabcc2e5e138` — `test: cover repeated heading overlap boundary`.
- RED CI `33810138595`: PHPStan **No errors**; PHPUnit **283 tests / 1346 assertions / 1 intended failure**. Expected second repeated-heading section to begin `one two three four five.` but old behavior produced `beta gamma delta. one two three four five.`.
- GREEN fix `ba4b3e28cde2eee2ea273d3d8546a7bad0a109b0` — `fix: isolate overlap by section instance`.
- Fix adds a deterministic internal `section_id` incremented on every ATX heading, propagates it through paragraph/base descriptors, and requires both equal heading path and equal section id before overlap. Public `ChunkRecord`, parent-key, heading-path, hash, and token-count contracts are unchanged.
- GREEN CI `33810450726`: all permanent jobs green (`php-quality`, `js-quality`, `package`, `wordpress-smoke`); PHPStan **No errors**; PHPUnit **283/283 / 1346 assertions**; Composer audit clean.
- Artifact `9914592158`, digest `sha256:8b8e68e38d8026c5eb433bc46962b3ab5932368af6339c3f1548cc096c008157`.
- Same-session post-fix review `5107220713`: **0 Critical / 0 Important unresolved**, explicitly **not independent**.

## Task 4 current behavior
`StructureAwareChunker` applies overlap only between adjacent base descriptors from the same deterministic section instance and identical heading path. It does not cross repeated-heading sections, different sections, or document calls. Candidate overlap must satisfy both injected-counter bounds: overlap cost <= configured `overlapTokens`, and overlap plus untouched new content <= `maxTokens`. Already-overlapped output is not propagated forward. Final hashes/token counts are calculated after overlap settles. M07 adds no provider, network, persistence, embedding, vector, queue, hook, REST, or WordPress execution behavior.

## Active quality gate
Task 4 is **not complete** until a new fresh-session independent re-review of GREEN `ba4b3e28cde2eee2ea273d3d8546a7bad0a109b0` / CI `33810450726` records **0 unresolved Critical / Important findings**. This session became the implementer after finding review `5107172233`, so same-session review `5107220713` cannot satisfy that gate. Do not begin Task 5 before the gate closes.

The next reviewer must verify: repeated-identical-heading section isolation; same-section overlap remains intact; both injected-counter bounds; original new-content preservation; deterministic public hashes/lineage/token counts; bounded termination/performance; Unicode/fail-closed behavior; and no M08/M09/provider/network/persistence leakage.

## Exact next unfinished action
Perform a **fresh-session independent re-review of M07 Task 4** anchored to GREEN `ba4b3e28cde2eee2ea273d3d8546a7bad0a109b0` / CI `33810450726`. If it reports 0 unresolved Critical/Important findings, mark Task 4 complete in durable docs and only then begin Task 5 — Compatibility-safe deduplication — with a genuine test-only RED.

## Previous milestone closeout
- M06 final durable `main`: `747733a92c23d411ccba2592d5cb8c7858b95a03`; CI `33770388757` green.
- M05 merge `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6` via PR #6; post-merge CI `33721014064` green.
