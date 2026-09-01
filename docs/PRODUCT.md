# WP RAG AI Chatbot — Product Definition

Status: APPROVED MASTER DIRECTION / M00 WRITTEN SPEC REVIEW

## Vision

WP RAG AI Chatbot is a WordPress-native AI chatbot, RAG, customer-support, WooCommerce conversational-commerce, and safe AI-actions platform. The server runtime remains PHP/WordPress. JavaScript/TypeScript is permitted for compiled admin and widget applications, but Node.js, Python, LangChain, LangGraph, Redis, RabbitMQ, Kafka, or any external service must not become mandatory runtime infrastructure.

## Product identity

The plugin differentiates on production-grade retrieval transparency and safety rather than simply exposing a floating LLM widget. Its defining product traits are:

- WordPress-native local-first operation with optional external services.
- Direct OpenAI and OpenRouter provider support plus WordPress AI Client compatibility.
- Replaceable vector stores with a modest-installation local WordPress option.
- Structure-aware ingestion, incremental indexing, semantic + lexical hybrid retrieval, optional reranking, deterministic grounding, and validated citations.
- A first-class RAG debugger and saved evaluation/regression framework.
- WooCommerce product knowledge with dynamic transactional values fetched at action time.
- Secure action/tool calling with server-side authorization, auditing, and risk classification.
- Conversations, leads, forms, feedback, live human handoff, analytics, privacy controls, agency features, and developer extension points.

## Primary users

1. WordPress site owners who need a support or knowledge chatbot without maintaining an external backend.
2. WooCommerce stores that need grounded product discovery and safe conversational commerce.
3. Agencies that manage multiple bots/sites and require cloning, export/import, presets, embeds, and optional white label.
4. Developers who need provider/vector/source/action extension interfaces, hooks, REST APIs, WordPress Abilities, and optional MCP interoperability.

## Non-goals

- Building a mandatory SaaS backend.
- Building an unrestricted autonomous WordPress administrator for anonymous visitors.
- Treating brute-force MySQL/PHP vector search as equivalent to a dedicated vector database at large scale.
- Creating a full enterprise form-builder, CRM, ticketing suite, or workflow engine before the chatbot/RAG core is correct.
- Requiring WordPress 7.0 solely for AI provider communication.
- Hard-coding model names across the application.

## Proposed compatibility baseline

- WordPress: 6.9+.
- PHP: 8.2+.
- WordPress 7.0+: enhanced AI Client/Connectors integration path.
- WooCommerce: optional; supported version matrix finalized during M06/M24.
- Database: the site's MySQL/MariaDB.
- Node.js: build/development only.

The WordPress minimum is intentionally proposed as 6.9 because the Abilities API is available there, while WordPress 7.0 adds the AI Client and Connectors APIs. This decision remains subject to implementation-time compatibility testing.

## Grounding modes

- Strict Knowledge Only — deterministic no-answer when approved retrieved evidence is insufficient.
- Knowledge Preferred — retrieved knowledge is preferred while general model reasoning may be permitted.
- General Assistant — general model behavior is explicitly enabled and RAG may supplement it.

## Release definition

The product is not complete until M24 final audit passes. Each prior milestone is a verified vertical slice, not a claim that the full plugin is release-ready.
