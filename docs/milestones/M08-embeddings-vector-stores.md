# M08 — Embeddings & Vector Store Abstraction/Implementations

Status: **FEATURE COMPLETE — PR #11 merge/post-merge verification pending**.

## Goal
Generate compatible embeddings and store/search them through replaceable vector-store adapters while truthfully exposing managed-vector capabilities that do not support caller-supplied raw vectors.

## Dependencies
M03 provider capabilities, M07 chunks.

## Design / Plan

- Architecture classification: architectural.
- Design/spec: **AUTO-APPROVED — SCHEDULED MODE**.
- Design: `docs/superpowers/specs/2026-09-04-m08-embeddings-vector-stores-design.md` (`b63add146280f5770939f52c9806ef928c0d39ca`).
- Implementation plan: **AUTO-APPROVED — SCHEDULED MODE**.
- Plan: `docs/superpowers/plans/2026-09-04-m08-embeddings-vector-stores.md` (`6b3ae63ed84c1e0e81884d32ae6f3c1577c4bf0a`).
- Selected architecture: capability-aware common vector-store contracts plus truthful optional operation interfaces; provider-specific HTTP/auth remains in `Providers`; vendor-neutral orchestration belongs in `Embeddings`; M09 queue/retries and M10 hybrid retrieval remain out of scope.
- Full closeout/security/performance evidence: `docs/progress/M08-CLOSEOUT.md`.

## Completed task ledger

### Task 1 — Embedding contracts and compatibility fingerprints — COMPLETE
Delivered optional provider embedding capability, immutable request/result/vector/usage contracts, embedding/index profiles, deterministic versioned compatibility fingerprints, ordered-list/finite-vector validation, and registry consistency.

- Genuine RED `17a77855d4634cb8f72d3327f442ef2cd0e76b3f` / CI `33861460302`.
- Final GREEN `9e3b9c85351b383d246860c8f786a9e74ff1dda0` / CI `33863156655`: all permanent jobs green; PHPUnit 324/324, 1,467 assertions.
- Final review: Critical 0 / Important 0 unresolved.

### Task 2 — Embedding service batching/validation and direct adapters — COMPLETE
Delivered deterministic bounded batching, strict response reconstruction/validation, usage aggregation, and fixed-endpoint OpenAI/OpenRouter embedding capabilities.

- Genuine RED `89767ff0b09915fb5fe1c7709fee565149d107c7` / CI `33866391356`.
- Review RED `74cd3a8cd439922befbc27f1e6ceb70abf63d6dc` / CI `33869958370` caught mixed first-batch dimensions.
- Final GREEN `cb37685ad8955f578e38b5e851193d860cec1871` / CI `33870183605`: PHPUnit 336/336, 1,514 assertions; all permanent jobs green.
- Final review: Critical 0 / Important 0 unresolved.

### Task 3 — Vector-store contracts, portable filters, registry, reusable contract suite — COMPLETE
Delivered truthful capability contracts, bounded collection/record/search/result contracts, portable Eq/In/And filter AST, normalized errors, duplicate-safe registry behavior, and reusable test contract coverage.

- Genuine RED `fb14f05ffba4a322570d02a6eb7079dadb154c9d` / CI `33874688946`.
- Review regressions covered non-scalar record/match metadata and malformed returned stable IDs.
- Final GREEN `d5fa24f1cbe29a1e163c791546fc0293774d0255` / CI `33880952765`: PHPUnit 347/347, 1,529 assertions; all permanent jobs green.
- Final review: Critical 0 / Important 0 unresolved.

### Task 4 — Local WordPress vector store — COMPLETE
Delivered versioned vector tables, collection/profile isolation, stable-ID replacement, idempotent deletes, prepared portable filters, database narrowing before PHP cosine scoring, deterministic ordering, and explicit `LOCAL_SCALE_LIMIT`.

- Initial RED `a0f49b3645a26889bca6c58b0d7f2349c89427c0` / CI `33885763320`.
- Review RED `31a9f25d6492a7df3189184487cf75eb51b70a24` / CI `33897449155` established the dedicated scale-limit category.
- Final GREEN `69dce20f2c7c58239d999cbb414e07c5dac100fb` / CI `33898085114`: PHPUnit 358/358, 1,576 assertions; all permanent jobs green.
- Review `5115843007`: Critical 0 / Important 0 unresolved.

### Task 5 — Qdrant adapter — COMPLETE
Delivered validated administrator-owned HTTPS endpoints, server-side credentials, redirects disabled/no retries, compatibility-isolated collections, remote profile verification, deterministic stable-ID→UUID mapping, portable filters, bounded result validation, sanitized errors, truthful health/capabilities, and opt-in live health gating.

- Initial RED `8cb3d00aa13edf33ac4c41ae0aceee5f90391c20` / CI `33900244344`.
- Final GREEN `974a9fb64a1bc66864b0cad82fe42a22225608c5` / CI `33909554302`: PHPUnit 368/368, 1,642 assertions; all permanent jobs green.
- Artifact `9950829684`, digest `sha256:e701359c6b955a54de73a0e9402db4d528f2e04049b2e6b584f4c0fbc2484958`.
- Review `5116935253`: Critical 0 / Important 0 unresolved.

### Task 6 — Pinecone adapter — COMPLETE
Delivered fixed validated HTTPS data/control-plane boundaries, server-side API key, pinned API version, redirects disabled/no retries, remote compatibility verification, profile-isolated namespaces, stable-ID operations, portable filters, bounded result mapping, sanitized errors, truthful health/capabilities, and opt-in live health gating.

- Genuine RED `e9334c8e0e15283988c6d1985426004c7e0c2956` / CI `33910441036`.
- Review RED `d73f5bfc6309da8c435b4a9f2d31ae4e00e8133d` / CI `33913238466` caught unsupported boolean membership mapping.
- Final GREEN `29f1d94394827615b82fadb7129d755a5bce50a3` / CI `33913502927`: PHPUnit 380/380, 1,714 assertions; all permanent jobs green.
- Artifact `9953323840`, digest `sha256:24ef3bd3794db00222d5999736304161a089465c32a55b68ced237ec45ce0c86`.
- Review `5117304228`: Critical 0 / Important 0 unresolved.

### Task 7 — Chroma adapter — COMPLETE
Delivered a Chroma v2 raw-vector adapter with validated HTTPS origin/tenant/database scope, optional server-side token, redirects disabled/no retries, compatibility-isolated collections, remote profile verification, explicit embeddings, stable-ID delete, portable filters, bounded result mapping, sanitized errors, truthful health/capabilities, and opt-in live health gating.

- Genuine RED `c331890c617aa5d3bc3dcf1036a3fc85b6fb8074` / CI `33918105636`.
- Review RED `ee19261237dd9fbf76363744d491172066ce247a` / CI `33922544502` caught malformed remote collection UUID acceptance.
- Final GREEN `121a930901ab1cf0f1eb9365a83a41d104b96b76` / CI `33922772281`: PHPUnit 392/392, 1,795 assertions; all permanent jobs green.
- Artifact `9955679919`, digest `sha256:c17aa5857594a453fec3ce433691a8847b641b72def4f2c5e5405d437a410db7`.
- Review `5118131476`: Critical 0 / Important 0 unresolved.

### Task 8 — OpenAI managed vector-store capability adapter — COMPLETE
Delivered a truthful capability-specific adapter based on the current public OpenAI API: managed file attachment/deletion and text-query vector-store search are exposed; raw-vector upsert/delete/search remain false. The adapter keeps fixed official HTTPS origin, server-side auth, redirects disabled/no retries, bounded validation, sanitized errors, managed registry gating, and completed-only health readiness.

- Genuine RED `4125950e5598082ba1c70039f98f20e7304dfc6c` / CI `33926896274`.
- Review RED `13810bf7f8ee516ecee4138ba585c6cf2eccb21f` / CI `33927625909` caught managed-registry truth and readiness-health defects.
- Final GREEN `dfbae21e1a4226a7ce285413a74b817e1089ce78` / CI `33927746937`: PHPUnit 402/402, 1,854 assertions; all permanent jobs green.
- Artifact `9957422625`, digest `sha256:dfef7a3064fdb8cc2bb90958481169d087f35767c46a28470bf8fcdb59808167`.
- Review `5118469428`: Critical 0 / Important 0 unresolved.

### Task 9 — M07 plan-to-embedding/vector integration and M08 closeout — COMPLETE ON FEATURE BRANCH
Delivered `IndexEmbeddingExecutor`, consuming accepted M07 `IndexPlan` objects without adding M09/M10 behavior.

Behavior:
- embeds only planned `upsert` chunks;
- does not re-embed `metadataRefresh` or `unchanged` chunks;
- executes planned collection-scoped stable-ID deletes;
- validates collection/profile/provider identity and non-null chunk compatibility before paid embedding;
- preflights stable IDs and curated scalar lineage metadata rather than copying arbitrary source metadata;
- bounds synchronous execution to 1,000 upserts and 1,000 deletes.

TDD / verification:
- `bcafccc12f72eb0c552e016ab797278948dd104d` stopped at PHPCS and is not counted as RED.
- Genuine initial RED `c356c65b9bad72346e139e1a1b7c76cfb6403b80` / CI `33928846507`: PHPStan 0 errors; PHPUnit 405 tests / 1,854 assertions with exactly three missing-class errors.
- Initial GREEN `231e287f9749da77d8e6a8e275d8bc42c3e1a263` / CI `33929134396`: PHPUnit 405/405, 1,864 assertions; PHPStan 0 errors; Composer audit clean.
- Fresh review found two Important issues: provider/profile mismatch was not rejected before paid embedding; delete execution was unbounded.
- Genuine review RED `de93fcc0045ece00cf5decea052953b2b33a2a11` / CI `33929269542`: PHPStan 0 errors; PHPUnit 407 tests / 1,866 assertions with exactly two intended failures.
- Fixes `f78cc7f58442d6ffc72fb4985b44acb8cf054b98` and `3baef98b31d0d85cbde0c6cd130274645d489505`.
- Final implementation GREEN `3baef98b31d0d85cbde0c6cd130274645d489505` / CI `33929387362`: all four permanent jobs green; PHPStan 0 errors; PHPUnit 407/407, 1,866 assertions; Composer audit clean; full WordPress smoke green.
- Artifact `9957988866`, digest `sha256:6eb92f7b5d3502975263c9a62fb3be01d4fb8390a16a87147ee36302fc02fd9b`.
- Whole-M08 review `5118637420`: Critical 0 / Important 0 unresolved; zero unresolved inline review threads at review time.

## Security Review

M08 keeps credentials server-side behind provider/transport boundaries; validates/fixes HTTPS endpoint ownership at the adapters; disables redirects; exposes no raw SQL/vendor-filter escape hatch; scopes raw-vector operations by explicit collection/profile boundaries; validates compatibility, metadata, stable IDs and remote IDs; sanitizes remote failures; performs no automatic retries; and keeps live external-store hooks explicit opt-in/credential-gated. Task 9 adds no new external endpoint or secret boundary and prevents arbitrary M07 `sourceMetadata` from crossing into vector metadata. OpenAI managed-vector support remains capability-truthful and does not pretend to accept caller-supplied raw vectors.

Unresolved Critical security findings: **0**. Unresolved Important security findings: **0**.

## Performance Review / Benchmark

- Embedding batching is bounded by `EmbeddingBatchConfig` (1..10,000).
- Task 9 synchronous execution is bounded to 1,000 upserts and 1,000 deletes.
- Common search/filter/metadata/result cardinalities are bounded.
- Local search database-narrows candidates before PHP similarity and fails with `LOCAL_SCALE_LIMIT` on overflow.
- External raw-vector adapters use bounded top-K and fail-closed response-cardinality checks.

Synthetic exact-artifact Task 9 orchestration benchmark, no network/database/vector-engine I/O: 1,000 upserts, 8 dimensions, batch size 100, fake in-process provider/store; six post-warmup runs `7.131, 7.183, 7.394, 7.405, 7.463, 7.675 ms`, median `7.399 ms`, exactly 10 provider batches and 1,000 writes. This measures orchestration overhead only and is not a dedicated-vector-engine/network throughput claim.

## Known Limitations / Intentional Deferrals

- M09 owns queueing, leases, retry/backoff, synchronization execution and recovery.
- M10 owns lexical/semantic/hybrid retrieval orchestration and fusion.
- M08 does not invent a metadata-only vector mutation API; `metadataRefresh` is intentionally not converted into unnecessary re-embedding.
- Comprehensive public-chat abuse/cost, retrieval/tool-injection and privacy hardening remains assigned to later milestones.

## Exact Next Unfinished Action

Require all permanent CI jobs green on the exact documentation-complete PR head, confirm zero unresolved Critical/Important findings and review threads, mark PR #11 ready, merge only with the expected exact head SHA, verify fresh push-triggered `main` CI, update post-merge durable status, and only then begin M09 — Job Queue & Synchronization.

## Next Milestone
M09 — Job Queue & Synchronization, only after M08 is merged and post-merge `main` verification is green.
