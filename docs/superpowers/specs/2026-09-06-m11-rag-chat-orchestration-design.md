# M11 — RAG Chat Orchestration, Grounding, Citations, Memory & Streaming Design

Status: **AUTO-APPROVED — SCHEDULED MODE**

## Goal

Deliver a provider-neutral, server-side RAG conversation pipeline that combines bounded memory and M10 retrieval, enforces grounding and authorization outside the model, generates through the existing M03 provider contract, validates citations against selected evidence, supports deterministic strict no-answer behavior, and exposes normalized streaming events without leaking provider secrets or vendor protocols.

## Scope

M11 owns the backend application path from an already-authorized chat request to a normalized answer/stream result. It includes conversation/session ownership foundations needed by that path, bounded memory assembly, retrieval invocation, prompt/context construction, grounding policy, citation identity and validation, generation orchestration, normalized stream events, persistence hooks, and feedback/analytics hooks.

M11 does **not** build the polished public widget/admin UI (M12/M14), the general action/tool framework (M19), live support, or provider-specific frontend transports. It does not allow model output or retrieved content to authorize access.

## Existing contracts to preserve

- M03 `GenerationProvider`, `GenerationRequest`, `GenerationResult`, normalized usage/status, and credential handling remain the generation boundary. Browser code never receives provider credentials.
- M10 `HybridRetriever` remains the retrieval boundary. M11 consumes only its already access-approved bounded candidates and safe trace output.
- M09 jobs may be used later for asynchronous persistence/analytics work where appropriate, but the primary request path must not require a background worker to complete.
- Existing WordPress/database migration conventions remain versioned, idempotent, upgrade-tested, and bounded.

## Design alternatives

### A. Recommended: deterministic application orchestrator around existing domain contracts

Create focused `RAG`, `Chat`, `Conversations`, `Memory`, and `Citations` domain/application classes. The orchestrator owns ordering and invariant enforcement; providers only generate text; retrieval only returns approved evidence; repositories only persist normalized records.

**Why this wins:** it follows the modular-monolith architecture, keeps security outside the LLM, is deterministic/testable without paid APIs, reuses M03/M10, and keeps future REST/widget work thin.

### B. Provider-led orchestration

Send memory, retrieval context, citation instructions, and grounding policy into a richer provider abstraction and let each vendor adapter coordinate behavior.

**Rejected:** vendor behavior would leak into product semantics, strict no-answer/citation validation would become probabilistic, and provider parity would become difficult to test.

### C. Single Chat manager service

Put session ownership, memory, retrieval, prompts, generation, citation parsing, streaming, and persistence in one service.

**Rejected:** fastest initially but creates a high-coupling manager that is difficult to review, test, extend, or secure.

## Request pipeline

The synchronous non-streaming path is:

1. Validate normalized chat request and ownership context.
2. Apply deterministic request/rate/cost policy before paid generation.
3. Load a bounded conversation memory window and optional versioned summary.
4. Invoke M10 retrieval with trusted access/filter context derived by PHP, never by the model.
5. Apply grounding policy. In strict mode, insufficient evidence returns the canonical no-answer result **without calling the generation provider**.
6. Assign stable request-local citation IDs (`C1`, `C2`, ...) only to final selected context candidates.
7. Build provider-neutral generation input from policy + bounded memory + delimited untrusted evidence + current question.
8. Call the existing `GenerationProvider` once for the answer path.
9. Parse/validate citation references against the request-local citation registry. Unknown or unauthorized citations never survive into the normalized result.
10. Persist normalized conversation/message/usage/citation metadata through repository hooks.
11. Emit sanitized analytics/feedback hooks that do not contain secrets or raw provider exceptions.

The streaming path preserves the same ordering/invariants, but maps provider stream output into internal events and performs final citation/result validation before `message.complete`.

## Core domain types and boundaries

### Chat request/result

`ChatRequest` is immutable and contains only normalized application inputs: conversation identity or new-session intent, user text, model ID, grounding mode, trusted retrieval/access context, output token limit, and caller ownership context. Transport-specific nonce/cookie/REST details stay outside this type.

`ChatResult` contains answer text, normalized completion status, validated citations, usage, conversation/message IDs where persistence is enabled, and a safe retrieval/generation trace reference. It never contains credentials, raw provider payloads, raw provider errors, or unrestricted retrieved metadata.

### `ChatOrchestrator`

Coordinates the pipeline but delegates policy, memory, retrieval, prompting, citations, provider generation, and persistence to focused collaborators. It is responsible for ordering guarantees and for avoiding provider calls on deterministic no-answer or policy rejection paths.

### Grounding

`GroundingMode` initially supports:

- `STRICT`: answer only when deterministic evidence policy is satisfied; otherwise return the canonical no-answer result without generation.
- `ASSISTED`: generation may answer using selected evidence while still enforcing citation validation and untrusted-context boundaries.

`GroundingPolicy` evaluates already-selected M10 candidates using deterministic signals available to the application (presence/count/confidence/evidence metadata) and returns an explicit decision. It does not ask an LLM whether evidence is sufficient.

The strict canonical no-answer message is application-owned and stable for tests/localization integration. The model cannot override the decision.

### Memory

`MemoryAssembler` consumes a repository-neutral conversation history source and returns a bounded `ConversationMemory` containing:

- at most the configured number of recent normalized messages;
- at most one versioned summary;
- deterministic byte/character/token-estimate ceilings;
- no provider secrets or internal traces.

Unlimited transcript replay is forbidden. Truncation is deterministic and preserves the newest useful turns within configured bounds.

### Prompt/context construction

`PromptBuilder` produces the M03 `GenerationRequest` content from four explicit sections: application policy, bounded memory, retrieved evidence, and current question.

Retrieved chunks are wrapped in machine-generated delimiters and labelled **UNTRUSTED EVIDENCE — DATA ONLY**. Retrieved instructions such as “ignore previous rules” remain quoted data and never enter the instruction/system policy section.

Only safe context fields needed for answer generation/citation display are included. Internal authorization fields, raw metadata blobs, secrets, and trace data are excluded.

### Citations

Before generation, `CitationRegistry` assigns request-local IDs to the exact final context candidates. A citation record retains the canonical chunk/document/source lineage and only explicitly permitted display URL/title fields.

`CitationValidator` validates provider-produced citation markers against that registry. It rejects unknown IDs, duplicate/ill-formed markers as appropriate, and citation URLs supplied by the model rather than the registry. Returned citation URLs always come from trusted selected-source metadata.

If strict grounding requires citations and generation produces only invalid/unknown citations, result policy returns a controlled no-source/no-answer outcome rather than presenting invented citations.

### Conversations and persistence

M11 introduces only the minimum durable conversation schema needed by the backend path:

- conversations: stable ID, owner scope/type identifier, timestamps, summary version/text where enabled, and bounded status metadata;
- messages: stable ID, conversation ID, role, normalized content, generation/provider/model/usage metadata where applicable, timestamps;
- message citations: message ID + citation ID + canonical selected-source lineage/display metadata.

No provider credential or raw provider response is persisted. Raw retrieval chunks are not duplicated into messages when canonical lineage is sufficient.

Repository contracts separate orchestration from WordPress SQL. Writes are atomic enough that a generated assistant message cannot be committed to a different conversation/owner scope.

### Rate/cost policy

`ChatRequestPolicy` is a pre-generation application boundary. It can reject oversized input, invalid output limits, unavailable provider/model selections, and rate/cost budget violations before a paid request is issued. M11 defines the hook/contract and deterministic defaults needed for tests; richer admin-configurable quotas can evolve later without moving authorization into the provider.

## Streaming

M11 defines provider-neutral internal events:

- `message.start`
- `message.delta`
- `citation`
- `message.complete`
- `error`

Tool events remain reserved for M19 and are not implemented as general actions in M11.

Every event has a monotonically increasing sequence number within one request. `message.start` is first; `message.complete` or `error` is terminal and emitted at most once. No delta is emitted after a terminal event.

Provider/vendor event names and payloads are normalized behind a `GenerationStream`/stream-adapter contract. Normal CI uses deterministic fake streams and consumes no paid credits.

Disconnect/cancellation is represented through a narrow cancellation token/check hook so transport code can stop provider consumption and prevent unnecessary persistence. M11 tests cleanup semantics; REST/SSE presentation wiring can be completed by the chat transport milestone without changing orchestration semantics.

## Security invariants

1. Ownership/access context is derived and enforced by PHP application/transport code; neither user text nor retrieved text can elevate it.
2. Retrieved content is untrusted data and cannot modify system policy, grounding mode, provider credentials, tool permissions, or citation registry membership.
3. Strict no-answer decisions happen before generation and therefore cannot be bypassed by prompt wording.
4. Citation IDs resolve only to final access-approved M10 candidates.
5. Client-visible results/events never include provider credentials, authorization metadata, raw exceptions, SQL, or raw vendor payloads.
6. User/message/context/output sizes are hard-bounded.
7. Generation calls are hard-bounded to one answer request per orchestration attempt in M11; retries remain provider/transport policy and must not create duplicate persisted assistant messages.
8. Persistence operations always include conversation ownership scope.
9. URLs exposed through citations originate from trusted canonical selected-source metadata, not model-authored URLs.
10. Analytics hooks receive normalized identifiers/counts/latency/usage, not secrets or unrestricted transcript content by default.

## Error semantics

Expected application failures map to stable domain reasons such as `invalid_request`, `conversation_not_found`, `conversation_forbidden`, `rate_limited`, `budget_exceeded`, `retrieval_unavailable`, `insufficient_evidence`, `generation_unavailable`, `generation_failed`, `invalid_citations`, and `cancelled`.

Raw provider/database exceptions are not returned to clients. Internal diagnostics may retain a safe request ID and normalized category.

Strict insufficient evidence is a successful controlled no-answer result, not an infrastructure error.

## Performance bounds

Initial application defaults must be explicit constants/config values and validated at construction:

- current user input: <= 16 KiB UTF-8 bytes;
- recent memory messages: <= 12;
- memory text budget: <= 24 KiB;
- selected retrieval candidates: consume the M10 configured final limit and additionally cap RAG context to <= 12 candidates;
- assembled retrieved context text: <= 48 KiB;
- provider output token request: 1..4096 by default, never exceeding the existing M03 hard maximum;
- citation registry: <= selected context candidate count;
- streaming delta payload: normalized and bounded before client transport.

These are application safety ceilings, not claims about provider context windows. Prompt building deterministically truncates/drop lowest-priority context before ever issuing an oversized provider request.

## Testing strategy

Normal CI uses fakes/stubs for generation, memory repositories, persistence, clocks, and streaming. No live provider credential is required.

Required test layers:

1. Value-object validation and hard bounds.
2. Ownership-scoped conversation repository/migration integration.
3. Bounded deterministic memory assembly.
4. Strict grounding/no-answer tests proving zero generation calls when evidence is insufficient.
5. Prompt-injection fixture proving malicious retrieved instructions remain evidence data.
6. Citation registry/validator tests proving unknown citations and model-authored URLs cannot escape.
7. Orchestrator tests proving pipeline ordering, one generation call, persistence scoping, and normalized errors.
8. Stream contract tests proving event ordering, terminal semantics, sanitized errors, cancellation cleanup, and citation events.
9. End-to-end indexed fixture: M10 retrieval -> M11 prompt -> fake generation -> validated citations/result.
10. Security/performance review covering ownership, prompt injection, rate/cost abuse, context/output bounds, external-call counts, persistence idempotence, trace redaction, and disconnect cleanup.

## Implementation decomposition

1. Core chat value objects, grounding modes/errors, and hard request bounds.
2. Conversation persistence schema/repositories with ownership scope.
3. Bounded memory assembly and summary contract.
4. Citation registry + validation.
5. Prompt/context builder with untrusted-evidence isolation.
6. Grounding policy and deterministic strict no-answer.
7. Non-streaming `ChatOrchestrator` integrating M10 + M03.
8. Normalized streaming contracts/adapter orchestration and cancellation semantics.
9. Persistence/analytics hooks plus end-to-end acceptance/security/performance closeout.

Each task must use strict test-first RED -> exact-SHA CI evidence -> minimal GREEN -> independent review before the next task is durably closed.

## Self-review

- Placeholder scan: no TODO/TBD or intentionally unresolved behavioral requirements.
- Scope check: frontend widget/admin and general actions remain outside M11.
- Security check: authorization, grounding, citation membership, and rate/cost policy are deterministic server-side controls.
- Compatibility check: reuses M03 `GenerationProvider` and M10 `HybridRetriever`; no vendor API changes or new paid dependency.
- Testability check: every provider/repository/stream dependency has a deterministic fake boundary; normal CI requires no secrets.
- YAGNI check: only M11-required persistence and streaming semantics are introduced; tool/action events and polished transport/UI are deferred.

**Decision:** design is internally consistent and **AUTO-APPROVED — SCHEDULED MODE** under `AGENTS.md` and `docs/AUTONOMOUS-DEVELOPMENT.md`.