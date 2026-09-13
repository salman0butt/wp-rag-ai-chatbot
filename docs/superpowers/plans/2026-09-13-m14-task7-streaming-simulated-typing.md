# M14 Task 7 — Streaming / Simulated Typing Integration Plan

Status: **AUTO-APPROVED** under `docs/AUTONOMOUS-DEVELOPMENT.md`.

## Goal

Add progressive answer presentation to the public widget without changing the trusted public chat request contract or duplicating provider/retrieval/runtime authority in the browser.

## Reused Authorities

- `PublicChatRestResource` remains the public abuse-control and REST execution boundary.
- `ProductionPublicChatExecutor` remains the public adapter into the production M11 response path.
- `StreamingChatOrchestrator`, `GenerationStream`, `StreamEvent`, and `StreamEventType` remain the production provider-neutral streaming authority when a transport can safely expose those events.
- The Task 6 widget renderer remains the only public DOM/message presentation implementation.

## Delivery Strategy

The existing WordPress REST public route currently returns one completed bounded response; it is not a chunked/SSE transport. Do not fake provider-level streaming or create a second generation call solely for presentation.

Implement Task 7 in two bounded slices:

1. **Task 7A — simulated progressive presentation over the established completed response**
   - expose a deterministic typing state after the completed public response is accepted;
   - progressively reveal the already-produced answer in bounded text chunks;
   - keep only one backend request and one assistant message container;
   - defer copy/citation controls until the answer is fully revealed;
   - keep the existing 40-entry transcript cap;
   - avoid raw HTML and preserve safe citation/link projection;
   - ensure pending presentation work is cancelled when the panel/widget lifecycle invalidates it;
   - use bounded timers so tests and runtime cannot create unbounded scheduled work.

2. **Task 7B — production-stream capability integration, only if an existing safe public transport seam can expose the existing normalized stream without introducing parallel runtime/provider authority**
   - first recover whether the repository already has a transport-capable stream endpoint/adapter;
   - if such a seam exists, consume only normalized `message.start`, `message.delta`, `citation`, `message.complete`, and stable error events;
   - if no safe WordPress transport seam exists, record simulated progressive presentation as the supported public fallback for this milestone rather than creating a provider-specific/browser-owned streaming path;
   - do not add a second retrieval/generation execution to obtain presentation deltas.

## TDD Order

### 7A.1 Progressive reveal

RED first:
- completed response does not appear all at once;
- typing status is exposed while progressive reveal is active;
- advancing timers reveals the full answer exactly once;
- copy/citations are attached only after completion.

GREEN:
- add one bounded presentation scheduler/helper in the existing widget runtime;
- split by UTF-16-safe string slices or conservative text chunks; never parse/execute answer HTML;
- maximum scheduled ticks must be bounded by an answer-size-derived hard cap.

### 7A.2 Cancellation / stale presentation

RED first:
- closing the panel during simulated typing cancels pending presentation timers;
- reopening does not resume stale pending work or duplicate the assistant message.

GREEN:
- maintain one request-local presentation cancellation handle;
- cancel on panel close and before starting another presentation;
- finish/reset state deterministically.

### 7B.1 Transport decision

Read existing streaming/provider/REST wiring and tests. Add a server/browser contract only if it can delegate to the existing `StreamingChatOrchestrator` and established persisted runtime authorities exactly once. Otherwise document the absence of a safe incremental WordPress transport seam and retain the Task 7A fallback.

## Verification

For every behavior slice:

1. commit/push test-only RED;
2. verify lint/typecheck reach the intended Jest/PHPUnit failure;
3. implement minimum production change;
4. require exact-head CI GREEN for `php-quality`, `js-quality`, `package`, and `wordpress-smoke`;
5. perform scoped correctness, security, performance, accessibility, architecture/duplication review;
6. resolve all Critical/Important findings;
7. update durable progress evidence.

## Security / Architecture Guardrails

- no client provider/model/credential/embedding/vector-store/retrieval override;
- no second RAG/retrieval/generation pipeline;
- no raw provider stream payloads exposed to the browser;
- user/model/citation content remains inert text;
- no additional generation call for visual typing;
- keep request and response collection bounds.

## Accessibility / Performance Guardrails

- avoid announcing every simulated character through live regions;
- expose one concise typing status and one completed answer announcement;
- one timer chain per mounted widget at most;
- bounded chunk/tick count;
- cancellation cleans up pending timer work;
- preserve keyboard/focus behavior from Task 6.
