# M13 Knowledge Manager & RAG Debugger Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver administrator-only knowledge/indexing management and a bounded, redacted retrieval/RAG diagnostic playground using the existing M04-M12 backend and admin seams.

**Architecture:** Add focused M13 REST projection resources over existing repositories/domain services and render those allow-listed DTOs in the existing M12 admin application. Keep authorization, filtering, retrieval, scoring, job transitions, redaction and provider interaction server-side; the browser only navigates and renders bounded results.

**Tech Stack:** PHP 8.2+, WordPress REST API, existing repository/domain abstractions, React/TypeScript admin app, PHPUnit, Jest, PHPStan/PHPCS/ESLint/Prettier, GitHub Actions and wp-env smoke tests.

**Spec:** `docs/superpowers/specs/2026-09-08-m13-knowledge-manager-debugger-design.md`

## Global Constraints

- All M13 REST routes use `wp-rag-ai-chatbot/v1/admin/*` and `AdminCapability::can_manage`.
- Browser traffic uses the existing same-origin nonce-authenticated M12 admin client.
- Never serialize credentials, encrypted credential blobs, authorization headers, arbitrary provider error bodies, auth/session data, unrestricted profile metadata, or environment dumps.
- Default page size is 20 and REST page size is capped at 100 unless an existing repository contract is stricter.
- Do not duplicate knowledge persistence, job lifecycle, retrieval/reranking or RAG logic in M13.
- M21 evaluation/regression-suite behavior remains out of scope.
- Every behavior task requires genuine RED -> GREEN evidence before completion.

---

### Task 1: Knowledge source inventory REST projection

**Files:**
- Create: `src/Admin/Rest/KnowledgeSourceRestResource.php`
- Modify: `src/Admin/Rest/AdminRestBootstrap.php`
- Create: `tests/Unit/Admin/KnowledgeSourceRestResourceTest.php`
- Create: `tests/Unit/Admin/KnowledgeSourceRoutesTest.php`

**Interfaces:**
- Consumes: `KnowledgeSourceRepository::paginate(int $page = 1, int $per_page = 20): PagedResult`, `KnowledgeSourceRecord`.
- Produces: `KnowledgeSourceRestResource::list(int $page, int $perPage): array` and `GET /admin/knowledge/sources`.

- [ ] **Step 1: Write the failing resource test**

Construct an in-memory `KnowledgeSourceRepository` fake returning a `PagedResult` with records whose `config` contains a sentinel secret-like value. Assert `list(1, 20)` returns only `id`, `source_key`, `source_type`, `external_id`, `title`, `canonical_url`, `status`, `last_synced_at`, `updated_at` and pagination metadata, and assert JSON-encoded output does not contain the config sentinel or source hash.

- [ ] **Step 2: Verify RED**

Run through repository CI (or local PHPUnit if dependencies are available):

```bash
composer test -- --filter KnowledgeSourceRestResourceTest
```

Expected: FAIL because `KnowledgeSourceRestResource` does not exist.

- [ ] **Step 3: Implement the minimal projection**

Create `KnowledgeSourceRestResource` with constructor injection of `KnowledgeSourceRepository`; reject page/per-page below 1 or above 100 with the repository-owned `invalid_request` shape; map records to the exact allow-list above; format timestamps with `DATE_ATOM`; return `{items,total,page,per_page}`.

- [ ] **Step 4: Add route RED then GREEN**

Add a route test requiring `GET /admin/knowledge/sources` to register with `AdminCapability::can_manage`. Then register the route in `AdminRestBootstrap`, parse `page`/`per_page` with the established positive-integer helper, instantiate `WpdbKnowledgeSourceRepository` using the existing `WpdbConnection`/`TableNames` pattern, and delegate to the resource.

- [ ] **Step 5: Verify broader GREEN and commit**

```bash
composer test
composer lint
composer analyse
npm test -- --runInBand
npm run lint
```

Commit only after focused and broad checks are green.

### Task 2: Source detail and bounded document/chunk inspection

**Files:**
- Create: `src/Admin/Rest/KnowledgeDetailRestResource.php`
- Modify: `src/Admin/Rest/AdminRestBootstrap.php`
- Test: `tests/Unit/Admin/KnowledgeDetailRestResourceTest.php`
- Test: `tests/Integration/KnowledgeAdminInspectionTest.php`

**Interfaces:**
- Consumes existing knowledge-source, document and chunk repositories.
- Produces `GET /admin/knowledge/sources/{id}`, `/documents`, and bounded chunk-detail projections.

- [ ] Add failing tests for not-found, source detail, bounded child pages and chunk truncation indicator.
- [ ] Prove RED for missing resource/routes.
- [ ] Implement allow-listed detail DTOs without raw config or unbounded child collections.
- [ ] Prove integration results match persisted M04-M07 fixtures.
- [ ] Run full relevant verification and commit.

### Task 3: Recoverable job status and safe lifecycle controls

**Files:**
- Create: `src/Admin/Rest/KnowledgeJobRestResource.php`
- Modify: `src/Admin/Rest/AdminRestBootstrap.php`
- Test: `tests/Unit/Admin/KnowledgeJobRestResourceTest.php`
- Test: `tests/Integration/KnowledgeJobAdminTest.php`

**Interfaces:**
- Consumes M09 job repository/queue/state-transition seams.
- Produces bounded job status plus enqueue/cancel/retry endpoints with stable `invalid_transition` responses.

- [ ] Write RED tests proving unsupported transitions do not mutate persisted job state.
- [ ] Implement only state transitions supported by M09 contracts.
- [ ] Project safe error code/message fields; never serialize raw exception/provider payloads.
- [ ] Verify integration against persisted jobs and commit.

### Task 4: Knowledge manager admin UI

**Files:**
- Modify the existing M12 admin router/screen files under `assets/admin/` or their current source paths.
- Modify: `assets/admin.css`
- Test the existing Jest admin test suite.

**Interfaces:**
- Consumes Tasks 1-3 REST DTOs through the existing typed nonce client.
- Produces Knowledge and Jobs admin views.

- [ ] Add UI RED for paginated source rendering, selected detail, loading/empty/error, allowed job actions and keyboard-labelled controls.
- [ ] Implement server-authoritative pagination and mutation refresh; do not cache secret/unbounded data.
- [ ] Add constrained-width/long-content accessibility tests and CSS.
- [ ] Verify Jest + PHP integration + full CI and commit.

### Task 5: Structured debug trace projection and redaction

**Files:**
- Create: `src/Debug/DebugTrace.php`
- Create: `src/Debug/DebugTraceProjector.php`
- Test: `tests/Unit/Debug/DebugTraceProjectorTest.php`

**Interfaces:**
- Consumes M10 retrieval evidence and M11 RAG/citation/model/usage results.
- Produces an immutable structured trace containing bounded candidates, scores, filters/rerank, selected chunks, context estimate, answer/citations, model IDs, latency/usage/cost and stable errors.

- [ ] Write RED fixtures containing secret-like/provider-message sentinels and oversized chunk text.
- [ ] Implement explicit allow-list mapping, list limits and chunk truncation.
- [ ] Prove sentinels cannot appear in serialized trace output.
- [ ] Verify deterministic fixture correlation and commit.

### Task 6: Playground REST execution

**Files:**
- Create: `src/Admin/Rest/PlaygroundRestResource.php`
- Modify: `src/Admin/Rest/AdminRestBootstrap.php`
- Test: `tests/Unit/Admin/PlaygroundRestResourceTest.php`
- Test: `tests/Integration/PlaygroundRetrievalTest.php`

**Interfaces:**
- Consumes production M10/M11 retrieval/RAG seams and Task 5 projector.
- Produces `POST /admin/debug/playground`.

- [ ] Write RED for invalid/bounded question input and representative backend fixture trace.
- [ ] Execute existing production pipeline; do not duplicate scoring/reranking.
- [ ] Map thrown/internal failures to stable safe codes (`retrieval_unavailable` / `playground_failed`).
- [ ] Verify integration correlation and commit.

### Task 7: Playground UI

**Files:**
- Modify existing M12 admin TypeScript/React router and screens.
- Modify: `assets/admin.css`
- Test existing Jest admin suite.

**Interfaces:**
- Consumes Task 6 trace DTO only.
- Produces question form plus diagnostic sections for candidates/scores/filter/rerank/context/answer/citations/model/latency/usage/cost/errors.

- [ ] Write UI RED for structured trace fixture rendering and known safe error mapping.
- [ ] Implement semantic sections/disclosures with labelled controls and live async status.
- [ ] Verify long URLs/chunks, keyboard flow, mobile layout and no arbitrary backend-message rendering.
- [ ] Run full CI and commit.

### Task 8: M13 integration, smoke, review and closeout

**Files:**
- Modify: `scripts/wp-smoke-test.sh` or current WordPress smoke harness.
- Modify: `docs/milestones/M13-knowledge-manager-playground-debugger.md`
- Modify: `docs/progress/STATUS.md`
- Add focused M13 progress evidence files only where the repository's existing pattern requires them.

**Interfaces:**
- Consumes Tasks 1-7.
- Produces verified M13 milestone completion.

- [ ] Add smoke RED for capability protection and representative knowledge/playground routes.
- [ ] Make smoke GREEN without weakening existing M00-M12 coverage.
- [ ] Perform correctness, security, performance and accessibility review; resolve all Critical/Important findings.
- [ ] Obtain exact-final-SHA CI green for `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.
- [ ] Reconcile milestone/progress docs, open/update PR, and merge only when every merge gate is satisfied.
- [ ] Verify fresh post-merge `main` CI before marking M13 complete.

## Plan self-review

- Spec coverage: every M13 acceptance criterion maps to Tasks 1-8.
- Placeholder scan: no TODO/TBD/implementation placeholders remain.
- Type consistency: Task 1 establishes bounded source projection; Tasks 2-4 extend inventory; Task 5 establishes trace DTO consumed by Tasks 6-7; Task 8 integrates all surfaces.
- Milestone boundary: M21 eval regression behavior is not introduced.

**AUTO-APPROVED — SCHEDULED MODE**