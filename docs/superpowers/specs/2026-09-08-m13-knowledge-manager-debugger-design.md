# M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger Design

Status: **AUTO-APPROVED — SCHEDULED MODE**

## Goal

Expose administrator-only knowledge/indexing operations and a retrieval/RAG diagnostic playground while preserving the existing M04-M11 domain boundaries and the M12 same-origin, nonce-authenticated admin shell.

## Scope

M13 adds bounded administration resources and UI for:

- paginated knowledge sources and documents with index/sync state;
- recoverable job status and safe enqueue/cancel/retry actions where existing job semantics permit them;
- chunk inspection with bounded text/metadata views;
- a test-question playground that reports structured retrieval and RAG diagnostics;
- semantic and lexical candidates, normalized/hybrid scores, filters, rerank decisions, selected chunks, context estimate, model identifiers, answer/citations, latency, usage/cost when already available, and stable error codes;
- desktop/mobile, loading/empty/error, long-content, and keyboard-accessible admin states.

M13 does **not** add the M21 evaluation/regression framework, change retrieval ranking algorithms, add new provider credentials, expose raw provider payloads, or create a parallel indexing/job implementation.

## Existing seams to reuse

- `KnowledgeSourceRepository::paginate()` / `findById()` for source truth.
- Existing document/chunk repositories from M05-M07 for persisted inspection.
- Existing M09 jobs/queue abstractions for status and safe lifecycle actions.
- Existing M10 hybrid retrieval pipeline and evidence objects for candidate/score diagnostics.
- Existing M11 RAG orchestration/citations/model/usage outputs for answer diagnostics.
- `AdminRestBootstrap::REST_NAMESPACE`, `AdminCapability::can_manage`, and the M12 nonce-authenticated browser client/admin shell for transport and authorization.

## Architecture choice

### Recommended: projection resources over existing domain services

Add focused M13 admin REST resources that translate existing domain records/results into small explicit DTO arrays. The browser receives only those DTOs and performs rendering/navigation; all filtering, retrieval, authorization, mutation semantics, and compatibility decisions remain server-side.

This keeps M13 observational and administrative rather than creating a second knowledge or RAG engine.

### Alternative 1: expose repositories directly through generic CRUD

Rejected. It would leak persistence shape, couple the browser to database records, and make redaction/authorization harder to reason about.

### Alternative 2: return raw retrieval/provider trace objects

Rejected. Raw traces may contain provider messages, personal data, oversized content, or future internal fields. M13 requires an explicit redacted contract.

### Alternative 3: build a client-side debugger pipeline

Rejected. Reimplementing scoring/filter/rerank logic in JavaScript risks divergence from the production backend and violates the milestone requirement that playground results correlate with backend trace fixtures.

## REST resource boundaries

All routes live under `wp-rag-ai-chatbot/v1/admin/*` and use `AdminCapability::can_manage`.

### Knowledge inventory

`GET /admin/knowledge/sources?page=1&per_page=20`

Returns a bounded page with:

- source `id`, stable `source_key`, `type`, display title/label when available;
- safe source URL/reference when already public/admin-safe;
- sync/index state, last-success/last-error timestamps when persisted;
- document/chunk counts only when available without unbounded scans;
- no raw credentials, provider payloads, or unrestricted post/user metadata.

`GET /admin/knowledge/sources/{id}` returns one safe source detail projection.

Later M13 tasks add bounded document/chunk child resources rather than embedding arbitrarily large collections in a source response.

### Job controls

Job routes expose stable lifecycle state and only actions already valid in M09. Cancel/retry/enqueue endpoints must reject unsupported transitions deterministically instead of forcing state changes.

### Playground

`POST /admin/debug/playground` accepts a bounded test question plus explicit existing bot/retrieval configuration identifiers. It executes the production retrieval/RAG seams and returns a redacted `DebugTrace` projection.

The trace is structured data, not logging text. Candidate lists and chunk text are bounded. Arbitrary upstream exception messages and secrets are never serialized.

## Redaction contract

A dedicated redaction/projection layer is mandatory before any debug trace enters REST output.

Never serialize:

- provider credentials or encrypted credential blobs;
- HTTP authorization headers;
- arbitrary upstream/provider error bodies;
- internal WordPress auth/session material;
- unrestricted user/profile metadata;
- full environment/config dumps.

Use stable error codes plus repository-owned safe messages. Public source URLs and document/chunk content already intentionally indexed may be shown to authorized administrators, but payload sizes remain bounded.

## Boundedness and performance

- Page size defaults to 20 and is capped at 100 unless an existing repository contract is stricter.
- Playground candidate/chunk collections use explicit server-side limits derived from retrieval configuration and a hard administration cap.
- No endpoint performs an unbounded source/document/chunk scan solely to calculate counts.
- Large chunk text is returned only on explicit detail/trace requests and is capped/truncated with a clear indicator.
- UI rendering uses pagination or bounded lists; virtualization is only added if measured result sizes require it.

## UI structure

Extend the existing M12 admin application with three top-level M13 views:

1. **Knowledge** — paginated sources, selected source detail, child document/chunk navigation, sync/index status, safe job actions.
2. **Jobs** — recoverable status/error visibility and allowed lifecycle actions.
3. **Playground** — test question input and structured diagnostic sections for retrieval candidates, score/fusion/rerank decisions, selected context, answer/citations, model/latency/usage/cost, and stable errors.

Use semantic headings, labelled controls, live status for async operations, keyboard-reachable disclosure/details, and existing responsive admin CSS conventions.

## Data flow

1. Browser calls same-origin nonce REST client.
2. M13 REST bootstrap authorizes through `AdminCapability::can_manage`.
3. Focused resource class invokes an existing repository/domain service.
4. Projection/redaction class maps the domain result into an explicit bounded DTO.
5. REST returns only that DTO.
6. Browser renders without recomputing backend ranking, capability, or job-transition logic.

## Error handling

REST resources return repository-owned stable codes such as `invalid_request`, `not_found`, `invalid_transition`, `retrieval_unavailable`, and `playground_failed` with safe actionable messages. Raw exception/provider messages remain server-side.

The UI maps only known stable codes to actionable copy and falls back to a generic safe error state.

## Security

- Every M13 route is administrator capability protected.
- Mutations continue to rely on WordPress REST nonce behavior through the existing admin client.
- Debug DTOs are allow-listed projections, not recursive object serialization.
- Retry/cancel/enqueue semantics delegate to M09 state-transition rules.
- No secret-bearing boot data or credential rehydration is added.

## Testing strategy

Each task follows strict RED -> GREEN with exact-SHA CI evidence when local dependency execution is unavailable.

Required coverage:

- unit tests for source/job/debug projections, redaction and bounds;
- REST route/authorization/request validation tests;
- repository/integration tests proving projections correlate with persisted M04-M11 fixtures;
- UI tests for pagination, loading/empty/error, long content, safe stable-error mapping and keyboard-accessible controls;
- WordPress smoke additions for capability protection and representative M13 routes;
- final correctness, security, performance and accessibility review.

## Milestone decomposition

1. Knowledge-source inventory REST projection and pagination.
2. Source detail plus bounded document/chunk inspection.
3. Job status projection and safe enqueue/cancel/retry controls.
4. Knowledge manager UI over Tasks 1-3.
5. Structured retrieval debug-trace domain projection/redaction.
6. Playground REST execution using existing M10/M11 pipelines.
7. Playground UI with retrieval/RAG diagnostic sections.
8. Integration/smoke, responsive/accessibility hardening and M13 closeout.

## Self-review

- No TODO/TBD placeholders remain.
- The design reuses M04-M12 boundaries and adds no parallel persistence/retrieval/provider subsystem.
- Security boundaries are explicit and testable.
- Result-size bounds and mutation rules are explicit.
- M21 evaluation scope remains excluded.

**AUTO-APPROVED — SCHEDULED MODE**