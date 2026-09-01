# WP RAG AI Chatbot — Master Architecture

Status: APPROVED IN CHAT; WRITTEN SPEC AWAITING USER REVIEW

## Architectural style

Use a PHP-first WordPress modular monolith with explicit domain boundaries and replaceable infrastructure adapters. WordPress hooks/REST/bootstrap code belong at integration boundaries rather than being scattered through domain logic. Dependencies should flow from presentation/integration layers to application services, domain contracts, then infrastructure adapters.

Conceptual modules:

- Core
- Admin
- REST
- Database
- Security
- Privacy
- Providers
- Embeddings
- Knowledge
- Documents
- Chunking
- Indexing
- VectorStore
- Retrieval
- RAG
- Chat
- Conversations
- Memory
- Citations
- WooCommerce
- Leads
- Forms
- LiveSupport
- Actions
- Analytics
- Evals
- Jobs
- Integrations

These are bounded responsibilities, not instructions to create one manager class per noun. YAGNI applies: introduce an interface when it has a concrete implementation boundary or near-term alternate implementation.

## Provider architecture

Core application code must not contain vendor-specific request logic. Proposed capability contracts include AI generation, embeddings, streaming, tool use, vision, audio, model discovery/capabilities, usage/error normalization, and health/configuration checks.

Initial provider adapters:

1. OpenAI Direct Adapter — current Responses API where appropriate, embeddings, model discovery, streaming, tool calling, multimodal capabilities, usage/error normalization, and optional OpenAI Vector Store integration.
2. OpenRouter Direct Adapter — chat, streaming, tool calling, embeddings, model discovery/capabilities, routing metadata, usage/error normalization, and optional reranking support.
3. WordPress AI Client Adapter — uses WordPress 7.0+ AI Client and Connectors when available without making WP 7 mandatory.

The browser must never call provider APIs directly or receive provider credentials.

## WordPress AI infrastructure

WordPress 6.9 provides the Abilities API. WordPress 7.0 provides the built-in provider-agnostic AI Client and Connectors API. The WordPress AI Client documentation recommends feature-specific server-side REST endpoints instead of arbitrary client-side prompt execution for distributed plugins. The plugin therefore keeps feature authorization and prompt orchestration server-side.

Abilities should reuse the same application services as internal actions. MCP exposure is explicit opt-in and permission-aware; read-only and write/destructive abilities have different policies.

## Persistence

Do not store application-scale data or embeddings in giant wp_options blobs. Use versioned dedicated tables introduced only as needed. Conceptual data domains include bots, sources, documents, chunks, vector/index metadata, indexing runs, jobs, conversations, messages, summaries, retrieval traces, leads, forms/submissions, feedback, action audits, analytics events/aggregates, evaluation cases, and evaluation runs.

Schema migrations must be versioned, idempotent, upgrade-tested, and protected against concurrent execution. dbDelta may be used where suitable but does not replace an explicit migration strategy.

## Knowledge and document model

Every source normalizes to a canonical Document containing source identity, external identity, type, title, canonical URL, normalized content, structured metadata, source version/hash, language, visibility/access metadata, and timestamps.

Knowledge sources eventually include WordPress posts/pages/public CPTs/taxonomies, manual text, FAQ, selected URLs, sitemap URLs, supported files, and WooCommerce products.

Document extraction is isolated behind extractor contracts for PDF, DOCX, TXT, Markdown, HTML, CSV, JSON, and XML. Files and crawled content are untrusted and must pass MIME/size/URL/security policies.

## Indexing pipeline

Source -> Extract -> Normalize -> Metadata -> Structure Analysis -> Chunk -> Hash -> Deduplicate -> Embed -> Vector Store -> Finalize Source Version.

Indexing is incremental and idempotent. Unchanged source/chunk hashes skip unnecessary embeddings. A working index should not be destructively removed before a replacement is ready.

## Chunking

Default production strategy: structure-aware recursive chunking that prefers heading/section, paragraph, and sentence boundaries, then enforces token limits. Overlap is configurable and used deliberately rather than universally. Chunk metadata preserves source/document URL/title/heading/post or product IDs/version/hash/language/sequence/parent relationship and embedding configuration.

Parent-child metadata is included early enough to support future small-to-big retrieval without redesigning the schema.

## Embedding compatibility invariant

An index/collection configuration is defined by at least provider + model + dimensions + distance/normalization configuration. Incompatible vectors must never silently coexist. Changing incompatible embedding configuration requires controlled reindexing.

## Vector stores

VectorStore contracts support upsert/delete/search/capabilities/health and metadata filters where available. Target adapters: local WordPress store, Qdrant, Pinecone, Chroma, OpenAI Vector Store; Supabase/pgvector may follow later.

The local adapter is for zero-infrastructure modest installations and prioritizes correctness, bounded candidate sets, and honest scale documentation. It must not claim dedicated-vector-engine scaling.

## Background jobs

Use a database-backed WordPress queue with batching, leases, retries/backoff, failure state, interrupted-job reclamation, idempotency keys, progress, and practical cancellation. Execution supports WP-Cron plus a server-cron/WP-CLI path for sites with WP-Cron disabled. Large ingestion/reindex work never depends on one normal admin request surviving.

## Retrieval

Query flow:

User Message -> validation/rate/cost checks -> ownership/context -> query processing -> query embedding -> semantic retrieval + lexical/exact retrieval -> score normalization/fusion -> metadata/access filters -> optional reranking -> confidence threshold -> context selection.

Lexical retrieval is mandatory because SKUs, model numbers, proper nouns, IDs, error codes, and exact phrases are often poorly served by vectors alone. Initial hybrid fusion should be simple and explainable (for example weighted normalized scoring or RRF) and observable in the debugger.

## RAG orchestration

Context assembly combines policy, bounded conversation memory, newly retrieved evidence, and the current question. Retrieved content is always untrusted data and cannot become system instructions, tool permissions, or authorization policy.

Strict mode enforces an application-level deterministic no-answer path rather than relying only on model instructions.

Each selected chunk receives an internal citation identity before generation. Post-generation citation validation ensures citations resolve to actually selected/retrieved content and permitted URLs. Invented citations are rejected/removed or converted into a controlled no-source representation.

## Memory

Do not resend unlimited transcripts. Compose context from recent relevant messages + a versioned conversation summary + fresh RAG context + current question. Retention/privacy configuration is distinct from model context management.

## Streaming

Provider-specific streaming is normalized into internal events (conceptually message.start, message.delta, citation, tool.start, tool.complete, message.complete, error). The REST/chat boundary streams normalized events to the frontend without exposing provider credentials or raw vendor protocols.

## Frontend/admin

Use React/TypeScript as compiled WordPress assets where beneficial. The public widget supports floating, embedded, shortcode, block, fullscreen/mobile, and later secure cross-site embed modes. Appearance is a validated structured schema shared by admin preview and runtime widget.

Display/proactive rules are deterministic data evaluated by a testable rules engine rather than scattered UI conditionals.

## WooCommerce

Index descriptive/stable product knowledge. Fetch current price, sale state, stock, variation availability, cart, authenticated order state, discounts, and other transactional values through authorized WooCommerce services/actions at execution time. The LLM must never invent those values.

## Actions, Abilities, MCP

Actions have stable IDs, descriptions, JSON input schema, output shape, risk class, authentication/authorization policy, timeout, audit policy, and execution handler. The model may propose a tool call; PHP authorization decides whether execution is permitted.

No arbitrary PHP, SQL, shell, unrestricted WordPress execution, or anonymous privileged mutation tool is allowed.

## RAG debugger/evals

The debugger records query transformation, semantic/keyword candidates and raw scores, normalized/hybrid scores, filters, reranking, final chunks, context estimate, provider/model configuration, answer, citation mapping, latency, usage, estimated cost, and warnings/errors with secret redaction.

Saved evaluations support expected sources/facts, prohibited claims, expected no-answer/action, tags, Recall@K, MRR, source hit, groundedness/answer correctness where measurable, citation correctness, no-answer correctness, latency, and cost. Configuration changes must be regression-testable against saved suites.

## Security/privacy

Threat boundaries include anonymous REST, authenticated customers, admin APIs, crawled content, uploaded files, provider calls, vector stores, external embeds, LLM-to-action requests, WordPress Abilities, and MCP clients. Security is enforced in every milestone and audited comprehensively in M22.

Secrets remain server-side and are never returned through public REST, HTML, bundles, localStorage, or normal logs. Privacy supports configurable transcript storage/retention, visitor identity handling, IP anonymization/hashing, lead consent, provider disclosures, WordPress exporter/eraser integration, and bounded analytics retention.

## Test strategy

Separate verification layers cover PHP unit/integration, WordPress integration, migrations, provider/vector contracts, retrieval quality, REST/security, JS/TS unit/component, typecheck/lint/build, Playwright/E2E/accessibility, WooCommerce, activation/deactivation, upgrades, uninstall policy, and release ZIP validation. Live provider tests are opt-in and credential-gated; normal CI must not consume paid API credits.
