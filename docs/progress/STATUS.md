# Global Status

- Completed milestones on `main`: **M00-M06**.
- Latest integrated milestone: **M06 — WooCommerce Knowledge Ingestion**.
- Current milestone: **M07 — Content Normalization, Chunking, Deduplication & Incremental Indexing — IN PROGRESS (Tasks 1-2 complete; Task 3 next)**.
- Current verified `main`: `747733a92c23d411ccba2592d5cb8c7858b95a03`.
- Latest verified `main` CI: `33770388757` — all permanent jobs passed.
- M06 integration merge: `356f419ea6df23e68d89a13ee322ca50585ed74b` via PR #8.

## M06 final state — COMPLETE
- Design/spec: `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Plan: `docs/superpowers/plans/2026-09-03-m06-woocommerce-knowledge-ingestion.md` — `AUTO-APPROVED — SCHEDULED MODE`.
- Tasks 1–6: complete with strict TDD and independent reviews.
- Whole-M06 review `5102236196`: **0 Critical / 0 Important unresolved**.
- Task 7 CI-installer independent review `5103413133`: **0 Critical / 0 Important unresolved**.
- Final feature head `9f8864e8a3c4c3bc66eb2cf708ba82e86ab493c9`, exact-head CI `33769471726`: all four permanent jobs green.
- PR #8 merged using expected-head protection at integrated main SHA `356f419ea6df23e68d89a13ee322ca50585ed74b`.
- Fresh post-merge CI `33769814956`: php-quality, js-quality, package, wordpress-smoke all green.
- Final M06 durable closeout `747733a92c23d411ccba2592d5cb8c7858b95a03`; CI `33770388757` green.
- Unresolved Critical/Important findings: **none**.

## M07 active state
- Feature branch: `feat/m07-chunking-dedup-indexing`.
- Draft PR: **#9 — `feat: build M07 chunking dedup incremental indexing`**.
- Milestone ledger: `docs/milestones/M07-chunking-dedup-indexing.md`.
- Design/spec: `docs/superpowers/specs/2026-09-03-m07-chunking-dedup-indexing-design.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- Implementation plan: `docs/superpowers/plans/2026-09-03-m07-chunking-dedup-indexing.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- Selected architecture: canonical `DocumentRecord` -> meaning-preserving deterministic normalization -> structure-aware bounded chunking -> stable chunk hashes/lineage -> compatibility-safe dedup -> pure incremental index plan.
- M08 owns embedding generation/vector stores and provider-exact compatibility; M09 owns queue/synchronization execution.
- Task 1 — Deterministic content normalization: **COMPLETE** after strict RED/GREEN, exact-head CI, and independent fresh-session review `5104488263` with 0 Critical / 0 Important unresolved.
- Task 2 — Token budget/configuration contracts: **COMPLETE** after strict RED/GREEN, exact-code-head CI, documentation-head CI, and independent fresh-session review `5105069991` with 0 Critical / 0 Important unresolved.
- Tasks 3–7 remain. Task 3 is now the first legitimate unfinished task.

### M07 Task 1 evidence
- RED test commit: `3e34a6d1125592a256463c3e75ee9406fa3e5e3a`.
- RED CI `33775003445`: PHPStan clean; PHPUnit **263 tests / 1252 assertions / 7 expected failures**, each exactly `ContentNormalizer class does not exist yet.`
- Minimal GREEN implementation: `de41fd281d95f2a367df163ea66d8713357b8a14`.
- Exact-code-head GREEN CI `33775193798`: **php-quality, js-quality, package, wordpress-smoke all passed**.
- Artifact `9901292022`, digest `sha256:1457cf8e2c625978959806ceb0e75c2409460a2ac3db4ad03e738de78873c552`.
- Same-session review `5104040492`: 0 Critical / 0 Important unresolved, not independent.
- Independent fresh-session review `5104488263`: **0 Critical / 0 Important unresolved**.
- Fresh review-time exact-head CI `33775697649` on `8493eec0843b3b85058ffa73f3300fee770eb1c5`: all permanent jobs green; artifact `9901483331`, digest `sha256:b8ea69a97149a7554809f4d83e1df8bc4ffb8d5b8fee88546d7b042067a5aabe`.

### M07 Task 2 TDD evidence
- RED test-only commit: `220bffa181cb4a32490f6fa35b3be6904ae790d6` — `test: define M07 token budget contracts`.
- RED PR CI `33780572899`: PHPStan clean; PHPUnit **272 tests / 1261 assertions / 9 expected failures**. Five failures were `ChunkingConfig class does not exist yet.` and four were `TokenCounter/LexicalTokenCounter contracts do not exist yet.`
- Minimal GREEN implementation: `f802bed14cc1887c219b4ac1058236ded114224c` — `feat: add M07 token budget contracts`.
- Exact-code-head GREEN PR CI `33780799386`: **php-quality, js-quality, package, wordpress-smoke all passed**.
- GREEN PHP verification: PHPStan clean; PHPUnit **272 / 1276**, all passed; Composer audit clean.
- Real WordPress smoke: activation, database, providers, knowledge, file ingestion, and WooCommerce knowledge all passed.
- Artifact `9903483875`, 714589 bytes, digest `sha256:f06d0f169bf7fa74718f976a3d63f05b3285347e2a45c24245c74c5a6138d388`.
- Same-session review `5104539329`: **0 Critical / 0 Important unresolved**, explicitly not independent.
- Documentation head `5eed132f33ac227cc0102b4b915b9dbe7de94768`, exact-head CI `33781239556`: **all permanent jobs passed**.
- Independent fresh-session review `5105069991`: **0 Critical / 0 Important unresolved**. Review covered Unicode lexical-unit semantics, invalid-UTF-8 fail-closed behavior, max/overlap bounds including exact 25%, readonly immutability, deterministic canonical fingerprinting, embedding compatibility identity, and absence of M08/M09/provider/network/persistence leakage.

### M07 Task 2 behavior
`TokenCounter`, `LexicalTokenCounter`, and immutable `ChunkingConfig` are pure PHP/WordPress-independent contracts. The lexical counter counts Unicode letter/number runs plus individual non-whitespace punctuation/symbols and throws `DomainException` on invalid UTF-8. Config validation enforces max-token bounds 32..4096, overlap from 0 through exactly 25% of max, a non-blank chunking version, and a null-or-non-blank embedding compatibility key. The SHA-256 fingerprint uses the existing canonical `DocumentHasher` and incorporates all compatibility inputs.

No Task 2 code performs provider calls, provider-exact tokenization, persistence, embeddings, vector writes, URL fetching, WordPress execution, or queue behavior.

## Exact next unfinished action
Begin M07 Task 3 — immutable chunk records and structure-aware splitting — with a genuine test-only RED commit. Cover headings, paragraphs, sentence fallback, one oversized sentence/code-point-safe lexical fallback, zero/tiny content, stable order/keys/hashes, copied metadata/language/visibility/canonical URL/source lineage, heading paths, structural parent keys, token counts, and deterministic repeated calls. Verify RED on the exact test-only SHA before adding any Task 3 production implementation.

## Previous milestone closeout
- M05 merge `dd29d3bc1dc62dbfcccf1f87272a75c4e145afa6` via PR #6.
- M05 post-merge CI `33721014064` — all permanent jobs green.
- M05 durable finalization on `main`: `d8a087f7a90badcc6eca8fe486f5c06d5c8cc66e`.
