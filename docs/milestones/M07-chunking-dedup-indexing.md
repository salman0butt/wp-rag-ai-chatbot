# M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing

Status: **IN PROGRESS — Tasks 1-5 complete; Task 6 implementation GREEN after token-count invalidation review fix, pending fresh-session independent re-review.**

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
- [x] Task 5 — Compatibility-safe deduplication. **Complete after deterministic-output fix and clean fresh-session re-review `5108150441`.**
- [ ] Task 6 — Incremental index planning. **Implementation GREEN after visibility, indexed-metadata, and token-count review fixes; fresh-session independent re-review pending.**
- [ ] Task 7 — Source-to-index-plan integration and milestone closeout.

## Completed task evidence

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
- Final Task 3 code `3b223cc22c41e35bbc3599f717606232ea976587`.
- Documentation-head CI `33794954967`: all permanent jobs green.
- Independent review `5105859046`: **0 Critical / 0 Important unresolved**.

### Task 4
- Original RED/GREEN: `49f423bcbdfd1458be31b4e376c55ef269e32a39` / CI `33796054973`; `aae5ab3861928bbcc2370d72a1a550c6c6eb2745` / CI `33796230348`.
- Independent findings `5106144751`, `5106687305`, and `5107172233` were fixed through genuine review-specific RED/GREEN cycles.
- Final independent fresh-session re-review `5107540703` at durable head `ba1a14f11338bbecc83d660dc208ebcc1267b553` / CI `33810787511`: **0 Critical / 0 Important unresolved**.
- Task 4 is **COMPLETE**.

## Task 5 — Compatibility-safe deduplication

### Contract
`ChunkDeduplicator` is a pure-PHP deterministic stage returning immutable `ChunkDeduplicationResult` with ordered canonical chunks and duplicate -> canonical aliases. Compatibility fingerprints include normalized content, language, visibility, and embedding-compatibility identity. Public/private, language, and incompatible embedding spaces are hard dedup boundaries. Caller-owned `ChunkRecord` objects remain immutable.

### Evidence
- Earlier canonical-selection review regression RED `cffd2a65731dc67e83f71af4ee8d3ee40de0646e` / CI `33816189082`; candidate `51efdfc4facc94a56852a64a82a80211ae78753d` fixed lowest-sequence/stable-key canonical selection.
- Fresh independent review `5107854511`: **0 Critical / 1 Important** — canonical and alias presentation still exposed input encounter order.
- Consolidated genuine RED `c7991f59a7f00982ae78fbcc5987198155323471` / CI `33819403618`: PHPStan clean; PHPUnit **292 tests / 1364 assertions / exactly 2 intended failures**.
- GREEN `9e6c7cb9cfbecb6ec7a3a746dc6c7332d0d20f25`; CI `33819541096`: all permanent jobs green; PHPUnit **292/292 / 1365 assertions**; Composer audit clean.
- Artifact `9917817913`, digest `sha256:074cdc75d55d554950fc2620ab2b5b4441459aa2bcfd7784af4644df57ff91e1`.
- Same-session review `5107905687`: **0 Critical / 0 Important unresolved**, not independent.
- Fresh-session independent re-review `5108150441`: **0 Critical / 0 Important unresolved**. Task 5 is **COMPLETE**.

## Task 6 — Incremental index planning

### Contract
`IncrementalIndexPlanner` is a pure-PHP side-effect-free comparison stage:

`plan(array $previousChunks, ChunkDeduplicationResult $current): IndexPlan`

Immutable `IndexPlan` exposes deterministic:
- `upsert`: new or changed current canonical chunks;
- `deleteKeys`: previous canonical keys absent from current output;
- `unchanged`: reusable current canonical chunks;
- `duplicateAliases`: duplicate -> canonical traceability from dedup.

Comparison uses chunk-key maps for expected O(n) set comparison; public presentation is deterministically sorted. Embedding/vector execution remains M08 and queue/synchronization remains M09.

### Initial TDD
- Test-only attempt `58a785aa2317a6032c72c15734efe38b525b2cf6` / CI `33823402476` is **not valid RED** because PHPCS stopped before PHPUnit on fixture documentation.
- Corrected genuine RED `ad550672552b54afcf2d6ef05ee72729a3f4c0cf` / CI `33823467764`: PHPStan **No errors**; PHPUnit **300 tests / 1373 assertions / exactly 8 intended failures**, all because the planner contract did not exist.
- Initial production candidate `60721e4af7dcd05479a22f27118e74b085deafc4` added `IndexPlan` and `IncrementalIndexPlanner`; its initial CI was not accepted as final GREEN because PHP quality was not yet clean.

### Visibility review finding and strict fix
- Same-session review `5108179436`: **0 Critical / 1 Important** — a current chunk could keep the same key/content hash while changing `visibility`, causing a `public -> private` transition to be classified `unchanged` and risking stale access metadata downstream.
- Regression attempts `1233ea599f882d36f398f6c524ec655cec24c6e8` / CI `33823740164` and `371222f7d481fb29b780276e6ef07414cec92e92` / CI `33823791538` are **not valid RED evidence** because PHPCS stopped before PHPUnit.
- Corrected genuine privacy RED `9e8bdbd7109ea77cdefba5ac8f369c228dd5317b` / CI `33823898874`: PHPStan **No errors**; PHPUnit **301 tests / 1390 assertions / exactly 1 intended failure**.
- Visibility GREEN `508901561e2a3119edb251b2537897880851276f`; CI `33823962753`: final matrix green after retrying one transient npm audit endpoint failure without source changes; PHPUnit **301/301 / 1391 assertions**; Composer audit clean.
- Artifact `9919277309`, digest `sha256:01760ebd1e7dbd9f48e1a0fab7f936e15ce53159b9528bfb8aaa0cce8edfdd50`.
- Same-session post-fix review `5108240331`: **0 Critical / 0 Important unresolved**, explicitly not independent.

### Fresh independent metadata review and strict fix
- Fresh-session independent review `5108289931`: **0 Critical / 1 Important** — reuse still omitted language and indexed/citation metadata. A stable key/content chunk could change language, title, source version/document hash, or source metadata yet remain `unchanged`, preserving stale index/citation records.
- Genuine test-only metadata RED `9523adb6362de793d1ed7283c5f006bfb4c09aab` / CI `33825278282`: PHPStan **No errors**; PHPUnit **303 tests / 1393 assertions / exactly 2 intended failures**, specifically language and title/source-metadata invalidation.
- Metadata GREEN `9c5a3ecce96bbd3f5bd37647949b67c32b436963` — reuse now requires unchanged content hash, document type, title, source version, document content hash, language, visibility, chunking fingerprint, embedding compatibility key, and canonically hashed source metadata. Canonical URL/source/structural changes remain represented by content hash or chunk-key identity.
- Exact-SHA CI `33825367919`: `php-quality` ✅, `js-quality` ✅, `package` ✅, `wordpress-smoke` ✅; PHPStan **No errors**; PHPUnit **303/303 / 1395 assertions**; Composer audit clean.
- Artifact `9919760984`, digest `sha256:f0abedd88e5bc6e3f00e1f6730a5388cb993adf7f167ab0bc390be6a543e2098`.
- Same-session post-fix review `5108316220`: **0 Critical / 0 Important unresolved**, explicitly not independent because this session discovered and implemented the fix.

### Fresh independent token-count review and strict fix
- Fresh-session independent re-review `5108416626`: **0 Critical / 1 Important** — `ChunkRecord::tokenCount` was not compared for reuse. Because M08 may inject a more exact `TokenCounter` while the counter itself is not part of `ChunkingConfig::fingerprint()`, the same key/content/config could carry a different current token count but still be emitted as `unchanged`, preserving stale index/planning metadata.
- Genuine token-count RED `0fd08c2f28eea021a6f06f800069e835cca33f2d` / CI `33827012526`: PHPStan **No errors**; PHPUnit **304 tests / 1396 assertions / exactly 1 intended failure**, specifically `IncrementalIndexPlannerMetadataTest::test_token_count_change_forces_upsert`.
- Token-count GREEN `62f014c07f3459cd37700db4c15afcfdcba1e475` adds the minimal reuse equality check for `tokenCount`.
- Exact-SHA CI `33827066217`: `php-quality` ✅, `js-quality` ✅, `package` ✅, `wordpress-smoke` ✅; PHPStan **No errors**; PHPUnit **304/304 / 1397 assertions**; Composer audit clean.
- Artifact `9920425519`, digest `sha256:29b2e109ff16b63f6e612fbba461c60a318e87bcfea101a82d3195450738b471`.
- Same-session post-fix review `5108455605`: **0 Critical / 0 Important unresolved**, explicitly not independent because this session discovered and implemented the fix.

## Security Review
Tasks 1-6 remain pure PHP and WordPress-independent. They do not execute retrieved content, fetch URLs, call providers, touch credentials, persist data, write embeddings/vectors, or add queue/REST/hook execution paths. Task 5 prevents cross-privacy/cross-language/cross-embedding-space canonical sharing, and Task 6 treats visibility, language, token count, and indexed/citation metadata changes as index-work boundaries rather than reusing stale state.

## Performance Review
Dedup and incremental comparison use hash maps for expected O(n) grouping/set comparison. Task 6 hashes source metadata canonically per same-key comparison and deterministic result presentation adds bounded sorting of emitted result collections. Token-count comparison is constant-time. No whole-document quadratic duplicate or planner comparison is introduced.

## Code Review Findings
- Task 1 independent review `5104488263`: **0 Critical / 0 Important unresolved**.
- Task 2 independent review `5105069991`: **0 Critical / 0 Important unresolved**.
- Task 3 independent review `5105859046`: **0 Critical / 0 Important unresolved**.
- Task 4 final independent re-review `5107540703`: **0 Critical / 0 Important unresolved**.
- Task 5 final independent re-review `5108150441`: **0 Critical / 0 Important unresolved**.
- Task 6 same-session review `5108179436`: **0 Critical / 1 Important**, visibility planning boundary; fixed through strict RED/GREEN.
- Task 6 same-session post-visibility review `5108240331`: **0 Critical / 0 Important unresolved**, not independent.
- Task 6 fresh-session independent review `5108289931`: **0 Critical / 1 Important**, language/indexed-metadata reuse boundary; fixed through strict RED/GREEN.
- Task 6 same-session post-metadata-fix review `5108316220`: **0 Critical / 0 Important unresolved**, not independent.
- Task 6 fresh-session independent re-review `5108416626`: **0 Critical / 1 Important**, token-count reuse boundary; fixed through strict RED/GREEN.
- Task 6 same-session post-token-count-fix review `5108455605`: **0 Critical / 0 Important unresolved**, not independent.

## Active quality gate
Task 6 is not complete until a **new fresh-session independent re-review** inspects token-count GREEN `62f014c07f3459cd37700db4c15afcfdcba1e475` / CI `33827066217` and records **0 unresolved Critical / Important findings**.

The next reviewer must verify exact no-op/minimal-work behavior; additions/deletions/localized changes; chunking and embedding compatibility invalidation; visibility and language boundaries; token-count and citation/index metadata invalidation; associative metadata key-order stability; deterministic output ordering; duplicate-alias propagation/direction/order; caller immutability; bounded performance; and absence of M08/M09/provider/network/persistence/vector/WordPress execution scope leakage. Task 7 must not begin before this gate closes.

## Known Limitations
- Provider/model-exact tokenization remains intentionally deferred/injectable for M08.
- End-to-end source-to-index-plan pipeline composition remains Task 7.

## Exact next unfinished action
Perform a **fresh-session independent re-review of Task 6** anchored to token-count GREEN `62f014c07f3459cd37700db4c15afcfdcba1e475` / CI `33827066217`. If that review records 0 unresolved Critical/Important findings, mark Task 6 complete and only then begin **Task 7 — Source-to-index-plan integration and milestone closeout** with genuine test-first evidence.

## Completion Checklist
All remaining mandatory gates remain required before M07 completion: Task 6 independent re-review, Task 7 genuine TDD and independent review, whole-M07 review, exact-final-SHA full CI, durable docs, PR completion/merge, and fresh post-merge `main` CI.

## Next Milestone
M08 — Embeddings & Vector Stores.
