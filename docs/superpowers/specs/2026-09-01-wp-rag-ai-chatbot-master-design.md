# WP RAG AI Chatbot — Master Product & Architecture Design

Date: 2026-09-01
Status: APPROVED IN CHAT; WRITTEN SPEC AWAITING FINAL USER REVIEW
Branch: feat/m00-master-architecture

## Purpose

Define the single approved architectural envelope for M01-M24. Individual milestone implementation plans may choose concrete class/file names and split work further, but they must not silently change the product direction defined here.

## Core constraints

- WordPress/PHP is the mandatory server runtime.
- Do not introduce a mandatory Node.js/Python/LangChain/LangGraph service.
- Do not introduce Redis, RabbitMQ, Kafka, Temporal, or another mandatory external queue.
- External vector databases are optional adapters.
- OpenAI and OpenRouter direct integrations are first class.
- WordPress AI Client/Connectors integration is additive for compatible WordPress versions.
- Security, privacy, observability, evaluation, and background processing are cross-cutting requirements.

## Chosen approach

Use a modular WordPress monolith with explicit domain/application contracts and infrastructure adapters. This is preferred over an external AI microservice architecture and over making WordPress 7.0 AI infrastructure the sole provider path.

Benefits:
- deploys as a normal plugin;
- preserves PHP/WordPress ownership of credentials, permissions, WooCommerce state, and data lifecycle;
- makes providers/vector stores replaceable;
- keeps RAG behavior deterministic/testable;
- allows optional external scale without forcing infrastructure on small sites.

## Compatibility

Proposed baseline: WordPress 6.9+ and PHP 8.2+, verified in M01 and finalized in M24. WordPress 7.0+ activates AI Client/Connectors integration when available.

## Bounded components

Conceptual domains: Core, Admin, REST, Providers, Embeddings, Knowledge, Documents, Chunking, Indexing, VectorStore, Retrieval, RAG, Chat, Conversations, Memory, Citations, WooCommerce, Leads, Forms, LiveSupport, Actions, Analytics, Evals, Jobs, Security, Privacy, Database, Integrations.

Components expose narrow contracts and constructor dependencies where practical. WordPress globals/hooks are adapted at boundaries. No god classes.

## Provider capability model

Provider registration carries capabilities rather than assuming every model/provider supports streaming, tools, embeddings, image input, audio, or realtime. Model discovery/capability metadata is cached with bounded lifetime and administrators receive clear capability errors.

OpenAI direct integration uses current supported APIs (Responses API where appropriate, embeddings, streaming, tools, multimodal capabilities) without hard-coding model names across domain code.

OpenRouter direct integration supports its current OpenAI-compatible chat surface plus model/embedding discovery and provider-specific capabilities such as optional reranking where supported.

WordPress 7.0 AI Client integration remains an adapter. Feature-specific plugin REST endpoints retain granular permission/cost controls.

## Data model and migrations

Use dedicated versioned tables for application-scale data. Introduce tables incrementally by milestone rather than pre-creating the entire final schema. Migrations are idempotent, lock-protected, upgrade-tested, and failure-aware.

Domain repositories hide SQL details. SQL is prepared and bounded/paginated. Large vectors, messages, logs, and analytics do not accumulate in wp_options.

## Ingestion

Canonical flow:

Source -> Extract -> Normalize -> Metadata -> Structure -> Chunk -> Hash -> Deduplicate -> Embed -> Vector Store -> Finalize version.

Source adapters normalize WordPress content, files, URLs/sitemaps, FAQs/manual text, and WooCommerce products into common Documents. Extractors are isolated and file/URL input is treated as hostile until validated.

Incremental indexing compares source/document/chunk hashes, avoids duplicate embeddings, and supports safe replacement. Every chunk remains traceable to original source/document/URL/title/section/post/product/version/language and embedding configuration.

## Chunking

Default to structure-aware recursive chunking: headings/sections -> paragraphs -> sentences -> token enforcement. Overlap is configurable. Parent-child relationships and sequence metadata are stored so later small-to-big retrieval can expand context without rethinking storage.

## Vector storage

A VectorStore contract allows a WordPress-local adapter plus Qdrant, Pinecone, Chroma, and OpenAI Vector Store targets. Supabase/pgvector remains a future extension if justified.

Embedding compatibility is strict: incompatible provider/model/dimension/distance-normalization configurations cannot share an index as if interchangeable. A configuration change that invalidates vectors requires a controlled reindex.

Local storage is explicitly documented for modest installations. Dedicated vector engines remain the scale path.

## Jobs

The WordPress database queue supports pending/running/completed/failed state, leases, attempts, retry policy/backoff, idempotency, progress, cancellation where practical, interrupted-lease recovery, WP-Cron triggering, and server cron/WP-CLI execution. Indexing does not run synchronously inside a normal admin request beyond small bounded orchestration.

## Retrieval

Use parallel semantic + lexical/exact retrieval, metadata/access filters, explainable hybrid fusion, optional reranking, confidence thresholding, and context selection. Lexical retrieval is mandatory for exact terms such as SKUs, product names, IDs, model numbers, and error codes.

The RAG debugger displays each stage and score transformation.

## RAG and grounding

Request path:

User input -> abuse/rate/cost checks -> ownership/context -> query processing -> retrieval -> rerank -> confidence -> context -> grounding policy -> prompt -> provider/tool loop -> citation validation -> stream/persist/analytics.

Retrieved data is untrusted content and is explicitly delimited. It cannot elevate itself into system instructions, permission policy, or tool authorization.

Strict Knowledge Only uses deterministic application-level insufficient-evidence behavior. Knowledge Preferred and General Assistant are separate explicit modes.

Citations resolve only to chunks/sources actually selected for the answer and must pass URL/access validation.

## Conversations and memory

Memory combines recent messages, a maintained conversation summary, fresh retrieved evidence, and the current question. Conversation storage and retention are independently configurable. Unlimited transcripts are never blindly sent to providers.

## Frontend and admin

React/TypeScript may power compiled admin and widget bundles. Runtime entry points include floating launcher, embedded mode, shortcode, block, mobile/fullscreen, and later secure cross-site embed. Widget configuration is a validated public-safe schema, not raw wp_options.

Appearance configuration and preview share one schema. Display/proactive rules use a deterministic testable rules engine.

## WooCommerce

Index stable descriptive catalog information. Fetch current price/stock/variation/cart/order/discount state from WooCommerce services at action time. Tool/action handlers enforce authentication and order ownership; the model never fabricates transactional values.

## Actions / Abilities / MCP

Action definitions include stable ID, descriptions, JSON input schema, output shape, risk classification, authn/authz policy, timeout, audit policy, and handler. Tool requests are proposals; server-side PHP authorizes execution.

Reuse application services behind WordPress Abilities. MCP exposure is explicit, permission-aware, and risk-sensitive. Never expose arbitrary PHP/SQL/shell/WordPress execution to public chat or MCP.

## Multimodal

Image/file input, vision, speech-to-text, text-to-speech, and realtime voice are capability-gated. Unsupported provider/model capabilities are explained in the UI instead of failing opaquely.

## Debugger and evaluations

Debugger traces query, transformation, candidates, raw and normalized scores, filters, reranking, final context, provider/model data, citations, latency, usage/cost estimate, and warnings/errors with secret redaction.

Evaluation cases store questions, expected sources/facts, prohibited claims, optional expected action/no-answer, and tags. Runs report retrieval and answer/citation/no-answer quality plus latency/cost. Changing chunking, embeddings, retrieval, reranking, prompts, or chat models must be regression-testable.

## Security

Every external/input boundary gets validation, sanitization/escaping, capability/ownership checks, prepared SQL, rate/cost controls, log redaction, and targeted threat tests. URL crawling requires SSRF protection. Uploads require MIME/size/resource controls. Conversation and WooCommerce endpoints prevent IDOR. Cross-site embeds validate origins/tokens as designed. Prompt/retrieval/tool injection is modeled explicitly.

## Privacy

Support configurable transcript storage, retention, visitor identity/IP treatment, lead consent, provider disclosures, data export/erasure, and WordPress personal data exporter/eraser integration. Analytics collects only necessary product metrics with bounded raw-event retention.

## Extensibility/agency

Provide stable registries/hooks/contracts for providers, vector stores, sources, chunk processing, retrieval/context, actions, responses, leads, and analytics where concrete extension need exists. Agency targets include multiple bots, cloning, config import/export, presets, secure cross-site embed, and optional white label. Licensing/payment infrastructure is out of scope unless separately approved.

## Milestone program

M00 Discovery/Research/Master Specification
M01 Foundation/Tooling
M02 Database/Migrations/Repositories
M03 Providers/Credentials
M04 WordPress Knowledge
M05 File Ingestion
M06 WooCommerce Knowledge
M07 Normalize/Chunk/Dedup/Index
M08 Embeddings/Vector Stores
M09 Jobs/Sync/Recovery
M10 Hybrid Retrieval/Reranking
M11 RAG/Chat/Grounding/Citations/Memory/Streaming
M12 Admin Onboarding/Bots/Providers
M13 Knowledge UI/Playground/Debugger
M14 Widget/Customizer
M15 Rules/RTL/Accessibility
M16 Conversations/Leads/Feedback/Forms
M17 Human Handoff
M18 WooCommerce Conversational Commerce
M19 Actions/Abilities/MCP
M20 Vision/Audio/Voice
M21 Analytics/Observability/Evals
M22 Security/Privacy/Hardening
M23 Agency/APIs/Integrations
M24 Performance/Compatibility/Packaging/Final Audit

Milestones may be split into multiple plans, but unrelated milestones should not be merged for speed.

## Verification policy

Every production behavior follows Superpowers TDD unless the TDD skill explicitly allows an approved exception. Completion requires fresh evidence appropriate to the claim: unit/integration tests, static analysis, lint/typecheck/build, WordPress activation/migration tests, E2E/visual/a11y checks, provider/vector contracts, security tests, and release validation where applicable. External paid-provider tests are opt-in and credential-gated.

## Repository/state recovery

Git plus persisted docs are authoritative. `docs/progress/STATUS.md` records current task/phase/verified commit/next action. Milestone docs contain test/review/verification evidence. After compaction or restart, recover from Git status/log, approved spec, plan, Superpowers ledger, status file, milestone file, and fresh tests; never guess from chat memory.

## Approval boundary

This document is the master product/architecture approval. Milestone plans may make non-destructive implementation rulings within it and record them in DECISIONS.md. A genuinely new architecture/product direction outside this envelope requires a new design gate.
