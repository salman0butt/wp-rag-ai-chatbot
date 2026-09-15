# Modern Knowledge and Publish Flow Design

**Status:** APPROVED IN CHAT

**Date:** 2026-09-15

## Goal

Turn the current functional but developer-oriented administration screen into a polished React setup experience that lets a site owner connect Gemini, add knowledge, attach that knowledge to a chatbot, test it, and publish the chatbot on the frontend without manual database values or undocumented identifiers.

## Product flow

The primary path is one guided sequence:

`Provider → Knowledge → Chatbot → Publish`

Existing advanced screens remain available, but the first screen presents the next required action and the current readiness state.

## Current gaps addressed

- The provider catalog and credential form work, but the visual hierarchy is still close to default WordPress admin styling.
- Knowledge is currently inspection-oriented: it lists persisted sources and jobs but has no source-creation wizard.
- Bots can select a provider and model, but the admin flow does not expose the persisted bot retrieval binding required by public chat.
- Public floating, embedded, fullscreen, shortcode, and Gutenberg surfaces already share one runtime, but the UI does not explain or generate the publish instructions.

## Recommended approach

Reuse the existing WordPress React runtime, REST client, provider registry, source normalizers, queue, retrieval binding repository, and public widget runtime. Add only the missing administrator resource boundaries and presentation components. Do not create a second chat client, retrieval pipeline, credential store, or frontend widget runtime.

### Alternatives rejected

1. **CSS-only refresh:** fast, but leaves the owner unable to add knowledge or bind it to a bot.
2. **New external dashboard/service:** creates a second source of truth and violates the local-first WordPress product direction.
3. **Full visual builder and analytics suite:** useful later, but unnecessary for the first usable setup path.

## UX design

### Application shell

- A compact left navigation with four primary destinations: Overview, Knowledge, Chatbots, and Publish/Test.
- A clear page title, short explanation, readiness badge, and one primary action per screen.
- Cards use a light neutral canvas, white surfaces, restrained borders, consistent 12–16px radius, accessible contrast, and responsive single-column fallbacks.
- Keep the app inside the existing `#wp-rag-ai-chatbot-admin` mount and retain WordPress admin compatibility.
- Use semantic headings, labelled controls, live status regions, keyboard focus, and visible validation.

### Overview

Show four compact status cards:

- Provider: Gemini connected or action required.
- Knowledge: source count and indexing state.
- Chatbot: enabled bot count and whether one has a knowledge binding.
- Publish: whether a valid public mount is ready.

The overview includes a “Continue setup” action that routes to the first incomplete step.

### Knowledge

The Knowledge screen receives an “Add knowledge” primary action and a modal or full-width step panel with four bounded source types:

1. **WordPress content** — posts/pages and selected public post types, with optional private-content opt-in remaining off by default.
2. **Manual text** — title plus bounded text area.
3. **FAQ** — repeatable question/answer rows with at least one complete item.
4. **WooCommerce catalog** — all public products or an explicit product-ID selection when WooCommerce is available.

Each successful source creation immediately creates a source-sync job through the existing queue seam. That job reuses the existing source normalizers and enqueues one `index.document` job per normalized document. The source card shows `queued`, `running`, `complete`, or `failed`, progress, retry, and last-safe-error text. The owner can open bounded documents/chunks for inspection.

File ingestion is available through a dedicated upload step. The browser sends the file as multipart form data; the server stores it in a plugin-owned subdirectory under the WordPress uploads directory and records the server-owned path. The browser never supplies a filesystem path.

### Chatbots

The chatbot editor becomes a two-part card:

- Identity and model: name, enabled state, provider, and model.
- Knowledge: one selected source and its server-owned collection, with a clear “Not connected” state.

Saving a bot with a selected knowledge source persists the existing bot retrieval binding. The UI never accepts arbitrary collection IDs without server validation. Existing appearance and display-rule controls remain below the core setup, in collapsible advanced cards.

### Publish/Test

Show a live preview and three publish choices:

- **Floating widget:** shortcode `[wp_rag_ai_chatbot bot="BOT_ID"]` with clear instructions for placing it in a site-wide template or footer hook.
- **Embedded chat:** `[wp_rag_ai_chatbot_embed bot="BOT_ID"]` or the Gutenberg “RAG AI Chatbot” block.
- **Fullscreen chat:** `[wp_rag_ai_chatbot_fullscreen bot="BOT_ID"]`.

The UI provides copy buttons, a short placement explanation, and a readiness warning if the selected bot is disabled, missing a provider model, missing knowledge, or has no completed index. Copying instructions is local-only and does not transmit secrets.

The existing admin Playground remains the “Test Chat” surface and continues to use the production retrieval/generation composition exactly once.

## Server contracts

### Knowledge source administration

Add capability-protected routes under the existing `wp-rag-ai-chatbot/v1` namespace:

- `POST /admin/knowledge/sources` — create one validated source from an allow-listed source type and configuration. JSON handles WordPress, manual text, FAQ, and WooCommerce sources; multipart form data handles files.

The create resource owns:

- stable source-key generation;
- source-type allow-listing;
- source-specific config validation;
- WordPress upload validation and plugin-owned upload storage for file sources;
- safe title/URL normalization;
- persisted `active` status;
- immediate enqueue of a bounded source-sync job that reuses the existing normalizers and `index.document` queue handler.

The response projects only the bounded source DTO and job DTO. It never returns credentials, raw file paths, arbitrary source config, or raw exceptions.

### Bot retrieval administration

Add capability-protected routes under the existing bot boundary:

- `GET /admin/bots/{id}/retrieval` — return the selected source title and collection readiness, never raw arbitrary configuration.
- `PUT /admin/bots/{id}/retrieval` — validate the bot, source, and collection on the server before saving the existing `BotRetrievalBinding`.
- `DELETE /admin/bots/{id}/retrieval` — clear the binding when the owner intentionally disconnects knowledge.

The public runtime remains unchanged: it resolves the enabled bot and the persisted binding server-side and fails closed when either is missing.

### Publish readiness

The existing readiness response is extended only with bounded counts and booleans needed by the dashboard:

- configured provider;
- model available;
- source count;
- completed index present;
- enabled bot count;
- publishable bot present.

No secret or provider error body crosses this boundary.

## Data flow

1. Provider credential is stored through the existing encrypted credential store.
2. Admin selects an allow-listed model returned by the existing model-readiness route.
3. Source creation persists a typed source record and enqueues the existing queue through a source-sync orchestration job.
4. The source-sync job normalizes documents; the existing document-index handler chunks them, creates embeddings, and updates the existing lexical/vector projections.
5. Bot retrieval save persists the source ID and validated collection ID.
6. The Publish/Test screen reads bounded readiness and emits existing public-safe mount instructions.
7. Frontend chat continues through the existing public REST request and production RAG pipeline.

## Error handling and security

- All new admin routes require `AdminCapability::can_manage`.
- Source types, config keys, collection identifiers, and bot IDs are finite/validated allow-lists.
- File uploads use WordPress upload APIs and the existing document validation policy; clients cannot submit a server path.
- Source creation and binding failures use stable codes and safe messages.
- Failed indexing exposes bounded diagnostic text already approved by the job projection.
- Existing nonce, same-origin, encrypted credential, public-safe widget-config, rate-limit, citation, and fail-closed behavior remain unchanged.
- No automatic frontend injection is enabled unless the site owner explicitly enables it in the Publish screen.

## Testing and acceptance

### PHP

- Unit tests for source-create validation, stable source-key generation, source-type config allow-lists, file-upload boundary, and retrieval-binding validation.
- REST tests for administrator permission checks, malformed payloads, safe DTO projection, and stable errors.
- Existing PHPUnit, PHPCS, PHPStan, and WordPress smoke suites remain green.

### JavaScript

- Component tests for the shell, readiness cards, source wizard validation, source/job states, bot binding states, publish instructions, and copy-button feedback.
- Responsive/accessibility assertions for labelled controls, keyboard actions, live status, and mobile layout.
- Existing widget, provider, knowledge, bot, and playground tests remain green.

### End-to-end acceptance

1. Save Gemini key.
2. Create a WordPress content or manual-text source.
3. Observe indexing job state and completion.
4. Create or edit a Gemini bot and attach the completed source.
5. Run Test Chat.
6. Render the selected bot through floating shortcode, embedded shortcode, and Gutenberg block.
7. Send a frontend question and verify a grounded answer or a safe unavailable state when prerequisites are intentionally missing.

## Scope boundary

This change does not add billing, multi-bot analytics, external crawling, arbitrary website scraping, or a second vector/retrieval engine. Those remain separate future work.
