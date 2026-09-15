# Modern Knowledge and Publish Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a polished React setup flow that lets a site owner use the configured Gemini key, add knowledge, attach it to a chatbot, test grounded answers, and publish the chatbot on the frontend without manual database values.

**Architecture:** Keep the existing `wp.element` React runtime, REST namespace, source normalizers, queue, retrieval binding repository, production RAG graph, and public widget runtime. Add the missing Gemini embedding capability, a small source-sync orchestration job that reuses `index.document`, administrator resources, and presentational React screens. Do not add a second chat client, retrieval engine, credential store, or widget runtime.

**Tech Stack:** PHP 8.2, WordPress 6.9+, PHPUnit/Brain Monkey, TypeScript, Jest/JSDOM, `@wordpress/scripts`, existing WordPress uploads API.

**Spec:** `docs/superpowers/specs/2026-09-15-modern-knowledge-publish-flow-design.md` (approved in chat).

## Global constraints

- Keep `wp-rag-ai-chatbot/v1` as the only administrator/public REST namespace.
- Reuse `createAdminApiClient`, `KnowledgeBootstrap`, existing source normalizers, `DocumentIndexJobEnqueuer`, `BotRetrievalBindingRepository`, and `widget-runtime.ts`.
- The guided Gemini path uses the documented OpenAI-compatible Gemini embedding endpoint with the fixed text profile `gemini-embedding-001`, 3072 dimensions, no client-side embedding override, and the existing local WordPress vector store. The generation model remains selected from the existing Gemini model catalog.
- Store the server-owned collection/profile in the persisted source configuration; never ask the browser to invent collection, profile, provider, or filesystem identifiers.
- Accept only `wordpress_posts`, `manual_text`, `faq`, `woocommerce_product`, and `file` source types.
- Never expose credentials, raw source configuration, server paths, raw provider payloads, or raw exception bodies to the browser.
- All new administrator routes use `AdminCapability::can_manage` and the existing same-origin nonce client.
- Every production behavior change starts with a focused failing test and ends with focused plus full verification.
- Keep existing shortcodes, the dynamic Gutenberg block, public request shape, and fail-closed public behavior backward-compatible.

---

### Task 1: Make the configured Gemini key usable for indexing and retrieval

**Files:**

- Modify: `src/Providers/OpenAiCompatible/OpenAiCompatibleChatProvider.php`
- Modify: `src/Providers/ProviderBootstrap.php`
- Create: `tests/Unit/Providers/OpenAiCompatibleEmbeddingTest.php`
- Modify: existing provider bootstrap/registry tests

**Interfaces:**

- Consume the existing credential resolver, HTTP client, redaction, `EmbeddingRequest`, and `EmbeddingResult` contracts.
- Produce an embedding-capable `gemini_direct` adapter using `https://generativelanguage.googleapis.com/v1beta/openai/embeddings`.

- [ ] Write failing tests for the embedding request body, endpoint, authorization redaction, response ordering, malformed vectors, and provider identity.
- [ ] Run `vendor/bin/phpunit --filter OpenAiCompatibleEmbeddingTest` and confirm RED because the compatible provider does not implement `EmbeddingProvider`.
- [ ] Add the smallest embedding capability to the existing compatible provider. Keep generation/model-discovery behavior unchanged; only the embedding endpoint and parser are new.
- [ ] Register the embedding capability for Gemini in `ProviderBootstrap`; do not expose embedding models in the generation model picker.
- [ ] Run focused PHPUnit, PHPCS, PHPStan, and the existing provider suite.

---

### Task 2: Add production source synchronization and document indexing

**Files:**

- Create: `src/Jobs/Sync/KnowledgeSourceSyncJobPayload.php`
- Create: `src/Jobs/Sync/KnowledgeSourceSyncJobEnqueuer.php`
- Create: `src/Jobs/Sync/KnowledgeSourceSyncJobHandler.php`
- Create: `src/Jobs/Sync/WordPressDocumentIndexDependencies.php`
- Modify: `src/Jobs/JobWorkerBootstrap.php`
- Modify: `src/Knowledge/KnowledgeBootstrap.php` only if the worker composition needs the registered source registry exposed safely
- Test: `tests/Unit/Jobs/Sync/KnowledgeSourceSyncJobTest.php`
- Test: `tests/Integration/Jobs/Sync/WordPressDocumentIndexDependenciesTest.php`

**Interfaces:**

- `sync.source` receives only `{source_id, collection_id, configuration_id, generation}`.
- The handler resolves the persisted source through `KnowledgeSourceRepository`, normalizes it through `KnowledgeSourceRegistry`, saves canonical `DocumentRecord` values, and enqueues one existing `index.document` payload per document.
- `WordPressDocumentIndexDependencies` resolves the saved document, runs the existing `DocumentIndexPipeline`, stamps the persisted Gemini embedding compatibility fingerprint through the existing `ChunkingConfig`, executes `IndexEmbeddingExecutor`, and wraps it with `SearchProjectionDocumentIndexDependencies`.

- [ ] Write failing tests proving a source-sync payload rejects malformed identifiers, a valid source produces document-index jobs, a missing source fails safely, and an indexed manual document creates both vector and lexical projections.
- [ ] Run the focused tests and confirm RED because the source-sync payload/handler and production dependency composition do not exist.
- [ ] Implement the payload/enqueuer/handler with bounded progress and the existing job clock/repository seams. Do not create a second document-index algorithm.
- [ ] Compose the production worker with `KnowledgeBootstrap::registry()`, `WpdbKnowledgeSourceRepository`, `WpdbDocumentRepository`, `WpdbChunkSearchStore`, `ProviderBootstrap::registry()`, local `VectorStoreBootstrap`, and the existing pipeline/executor types.
- [ ] Derive one server-owned configuration identity from the fixed Gemini embedding profile and reject queued payloads that do not match the current source configuration.
- [ ] Schedule the existing WordPress job hook once after a successful enqueue so new jobs do not wait for the hourly interval. Do not execute network/provider work inside the administrator request.
- [ ] Run focused PHPUnit, the existing queue/indexing suites, PHPCS, PHPStan, and the WordPress database/knowledge smoke checks.

**Deliberate ceiling:** source synchronization enumerates the configured source in one bounded worker run. A very large catalog can later be paged across child sync jobs; that is not needed for this owner-facing first setup path.

---

### Task 3: Add validated knowledge-source creation

**Files:**

- Create: `src/Admin/Rest/KnowledgeSourceCreateResource.php`
- Modify: `src/Admin/Rest/AdminRestBootstrap.php`
- Modify: `src/Admin/Rest/KnowledgeSourceRestResource.php` only to project safe source-sync state
- Test: `tests/Unit/Admin/KnowledgeSourceCreateResourceTest.php`
- Test: existing administrator route-registration tests

**Interfaces:**

- Produce `POST /admin/knowledge/sources` returning `{source, job}` where `job` is the source-sync job projection.
- JSON handles WordPress, manual text, FAQ, and WooCommerce sources. Multipart form data handles files.

- [ ] Write failing tests for manual text creation, WordPress defaults, FAQ validation, WooCommerce selection validation, stable source-key generation, duplicate submission behavior, path injection rejection, and uploaded-file validation.
- [ ] Run `vendor/bin/phpunit --filter KnowledgeSourceCreateResourceTest` and confirm RED.
- [ ] Normalize only the finite source shapes already specified. Generate a deterministic source key from the normalized title/type/config; return a safe conflict for an existing key instead of creating duplicate rows.
- [ ] Persist the server-owned `semantic_retrieval` configuration for the Gemini embedding profile and the fixed local collection. The client does not provide these values.
- [ ] For files, validate the upload error/size/MIME through WordPress upload APIs, move the file below a plugin-owned uploads subdirectory, and record only the server-owned path plus allowed root.
- [ ] Persist `active` source state, enqueue `sync.source`, and return bounded source/job DTOs. Convert validation/database/queue failures to stable safe codes.
- [ ] Register the capability-protected route and run focused PHPUnit, PHPCS, PHPStan, and the existing knowledge REST tests.

---

### Task 4: Expose and persist bot knowledge binding

**Files:**

- Create: `src/Admin/Rest/BotRetrievalResource.php`
- Modify: `src/Admin/Rest/AdminRestBootstrap.php`
- Test: `tests/Unit/Admin/BotRetrievalResourceTest.php`
- Test: existing bot/retrieval route tests

- [ ] Write failing tests for missing, valid, stale, malformed, and extra-key binding payloads; verify delete clears only the binding.
- [ ] Run `vendor/bin/phpunit --filter BotRetrievalResourceTest` and confirm RED.
- [ ] Implement `GET|PUT|DELETE /admin/bots/{id}/retrieval`. Validate the bot ID with `BotId`, source existence with `KnowledgeSourceRepository`, and collection existence/profile readiness with the existing local vector-collection table.
- [ ] Let the browser select only a persisted source. The server derives/validates the source-owned collection and returns `{configured, source_id, source_title, collection_id, collection_ready}` without arbitrary configuration.
- [ ] Save/clear only `BotRetrievalBinding`; never delete source, document, vector, or job data.
- [ ] Run focused and existing bot/retrieval suites, PHPCS, and PHPStan.

---

### Task 5: Add bounded setup readiness

**Files:**

- Create: `src/Admin/Rest/SetupReadinessRestResource.php`
- Modify: `src/Admin/Rest/AdminRestBootstrap.php`
- Test: `tests/Unit/Admin/SetupReadinessRestResourceTest.php`

- [ ] Write failing tests for provider/model, source, completed lexical/vector index, enabled bot, bound bot, and publishable-bot states.
- [ ] Run the focused test and confirm RED.
- [ ] Return backward-compatible readiness fields plus bounded counts/booleans: configured generation provider, configured Gemini embedding capability, source count, completed index presence, enabled bot count, and publishable bot presence.
- [ ] Keep all provider discovery and indexing truth server-side; do not infer completion from an optimistic browser mutation.
- [ ] Run focused PHPUnit and existing onboarding/readiness tests.

---

### Task 6: Build the modern React admin shell and Publish/Test screen

**Files:**

- Create: `src-js/admin-ui.ts`
- Modify: `src-js/index.ts`
- Modify: `src-js/admin-entry/index.ts`
- Modify: `assets/admin.css`
- Test: `src-js/admin-ui.test.ts`
- Modify: existing provider/bot/index tests

- [ ] Write failing component tests for labelled navigation (Overview, Knowledge, Chatbots, Publish/Test), four readiness cards, current-step action, accessible status regions, and publish snippets.
- [ ] Run `npx wp-scripts test-unit-js src-js/admin-ui.test.ts --runInBand` and confirm RED.
- [ ] Implement data-driven components with `window.wp.element.createElement` and the existing `ElementFactory`; keep REST calls out of presentational components.
- [ ] Preserve `#/providers`, `#/bots`, `#/knowledge`, and `#/playground` deep links while mapping them into the new shell.
- [ ] Keep provider credential/model behavior unchanged and show the connected Gemini state plus a direct next step after save.
- [ ] Add Publish/Test cards for floating, embedded, fullscreen, and Gutenberg publishing. Copy buttons use `navigator.clipboard.writeText` only after click and announce success in a polite live region.
- [ ] Add scoped modern CSS under `#wp-rag-ai-chatbot-admin`: neutral canvas, cards, rail, responsive grid, focus rings, status badges, modal/step panel, and mobile single-column fallback.
- [ ] Run focused and existing JS suites, `npm run lint:js`, `npm run typecheck`, and `npm run build`.

---

### Task 7: Add the Knowledge wizard and REST wiring

**Files:**

- Create: `src-js/knowledge-wizard.ts`
- Modify: `src-js/index.ts`
- Modify: `src-js/admin-entry/index.ts`
- Modify: `assets/admin.css`
- Test: `src-js/knowledge-wizard.test.ts`
- Modify: existing knowledge tests

- [ ] Write failing tests for manual text, FAQ rows, WordPress defaults, WooCommerce availability, multipart file submission, duplicate-submit protection, and authoritative source/job refresh.
- [ ] Run the focused Jest test and confirm RED.
- [ ] Extend the admin API client with one explicit `FormData` request path that preserves nonce/credentials and omits JSON `Content-Type`.
- [ ] Implement source-type cards, type-specific validation, accessible errors, submit progress, safe errors, source cards, job states, retry/cancel actions, and bounded document/chunk inspection.
- [ ] After creation, refresh sources/jobs using the existing request-generation/latest-request-wins guards. Never show optimistic completion.
- [ ] Run focused wizard/knowledge JS tests, source-create PHP tests, lint, typecheck, and build.

---

### Task 8: Add bot binding UI and complete publish readiness

**Files:**

- Create: `src-js/bot-knowledge-binding.ts`
- Modify: `src-js/index.ts`
- Modify: `src-js/admin-entry/index.ts`
- Modify: `assets/admin.css`
- Test: `src-js/bot-knowledge-binding.test.ts`
- Modify: existing bot/playground tests

- [ ] Write failing tests for unconfigured binding, source choices limited to persisted DTOs, server-derived collection display, save/disconnect states, and Publish disabled until all prerequisites are true.
- [ ] Run the focused Jest test and confirm RED.
- [ ] Implement bot knowledge selection using `GET|PUT|DELETE /admin/bots/{id}/retrieval`; the browser sends only the selected persisted source ID.
- [ ] Keep appearance/display-rule controls as advanced sections below the core setup.
- [ ] Connect readiness cards and Publish/Test warnings to server truth. Do not block the existing admin Playground route with browser-only state.
- [ ] Run focused and full JS/PHP suites, lint, typecheck, and build.

---

### Task 9: WordPress end-to-end smoke, package, and release verification

**Files:**

- Create: `scripts/test-wp-modern-setup.php`
- Create: `scripts/test-wp-modern-setup.sh`
- Create: `docs/progress/2026-09-15-MODERN-KNOWLEDGE-PUBLISH-FLOW.md`

- [ ] Write smoke assertions before integration fixes: create a manual source, observe `sync.source`, process child `index.document` work, confirm document/chunk/vector persistence, create a Gemini bot, bind the source, run Playground, render all shortcodes plus the block, and exercise public chat.
- [ ] Include fail-closed checks for disabled/unbound bots and verify no credentials/server paths appear in public bootstrap.
- [ ] Run `bash scripts/test-wp-modern-setup.sh`; classify environment startup failures separately from application assertion failures.
- [ ] Make only integration fixes required by the smoke; do not add a second runtime or bypass the queue.
- [ ] Run the complete existing verification: `composer test`, PHP lint, PHPStan with 2G, `npm run verify:js`, runtime-only ZIP build, package assertion, `unzip -t`, and the WordPress smoke.
- [ ] Record commit, test counts, package path, SHA-256, and frontend publishing instructions in the progress document.
- [ ] Build the final runtime-only ZIP and keep the live WordPress update as a separate final handoff after package verification.

**Closeout evidence required:** no raw credentials/paths in REST or public bootstrap, no new console/runtime errors, green PHP/JS/package/smoke checks, valid ZIP, and a tested frontend mount path.
