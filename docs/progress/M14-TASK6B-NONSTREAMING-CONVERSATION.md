# M14 Task 6B — Non-streaming Conversation Evidence

Status: **COMPLETE**

## Scope

Task 6B adds the bounded browser interaction layer on top of the existing Task 4 public chat REST authority. The browser does not select or override providers, models, embeddings, vector stores, retrieval limits, prompt policy, or credentials.

Verified behavior:

- native accessible textarea and send button;
- whitespace-only submit is a no-op;
- requests use only `bot_id`, `question`, and optional server-issued `conversation_id`;
- one request may be in flight per mounted widget;
- send is disabled while pending and a polite live status announces loading;
- user and assistant messages render with `textContent`, never raw HTML;
- successful responses preserve only the server-issued conversation identifier for later continuity;
- public errors consume the real Task 4 `{ error: { code } }` response envelope, map only stable codes to bounded local copy, and never display arbitrary server/provider messages;
- retry replays the last bounded request without duplicating the visible user message;
- malformed/network failures fail closed through generic local copy;
- the Task 4 server endpoint remains the sole runtime/retrieval/provider authority.

## TDD Chronology

### Native conversation controls

- `b6811a6e14067c6952f5e7ecf417910736d95a20` / CI `34725323120` — **RED**. Jest failed because the textarea/send controls did not exist.
- `796cb635502fe25c897d18f0051e7a4cad20e417` / CI `34725395949` — **GREEN** across all permanent jobs.

### Closed public request DTO

- `433c1d2c96caef62bcea583934510db9672142b7` / CI `34725554084` — **NOT RED**. Prettier stopped JavaScript verification before Jest.
- `362eb2e3aad20529de5aa8806346476f5d94ff18` / CI `34725635959` — **RED**. Lint/typecheck passed; whitespace made no request and the valid-submit assertion failed with zero fetch calls.
- `08997046fb2334c9593a2239b321bb633afea9ce` / CI `34725703214` — **GREEN** across all permanent jobs. The widget posts only the closed DTO to `${restBase}/chat`.

### Single-flight loading behavior

- `68498c2d01356a92c15ee9ebd519bf982280cd2e` / CI `34725923685` — **RED**. Two submissions produced two requests.
- `61617fa47d5fb53449bc6702b0d9c93f499ce2a8` / CI `34725990797` — **GREEN**. Duplicate in-flight submit is blocked, send is disabled, and `role="status" aria-live="polite"` announces `Sending…`.

### Safe success rendering and conversation continuity

- `2824d46c56b6f104c51fd7ca9983d713a53c3279` / CI `34726162944` — **RED**. Jest reached the new test and failed because no transcript existed.
- `246da3813d4a7fdae7afcc91be010fe9aa108a1d` / CI `34726242671` — **NOT GREEN**. The implementation existed, but Prettier blocked JavaScript verification before Jest.
- `2fd1a72be1cabc6a4d1902854b4b4e82bbf92279` / CI `34726352190` — **NOT GREEN**. Formatting passed and Jest reached the test, but the test helper did not settle the full fetch/json/render promise chain.
- `65f6b9f733790aa70ae39c539da7e1ba26cb0b07` / CI `34726426447` — **GREEN** across all permanent jobs. User/model markup-like strings render literally and server-issued conversation continuity is preserved.

### Safe error mapping and bounded retry

- `45d10f754859a101cc1fe353af6b247b8d5783ef` / push CI `34726611942` — **NOT RED**. Prettier stopped JavaScript verification before typecheck/Jest; PHP/package/WordPress smoke remained green.
- `25e96159bc6161206bd9c6d27ebdafd9c86e57d5` / CI `34726821848` — **RED**. Lint/typecheck passed, 39 existing suites passed, and only the new safe-error test failed because no bounded error/retry UI existed.
- `cba5db8eac30ea2df4691ef97d2e038f4aee41f4` / CI `34726908206` — **NOT GREEN**. Error/retry implementation was present, but two Prettier findings stopped JavaScript verification before Jest.
- `730e7330e627c93af814e99ced3287c8c0394cbf` / CI `34726994193` — **GREEN** for the initial local error mapper/retry behavior.

### Review-discovered real REST error-envelope mismatch

A deeper integration review against `PublicChatRestResource` found an **Important** mismatch after the initial Task 6B closeout: the server emits public errors as `{ error: { code } }`, while the browser parser expected a top-level `{ code }`. That would have degraded known public errors to generic copy in production.

- `9c9d012d7afcaf8b9443d35755a1e40d504fb921` / CI `34727235080` — **RED**. Lint/typecheck and the existing JavaScript suite passed; the new real-envelope assertion failed because `rate_limited` nested under `error.code` was not recognized.
- `77b15a55b4ac6383eb0f7dd2faefb8a69d67ad54` / CI `34727350832` — **GREEN** across all permanent jobs. `readError()` now accepts only the real nested `error.code` field and continues to ignore server `message`/provider detail.

## Review

Independent reviewer transport was unavailable in this connector-only scheduled run. The repository-approved fallback review covered correctness, security, performance, accessibility, architecture/duplication, and integration with the actual Task 4 DTOs.

The review discovered one **Important** integration finding after the initial green error/retry implementation: browser error parsing did not match the server's nested `{ error: { code } }` envelope. The strict RED/GREEN repair above resolved it.

Final Task 6B review state: **0 Critical / 0 Important unresolved**.

Key review conclusions:

- request construction is closed and derived only from trusted bootstrap identity plus a server-issued conversation identifier;
- retry cannot inject arbitrary request fields and remains under the same one-in-flight guard;
- response/error strings use DOM `textContent`; raw server `message`, stack, provider payload, and unknown fields are ignored;
- the browser parses the actual Task 4 nested public error envelope and local error copy is bounded (`rate_limited`, `chat_unavailable`, generic fallback);
- loading/error status uses a polite live region and controls remain native keyboard-operable;
- no second production RAG/chat/provider composition was introduced.

## Final Verification

Final Task 6B implementation head: `77b15a55b4ac6383eb0f7dd2faefb8a69d67ad54`.

Exact-head CI `34727350832`: **GREEN** for:

- `php-quality`;
- `js-quality`;
- `package`;
- `wordpress-smoke`.

## Next Unfinished Unit

Task 6 remains open. Continue at **Task 6C — bounded message presentation, copy, safe links/citation-ready surface, finite history, responsive closeout**. Keep `textContent` as the default and construct only allow-listed `http:`/`https:` anchors with safe external-link attributes.
