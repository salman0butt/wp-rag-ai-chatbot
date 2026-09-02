# M04 Closeout — WordPress Knowledge Source Framework

Status: **COMPLETE — integrated into `main`; post-merge permanent CI verified green.**

This closeout record supersedes the pre-merge pending wording at the end of the detailed M04 milestone ledger. The task-by-task design, implementation, RED/GREEN history, review notes, and pre-integration evidence remain in `docs/milestones/M04-wordpress-knowledge-sources.md`.

## Integration
- Final reconciled feature head: `f09f293c913203bdc498e76a28413e3ab2614f5c`.
- Final exact-head CI: `33687964210` — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed; WordPress smoke included activation, database, providers, and the new knowledge lifecycle test.
- Exact-head artifact: `wp-rag-ai-chatbot`, ID `9868887017`, 75,694 bytes, digest `sha256:0bb2f525d4ae4c37a0d69d7f9d02716b94868e5db9f07a0d8362918b6c47904e`.
- PR #4 merged with expected-head protection as `666bb02dc6780f2fb3c818bbbf4d3fe1a0778555`.
- Post-merge `main` CI: `33688297306` — all four permanent jobs passed, including real WordPress `test:wp:knowledge`.
- Post-merge artifact: `wp-rag-ai-chatbot`, ID `9869009969`, 75,695 bytes, digest `sha256:5d5e69b131b33a87614dfb86ef228e5fd3c168065b077e1bfbaf2af16ec82590`.

## Delivered
M04 established deterministic `KnowledgeSource`/registry/hash contracts, manual text and FAQ normalization, the WordPress content gateway/native adapter, post/page/public-CPT normalization with explicit access policy and sanitized text, knowledge bootstrap/extension composition, and permanent real-WordPress knowledge lifecycle smoke coverage.

## TDD / Review Closeout
- Tasks 1-6 retain genuine behavioral RED/GREEN evidence in the milestone ledger.
- Task 3 Important partial-yield integrity defect was fixed with a dedicated regression RED/GREEN cycle.
- Task 5 Important adapter-boundary sanitization defect was fixed with a dedicated regression RED/GREEN cycle.
- Task 7 was integration-only after the production behavior existed. Its approved plan explicitly allowed direct integration GREEN if no production defect was exposed; no defect was exposed and no RED was fabricated.
- Final milestone review: Critical none; Important unresolved none.
- No submitted PR reviews or unresolved inline review threads blocked integration.

## Security / Performance Closeout
M04 introduced no public REST prompt surface, remote crawler, file parser, model/vector call, credential handling, or arbitrary post-meta ingestion. Public WordPress content is the default; private content requires explicit opt-in; drafts/pending/trash/password-protected content are excluded; native text is sanitized before canonical normalization. Paging is bounded to 100 records with stable ordering and `no_found_rows`; per-post taxonomy lookup remains a documented Minor optimization consideration.

## Next Milestone
M05 — File/Document Ingestion. A fresh autonomous run must recover latest GitHub state, mandatory instructions, active branches/PRs/CI, and the M05 milestone/spec/plan state before creating new work.
