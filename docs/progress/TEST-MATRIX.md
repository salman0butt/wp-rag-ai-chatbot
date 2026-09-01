# Test Matrix

Status: INITIALIZED — expand with exact commands/evidence per milestone.

| Layer | Purpose | First Required | Evidence location |
|---|---|---|---|
| PHP unit | isolated domain behavior | M01 | milestone TDD evidence |
| WordPress integration | hooks/REST/plugin lifecycle | M01 | milestone integration evidence |
| DB migrations | fresh install + upgrades + idempotency | M02 | M02/M24 |
| Repository/SQL | prepared queries, pagination, ownership | M02 | M02+ |
| Provider contract | normalized generation/errors/capabilities | M03 | M03 |
| Live provider smoke | opt-in credential-gated path | M03 | M03/M24 |
| Knowledge source contract | normalization and access metadata | M04 | M04-M06 |
| Extractor security | type/size/parser failure controls | M05 | M05/M22 |
| Chunking/indexing | boundaries/hash/dedup/incremental behavior | M07 | M07 |
| Vector store contract | upsert/delete/search/filter/health | M08 | M08 |
| Job queue | leases/retry/recovery/idempotency | M09 | M09 |
| Retrieval quality | lexical/semantic/hybrid/filter/rerank | M10 | M10/M21 |
| RAG grounding | strict no-answer/citations/memory | M11 | M11/M21 |
| Streaming | event ordering/errors/disconnects | M11 | M11 |
| Admin JS/TS | components/state/API behavior | M12+ | relevant milestone |
| Typecheck/lint/build | frontend production integrity | M12+ | relevant milestone |
| Playwright E2E | admin/widget workflows | M12+ | relevant milestone |
| Accessibility | keyboard/screen reader/axe/manual | M14-M15 | M14/M15/M24 |
| WooCommerce | product/action/order authorization | M06/M18 | M06/M18/M24 |
| Actions security | schema/authz/risk/audit/tool injection | M19 | M19/M22 |
| Privacy | retention/export/erasure | M22 | M22/M24 |
| Abuse/cost | rate limiting/denial-of-wallet | M22 | M22 |
| Upgrade/uninstall | migration compatibility/data policy | M24 | M24 |
| Release ZIP | install/activate/deactivate/package validation | M24 | M24 |
