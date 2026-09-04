# M08 — Embeddings & Vector Store Abstraction/Implementations

Status: **COMPLETE ON `main`**.

## Goal
Generate compatible embeddings and store/search them through replaceable vector-store adapters while truthfully exposing managed-vector capabilities that do not support caller-supplied raw vectors.

## Dependencies
M03 provider capabilities, M07 chunks/index plans.

## Design / Plan

- Architecture classification: architectural.
- Design/spec: **AUTO-APPROVED — SCHEDULED MODE**.
- Design: `docs/superpowers/specs/2026-09-04-m08-embeddings-vector-stores-design.md` (`b63add146280f5770939f52c9806ef928c0d39ca`).
- Implementation plan: **AUTO-APPROVED — SCHEDULED MODE**.
- Plan: `docs/superpowers/plans/2026-09-04-m08-embeddings-vector-stores.md` (`6b3ae63ed84c1e0e81884d32ae6f3c1577c4bf0a`).
- Selected architecture: capability-aware common vector-store contracts plus truthful optional operation interfaces; provider-specific HTTP/auth remains in `Providers`; vendor-neutral orchestration belongs in `Embeddings`; M09 queue/retries and M10 hybrid retrieval remain out of scope.
- Full closeout/security/performance evidence: `docs/progress/M08-CLOSEOUT.md`.

## Completed task ledger

| Task | Scope | Final evidence |
| --- | --- | --- |
| 1 | Embedding contracts and compatibility fingerprints | `9e3b9c85351b383d246860c8f786a9e74ff1dda0` / CI `33863156655` |
| 2 | Bounded embedding service and direct OpenAI/OpenRouter embedding adapters | `cb37685ad8955f578e38b5e851193d860cec1871` / CI `33870183605` |
| 3 | Vector-store contracts, portable filters, registry, reusable contract suite | `d5fa24f1cbe29a1e163c791546fc0293774d0255` / CI `33880952765` |
| 4 | Local WordPress vector store | `69dce20f2c7c58239d999cbb414e07c5dac100fb` / CI `33898085114` |
| 5 | Qdrant adapter | `974a9fb64a1bc66864b0cad82fe42a22225608c5` / CI `33909554302` |
| 6 | Pinecone adapter | `29f1d94394827615b82fadb7129d755a5bce50a3` / CI `33913502927` |
| 7 | Chroma adapter | `121a930901ab1cf0f1eb9365a83a41d104b96b76` / CI `33922772281` |
| 8 | OpenAI managed vector-store capability adapter | `dfbae21e1a4226a7ce285413a74b817e1089ce78` / CI `33927746937` |
| 9 | M07 `IndexPlan` → embedding/vector integration and whole-M08 closeout | `3baef98b31d0d85cbde0c6cd130274645d489505` / CI `33929387362` |

Every task completed its required behavioral RED/GREEN loop and independent review. The detailed per-task RED/review history is preserved in merged PR #11 and repository history; whole-M08 review `5118637420` finished with **Critical 0 / Important 0 unresolved** and zero unresolved inline review threads.

## Final delivered behavior

M08 provides:

- immutable embedding requests/results/vectors/usage contracts;
- deterministic embedding/index compatibility profiles and fingerprints;
- bounded batching and strict provider response reconstruction/validation;
- direct fixed-endpoint OpenAI/OpenRouter embedding capabilities;
- truthful capability-aware raw/managed vector-store contracts;
- bounded portable scalar metadata and Eq/In/And filters with no vendor-expression escape hatch;
- a versioned local WordPress vector store with database narrowing before PHP similarity and explicit scale-limit failure;
- Qdrant, Pinecone, and Chroma raw-vector adapters with fixed/validated HTTPS boundaries, server-side credentials, zero redirects, no automatic retries, compatibility isolation, bounded result validation, and opt-in live-health hooks;
- truthful OpenAI managed-vector operations without pretending caller-supplied raw-vector support exists;
- `IndexEmbeddingExecutor`, which consumes accepted M07 `IndexPlan` objects, embeds only planned upserts, executes planned deletes, validates provider/profile/stable-ID/metadata constraints before paid embedding, and bounds one synchronous execution to 1,000 upserts and 1,000 deletes.

## Security Review

Credentials remain server-side behind provider/transport boundaries; external adapters use fixed/validated HTTPS origins and redirects disabled; raw SQL/vendor-filter escape hatches are not exposed; collection/profile/provider compatibility is validated; metadata/stable IDs/remote IDs are treated as untrusted; remote errors are sanitized; automatic retries remain deferred to M09; arbitrary M07 source metadata does not cross the vector boundary.

Unresolved Critical security findings: **0**.  
Unresolved Important security findings: **0**.

## Performance Review

- Embedding batches are bounded by `EmbeddingBatchConfig`.
- Synchronous M07→M08 execution is bounded to 1,000 upserts and 1,000 deletes.
- Vector search/filter/metadata/result cardinalities are bounded.
- Local search database-narrows candidates before PHP cosine similarity and fails explicitly with `LOCAL_SCALE_LIMIT` on overflow.
- External adapters use bounded top-K and fail-closed response-cardinality checks.
- Synthetic Task 9 orchestration benchmark: 1,000 upserts, 8 dimensions, batch size 100, fake in-process provider/store; median `7.399 ms`. This measures orchestration overhead only, not real network/vector-engine throughput.

## Merge / Post-Merge Verification

- PR #11 final feature head: `585c9c534cd1f4520733e986d5eacd7eebe87ced`.
- Merge commit on `main`: `bf4b889c80e1bd9fc39d7ca24a7fb6f6f86201aa`.
- Merged: `2026-09-04T23:35:20Z`.
- Fresh push-triggered post-merge `main` CI: `33930071265` — **SUCCESS** across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.
- Post-merge package artifact: `9958213968`.
- Post-merge artifact digest: `sha256:4386b169b1a47bcea184f2cb9a919f2c357f4ee4b1c6bde87e9691773afeed3e`.

M08 therefore satisfies its merge gate and is complete on `main`.

## Known Limitations / Intentional Deferrals

- M09 owns queueing, leases, retry/backoff, synchronization execution and recovery.
- M10 owns lexical/semantic/hybrid retrieval orchestration and fusion.
- M08 does not invent a metadata-only vector mutation API; `metadataRefresh` is intentionally not converted into unnecessary re-embedding.
- Comprehensive public-chat abuse/cost, retrieval/tool-injection and privacy hardening remains assigned to later milestones.

## Exact Next Unfinished Action

Begin M09 — Database Job Queue, Synchronization, Retries & Recovery. Because M09 introduces a persisted execution/recovery subsystem, run the architectural Brainstorm → design/spec → plan workflow under **AUTO-APPROVED — SCHEDULED MODE**, then implement the first planned unit with strict behavioral RED/GREEN evidence.

## Next Milestone
M09 — Database Job Queue, Synchronization, Retries & Recovery.
