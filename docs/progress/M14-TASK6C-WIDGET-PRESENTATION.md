# M14 Task 6C — Safe Widget Presentation and Task 6 Closeout

Status: **COMPLETE**

## Scope

Task 6C closes the non-streaming public widget conversation surface without introducing a second chat/RAG/runtime implementation. It consumes only the established Task 4 public response DTO and keeps the Task 4/M10/M11 production authority server-side.

Verified behavior:

- assistant and citation text render through DOM `textContent`, never trusted/raw model HTML;
- copy controls copy only the rendered assistant text and have stable accessible names;
- citation links are created only for allow-listed `http:`/`https:` URLs;
- external citation links use `target="_blank"` plus `rel="noopener noreferrer"`;
- unsafe/malformed citation URLs degrade to plain text;
- public citations are capped at 8 before rendering and use native disclosure/list semantics;
- rendered transcript history is capped at 40 top-level message entries;
- Task 6B single-flight/loading/error/retry behavior remains intact;
- the browser accepts the server's nullable `conversation_id` contract and omits the identifier from later requests when no persisted conversation exists;
- responsive/mobile shell behavior, native controls, dialog naming, Escape dismissal, and focus restoration remain intact from Task 6A.

## TDD Chronology

### Safe text, copy, citation and link presentation

The Task 6C presentation implementation was already present on the active branch when this autonomous run recovered it. The implementation uses `textContent`, a bounded citation projection, an allow-listed URL parser, native disclosure/list semantics, and safe external-link attributes.

- `9795d5359b13508a5ca35de0d736d52cf0d571b3` — implementation checkpoint for safe widget copy/citation presentation.
- `4758b0899538b5c2844a1611c0efae4dad74b04b` / CI `34727755857` — **NOT GREEN**. Three Prettier findings in `src-js/widget-runtime.ts` stopped JavaScript verification before Jest.
- `a7860a56ae5c40998ed194da5954166c3cb9f8e7` — formatting-only repair. JavaScript verification subsequently reached and passed the presentation suite; later Task 6C checkpoints below provide the authoritative all-gates evidence.

### Finite rendered transcript history

Fallback performance review found that the presentation DOM still grew without a finite bound.

- `947fa6beed97bb363cbb3163d88e65b37f5ca5e9` / CI `34728917027` — **RED**. Lint/typecheck passed and Jest reached only the new history-bound assertion; 25 completed exchanges produced 50 rendered entries instead of the required maximum of 40.
- `f985e4f9cd117c7e931d3a31c85b58e88016d874` / CI `34729000652` — **GREEN** across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`. The runtime removes oldest top-level transcript entries after the finite limit is exceeded.

### Review-discovered nullable conversation-ID integration mismatch

Closeout review compared the browser parser to the actual PHP public contract. `PublicChatResponse` exposes `?string $conversation_id`, and `PublicChatRestResource` serializes that nullable value directly. The browser parser incorrectly required a string, so a legitimate stateless success response would be treated as malformed and shown as an error.

- `de6dc1860fe0225699d50441e39622ae801037bd` / CI `34729171223` — **RED**. Package passed; JavaScript lint and typecheck passed; Jest ran 41 suites with 40 suites passing and only `accepts a successful response without a persisted conversation id` failing (`"Stateless answer"` expected, no assistant message rendered).
- `ddd4de2e1cd1af2ada84a0f703226377c958e030` / CI `34729280110` — **GREEN** across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`. The browser success DTO now accepts `string | null`; a null identifier remains null and therefore is not sent on the next public request.

## Review

Independent reviewer transport was unavailable in this connector-only scheduled run. The repository-approved scoped fallback review covered correctness, security, performance, accessibility, architecture/duplication, and integration with the actual Task 4 PHP DTOs.

Resolved findings:

- **Important — performance:** unbounded rendered transcript DOM. Resolved with a tested 40-entry finite cap.
- **Important — correctness/integration:** the browser rejected the server-authorized nullable `conversation_id`. Resolved by aligning the TypeScript success projection with the actual nullable PHP contract.

Final Task 6 review state: **0 Critical / 0 Important unresolved**.

### Security / architecture conclusions

- Model/user/citation strings remain data, not HTML.
- Unsafe citation schemes cannot become anchors.
- Unknown response fields are ignored.
- Public requests remain closed to `bot_id`, `question`, and optional server-issued `conversation_id`.
- No provider/model/credential/embedding/vector-store/retrieval override surface was introduced.
- The widget continues to call the existing Task 4 public REST authority; no semantic/lexical retrieval, fusion, reranking, grounding, prompts, memory, citation generation, provider selection, embedding selection, or vector-store selection is duplicated in the browser.

### Performance / accessibility conclusions

- one request in flight per mounted widget;
- at most 8 citations parsed for one response;
- at most 40 top-level transcript entries retained;
- public assets remain conditional from Task 5;
- native buttons/form/details/summary stay keyboard-operable;
- status/messages use polite live regions;
- launcher/panel focus and Escape behavior from Task 6A remain unchanged;
- the existing viewport-bounded responsive CSS continues to prevent panel/page overflow.

## Final Verification

Final Task 6 implementation head before durable status-only commits: `ddd4de2e1cd1af2ada84a0f703226377c958e030`.

Exact-head CI `34729280110`: **GREEN** for:

- `php-quality`;
- `js-quality`;
- `package`;
- `wordpress-smoke`.

Task 6A evidence: `docs/progress/M14-TASK6A-WIDGET-SHELL.md`.
Task 6B evidence: `docs/progress/M14-TASK6B-NONSTREAMING-CONVERSATION.md`.

## Next Unfinished Unit

Task 6 is complete. Continue M14 at **Task 7 — streaming/simulated typing integration with the existing production streaming authority**. Preserve Task 4 server-owned runtime/provider authority and reuse the Task 6 DOM/presentation seams rather than creating a second widget implementation.
