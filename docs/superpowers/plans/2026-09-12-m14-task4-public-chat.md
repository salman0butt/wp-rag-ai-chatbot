# M14 Task 4 — Public Chat Runtime Plan

Status: IN PROGRESS on `feat/m14-frontend-chatbot-customizer` / PR #19.

## Goal

Expose the existing M11 chat/retrieval pipeline to the public widget through a narrow WordPress-facing boundary without duplicating retrieval, grounding, prompt construction, memory, citations, provider selection, embedding selection, or vector-store selection.

Public callers may provide only bounded public interaction data. Runtime authority stays server-side.

## Recovered production authorities

- `ChatRequest` is the internal M11 request value object and still contains server-authoritative model/grounding/output-token fields.
- `ChatRequestPolicy` performs bounded query preprocessing and provider availability checks before paid generation.
- `ChatOrchestrator` owns the existing M11 memory -> hybrid retrieval -> grounding -> prompt -> generation -> citation validation -> persistence flow.
- `PlaygroundExecutorResolver` / `PlaygroundChatGraphResolver` demonstrate the existing production composition path used by the administrator Playground without duplicating the M11 graph.
- M14 Task 2 already provides a public-safe bot/widget lookup boundary and rejects missing/disabled bots.

## Public contract

The public HTTP request must not map directly onto `ChatRequest`.

Introduce a separate bounded public request value object with only:

- `bot_id` — required bounded bot identifier;
- `question` — required bounded UTF-8 question;
- `conversation_id` — optional bounded opaque conversation identifier.

Reject unknown keys so callers cannot smuggle provider/model/grounding/output-token/embedding/vector-store/retrieval controls through the public boundary.

## Subunits

### 4A — public request contract

RED first for a missing `PublicChatRequest` contract. Specify strict key allow-listing, trimming, UTF-8/byte bounds, bot ID validation, optional bounded conversation ID, and rejection of provider/model/retrieval override keys.

GREEN with a value object only; no WordPress, provider, retrieval, or persistence work.

### 4B — abuse-control boundary

Introduce a deterministic public abuse-control contract before expensive runtime work. It must support a bounded per-bot/client request budget without storing prompt text or secrets. WordPress-specific storage/key derivation belongs behind this boundary.

### 4C — persisted runtime resolution

Resolve the enabled bot and all model/provider/retrieval authority from persisted server-side state. Reuse existing bot/provider/retrieval/vector-store composition. Do not accept source/collection/provider/model/embedding/vector-store/retrieval-limit values from public input.

If an existing persisted bot-to-knowledge binding is absent, add the smallest server-side binding authority required by the product model rather than falling back to caller-supplied retrieval scope.

### 4D — production chat handler

Map `PublicChatRequest` plus trusted runtime/access scope into one internal `ChatRequest`, invoke the existing M11 graph exactly once, and project a public-safe response containing only answer/conversation/citation data required by the widget.

### 4E — WordPress REST adapter

Register the public route with the established namespace/version conventions. Parse only through `PublicChatRequest`, apply abuse control before expensive provider/retrieval work, delegate once to the production handler, and return stable non-sensitive errors.

### 4F — integration/review evidence

Add representative WordPress smoke/integration coverage for allowed public access, disabled/missing bot behavior, malformed/unknown input, abuse-limit denial, and no request-level runtime override surface. Run correctness, security, performance, architecture/duplication, and public-response privacy review.

## TDD and verification

Each behavior subunit follows test-only commit -> exact-SHA CI -> genuine RED confirmation -> implementation -> exact-SHA GREEN. Preserve any lint/static/infrastructure failures as NOT RED/NOT GREEN. Permanent gates are `php-quality`, `js-quality`, `package`, and `wordpress-smoke` unless repository instructions change.
