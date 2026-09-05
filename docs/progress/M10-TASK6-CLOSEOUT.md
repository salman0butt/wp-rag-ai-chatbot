# M10 Task 6 — Hybrid Orchestration Closeout

Status: **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED**

Milestone: M10 — Semantic + Keyword + Hybrid Retrieval, Filters & Reranking.

This closeout supplements `docs/progress/M10-PROGRESS.md` with the completed Task 6 orchestration evidence. Tasks 1–6 are now complete; Tasks 7–8 remain unfinished, so PR #15 stays draft and M10 is not merge-ready.

## Delivered behavior

- explicit `SemanticRetrievalChannel` and `LexicalRetrievalChannel` orchestration boundaries over the completed semantic and lexical retrievers;
- deterministic both-channel weighted RRF with duplicate collapse inherited from the reviewed fusion engine;
- controlled single-channel degradation only when explicitly enabled by the caller;
- both-channel unavailability always fails with a normalized `RetrievalException` rather than exposing provider/persistence error text;
- post-fusion `CandidateAccessPolicy` enforcement before the final configured context limit;
- deterministic confidence attached only after fused candidates pass the trusted access boundary;
- bounded final results using `context_candidate_limit`;
- safe retrieval traces containing query hash/byte length, bounded per-channel counts, and normalized channel-specific failure reason codes only;
- `RetrievalTrace` rejects mismatched channel/failure reason pairs so diagnostics cannot misattribute channel health.

## Strict TDD evidence

### Hybrid orchestration cycle

- Test-first commit `dbef9454c636559b7e50bb807a2a332a666d39e4` was stopped by PHPCS and is **not** counted as behavioral RED.
- Test-only cleanup `12bf8445d7137a77a23652185943e04b4960d99e` still exposed coding-standard issues and is also **not** counted as behavioral RED.
- Valid RED: `bc277d497878e8d3fe46dd0ed198448ed1d89ebf`, CI `33994339677` — PHPCS/PHPStan passed; PHPUnit reached 541 tests / 2,191 assertions and produced exactly four expected missing-interface errors before the orchestration contracts existed.
- Implementation: `d23f3c53eba49f05ac329eb19c90864901652c54` added the channel contracts, safe retrieval exception, hybrid orchestration, confidence surfacing, failure traces, and concrete retriever interface adoption.
- Initial GREEN: `d23f3c53eba49f05ac329eb19c90864901652c54`, CI `33994476577` — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed; PHPStan reported no errors; PHPUnit passed 541 tests / 2,200 assertions; Composer audit reported no security advisories.

### Independent-review diagnostics fix

- Scoped correctness/security review found **0 Critical / 1 Important**: `RetrievalTrace` accepted a failure reason belonging to the wrong channel (for example `semantic => lexical_unavailable`), allowing misleading health diagnostics at the public trace boundary.
- Regression RED: `829b7661ee1697a29f0a41fc6b850724d8850fcc`, CI `33994640122` — PHPStan passed; PHPUnit ran 542 tests / 2,201 assertions and failed exactly one new regression because the mismatched channel/failure pair was accepted.
- Fix attempt `78f05c3f7bcdfd22367f5ae2a1c2c40bcdadcdcf` implemented exact channel/reason pairing but exposed only redundant PHPDoc-type checks in PHPStan; it is not claimed as GREEN.
- Static-analysis correction: `0bdffc861aaedb9cebea9313f59a20cce5e79799` retained exact channel/reason pairing without redundant type checks.
- GREEN implementation verification: `0bdffc861aaedb9cebea9313f59a20cce5e79799`, CI `33994800924` — PHPStan reported no errors; PHPUnit passed 542 tests / 2,201 assertions; Composer audit reported no security advisories; JavaScript and package verification passed and the WordPress smoke suite completed its activation/database/provider/knowledge/file-ingestion/WooCommerce checks successfully.

## Independent re-review

The final Task 6 scope was rechecked for:

- explicit and symmetric channel-failure handling;
- no raw exception/provider diagnostic leakage;
- opt-in-only single-channel degradation;
- deterministic fusion and duplicate collapse;
- post-fusion access enforcement before final limiting;
- deterministic confidence without fabricated cross-channel agreement;
- final candidate ceilings;
- safe trace query diagnostics and correctly bound channel failure codes.

Result: **0 unresolved Critical / 0 unresolved Important**. PR #15 has zero inline review threads.

## Merge state

Task 6 is complete, but M10 remains intentionally unmerged. Tasks 7 and 8 must be genuinely completed, reviewed, documented, and exact-SHA GREEN before PR #15 can be made merge-ready.

## Exact next unfinished action

Begin **Task 7 — Optional reranker contract and implementation** with a test-only RED commit. Prove reranking is optional, runs only after trusted post-fusion filtering, receives only bounded safe candidate fields, preserves candidate lineage/citations, rejects malformed or unauthorized reranker output fail-closed, and falls back deterministically to fused ordering when reranking is disabled or unavailable according to the approved design. Do not add production reranker code until exact-head CI proves the behavioral RED.
