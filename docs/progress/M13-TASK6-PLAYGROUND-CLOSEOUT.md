# M13 Task 6 — Playground REST Execution Closeout

Status: **COMPLETE**

## Delivered behavior

Task 6 exposes protected `POST /wp-rag-ai-chatbot/v1/admin/debug/playground` behind `AdminCapability::can_manage` and routes one bounded administrator request through the existing production M10/M11 retrieval/chat pipeline exactly once.

The public request surface is closed to exactly:

- `bot_id` — persisted bot selector, bounded to 256 bytes and valid UTF-8;
- `source_id` — positive persisted knowledge-source identifier;
- `collection_id` — persisted collection selector, bounded to 256 bytes and valid UTF-8;
- `question` — trimmed, non-empty, valid UTF-8, bounded to 16,384 bytes.

Any extra request key is rejected as `invalid_request`. Credentials, provider/model overrides, embedding overrides, vector-store options, and retrieval-limit overrides are therefore not accepted at the route boundary.

The runtime resolves persisted production authority for bot/provider/model, source/collection, embedding profile, vector-store identity, retrieval configuration, access context, and the shared provider/vector-store registries. It composes the existing semantic + lexical retrieval, fusion/confidence/access controls, grounding, prompt construction, citations, memory assembly, and M11 chat graph. `PlaygroundRetrievalCapture` observes the exact M10 result consumed by that graph for Task 5 debug projection; no diagnostic-only second retrieval is executed.

The success DTO is explicitly allow-listed and bounded: answer, citations, provider-neutral usage, selected persisted model ID, latency, and the bounded/redacted Task 5 debug trace. Internal/upstream exceptions are mapped to stable repository-owned `retrieval_unavailable` or `playground_failed` codes without exposing exception/provider bodies.

## Key strict-TDD evidence

Task 6 was implemented as a sequence of strict RED → GREEN prerequisites. The authoritative chronology is preserved in `docs/progress/M13-TASK6-*` and git/CI history. Important final checkpoints include:

- request-handler RED `3b4bdb1df6de76474e60693abbb97dd28a92672b` / CI `34659505729` and GREEN `1b85b0185689472bd38035f1757da29b7ef8058d` / CI `34659592284`;
- citation-lineage RED `dee49f4b6bfd12a2d40ba724f9f42b838d2fab8d` / CI `34669687968` and GREEN `bbf0a03b95cf359a9a5c4ea643bf323ca24008de` / CI `34671321083`;
- real WordPress route/smoke exact-head GREEN `0230ef184ef9a7558cb38e6541a5d8e207f21fb5` / CI `34671573305`.

Invalid NOT RED / NOT GREEN checkpoints from earlier Task 6 subunits remain recorded verbatim in their dedicated progress records and were never rewritten into successful evidence.

## Verification

CI `34671573305` is exact-head GREEN across all permanent jobs:

- `php-quality` — GREEN;
- `js-quality` — GREEN;
- `package` — GREEN;
- `wordpress-smoke` — GREEN.

The WordPress smoke now includes a real `/admin/debug/playground` route assertion proving registration, anonymous denial, administrator bounded parsing, and rejection of request-level provider/runtime overrides. It terminates before external provider execution, so the assertion needs no live AI credential and cannot accidentally duplicate live generation/retrieval work.

Integration coverage also proves the production graph invokes semantic and lexical retrieval once and projects diagnostics from that same retrieval execution.

## Fresh closeout review

A fresh Task 6 correctness/security/performance/architecture review was performed after the WordPress smoke became GREEN.

### Correctness

- Route callback parses only `PlaygroundRequest` and delegates through the shared `PlaygroundRuntimeBootstrap`/`PlaygroundRequestHandler` path.
- Persisted configuration is resolved explicitly and fail-closed; there is no silent provider/store/default substitution.
- M10 retrieval evidence used by M11 is captured by observation rather than recomputed for diagnostics.
- Citation identifiers emitted by the production citation registry are request-local `C1`…`C12`; citation lineage fields are projection-bounded to 256 UTF-8-safe bytes.
- Persisted `model_id` is already bounded by the `Bot` aggregate's 191-byte persistence contract.

### Security

- Capability protection is exercised in real WordPress REST smoke.
- Request schema rejects all unknown keys, including runtime/provider overrides.
- Credentials and encrypted/provider-specific payloads are not serialized.
- Exception messages are not returned to administrators through Task 6 DTOs.
- Task 5 redaction/bounds remain the debug-trace authority.

### Performance

- Request composition is bounded request-local wiring.
- The Playground does not run a second semantic/lexical retrieval for diagnostics.
- Citation/debug projections retain hard list/scalar/content bounds.

### Architecture / duplication

- Existing production provider/vector-store registries, retrievers, fusion/confidence/access authorities, grounding/prompt/citation graph, and M11 orchestration are reused.
- No parallel Playground scoring, normalization, fusion, reranking, grounding, prompt, memory, citation, provider, embedding, or vector-store implementation exists.

### Findings

- **Critical:** 0 unresolved.
- **Important:** 0 unresolved.

A separate reviewer/subagent transport was not exposed in this runtime, so this closeout does not falsely claim an independent reviewer. The repository-approved fresh fallback review was used and the transport limitation is recorded here.

## Task 6 result

Task 6 acceptance criteria are satisfied. Task 7 — Playground UI — is the next milestone unit. It must consume only the Task 6 bounded trace DTO, render safe structured sections/errors, remain keyboard/responsive accessible, and never render arbitrary backend/provider messages.
