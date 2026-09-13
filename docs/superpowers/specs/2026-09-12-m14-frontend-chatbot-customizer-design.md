# M14 Frontend Chatbot / Customizer Design

Status: AUTO-APPROVED under `docs/AUTONOMOUS-DEVELOPMENT.md`.

## Goal

Deliver an embeddable, mobile-friendly customer-facing chatbot launcher/panel plus an administrator appearance customizer whose live preview and runtime widget consume the same normalized appearance schema.

## Existing production authorities to reuse

- `src/Chat/ChatOrchestrator.php` — production non-streaming chat orchestration.
- `src/Chat/Streaming/StreamingChatOrchestrator.php` and streaming DTOs — production streaming contract where provider capability permits it.
- `src/Chat/ChatRequest.php`, `ChatRequestPolicy.php`, `ChatAccessContext.php` — bounded request and access policy primitives.
- Existing M10/M11 retrieval, grounding, prompt, memory, citation, provider and vector-store authorities.
- Existing bot persistence/repositories. No frontend request may choose credentials, provider secrets, arbitrary models, embedding configuration, vector-store configuration, or retrieval limits.

## Architecture

### 1. Shared appearance domain

Introduce one PHP/TypeScript-compatible normalized appearance contract with conservative defaults. Runtime widget configuration and administrator live preview must project this same schema. Initial fields cover primary color, color mode (`light|dark|system`), launcher style/position, panel size, radius, typography, bot/user bubble presentation, logo/avatar references, and welcome copy. Validation must bound strings/numeric values and reject unknown or unsafe values rather than passing arbitrary CSS through.

Persist appearance as bot-scoped configuration through the repository's established settings/persistence mechanism. Do not expose provider credentials or unrestricted bot records to the browser.

### 2. Public-safe widget configuration

Add a public projection keyed by a stable bot/embed identifier. It returns only the fields needed to mount the widget: public bot identity, welcome copy, normalized appearance, endpoint/runtime metadata, and capabilities. Inactive/unknown bots fail closed. Secrets and internal retrieval/provider configuration are excluded.

### 3. Public chat execution

Add a narrowly-scoped public chat transport that validates a bounded question and conversation identifier, resolves all provider/model/retrieval authority from persisted server-side bot configuration, applies existing chat request/access policies plus abuse controls, and delegates to the production M11 chat orchestration exactly once. Streaming uses the existing streaming orchestration when available; otherwise the browser may render a simulated typing experience from the completed response without changing backend semantics.

### 4. Widget runtime

Create a separate public frontend entrypoint from the administrator app. Assets are enqueued only when an embed/shortcode/block/full-screen surface is present or explicitly requested. The widget owns launcher/panel state, conversation rendering, loading/error/retry/copy/optional feedback UI, safe Markdown/link rendering, citation/source disclosure, and responsive/keyboard behavior. Raw model HTML is never trusted.

### 5. Administrator customizer

Extend the existing administrator app with appearance controls and a live preview using the same renderer/schema as the runtime widget. Saving is server-authoritative; preview can use unsaved normalized draft state client-side but cannot bypass validation on persistence.

### 6. Embed surfaces

Support shortcode first as the smallest WordPress-native mount authority, then block/direct embed/full-screen adapters that all emit the same widget mount contract rather than separate UI implementations.

## Security

- No API keys/provider credentials in frontend config, markup, REST responses, logs, or embed code.
- Public chat accepts no provider/model/embedding/vector-store/retrieval-limit override surface.
- Bound question, identifiers, appearance strings, URLs, message/history payloads, and response collections.
- Sanitize Markdown output; raw HTML is escaped/removed. Links use an allow-listed URL scheme and external links use `rel="noopener noreferrer"`.
- Apply CSRF/nonces where WordPress session semantics apply and explicit abuse/rate controls for anonymous/public chat.
- Preserve M11 grounding/citation/security behavior by reuse, not reimplementation.

## Performance

- Public widget has its own bundle and is conditionally loaded.
- Avoid loading the administrator bundle on public pages.
- Avoid duplicate retrieval/generation calls.
- Bound conversation DOM/history projection and citation/source lists.

## Accessibility / responsive behavior

- Launcher and close/send controls are native keyboard-focusable buttons with accessible names.
- Focus moves predictably when panel opens/closes.
- Async answer/error status is announced without excessive chatter.
- Mobile panel fits viewport without horizontal overflow; long code/URLs/content wrap or scroll safely.
- Color choices preserve usable contrast; defaults target WCAG AA-friendly combinations.

## Milestone task order

1. Shared normalized `AppearanceConfig` domain contract + tests.
2. Bot-scoped appearance persistence and public-safe widget configuration projection.
3. Protected admin appearance save/read contracts.
4. Public chat request/runtime composition reusing M11 authorities + abuse controls.
5. Conditional public asset/bootstrap/shortcode mount.
6. Launcher/panel conversation UI with loading/error/retry/copy and safe Markdown/links/citations.
7. Streaming/simulated typing integration with existing streaming authority.
8. Administrator customizer/live preview using the shared renderer/schema.
9. Block/direct embed/full-screen adapters.
10. Integration, real WordPress smoke, accessibility/mobile/performance/security review and closeout.

## TDD / verification

Behavior changes use genuine RED -> GREEN chronology. Permanent gates remain PHP quality, JS quality, package and WordPress smoke, with focused PHPUnit/Jest/TypeScript/ESLint/build verification. Representative public routes/embed surfaces must receive real WordPress smoke before milestone closeout.
