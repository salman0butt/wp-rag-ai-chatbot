# M14 Task 6A — Floating Widget Shell Evidence

Status: **COMPLETE**

## Scope

Task 6A establishes the deterministic public floating launcher/panel shell on the existing Task 5 browser bootstrap contract. It does not add a second chat, retrieval, provider, embedding, vector-store, prompt, citation, or runtime-authority path.

The verified shell now provides:

- matching bootstrap-config mount discovery;
- idempotent rendering when the widget entry executes more than once;
- native accessible launcher and close buttons;
- launcher open state with predictable focus transfer into the panel;
- close-button and Escape dismissal with focus restoration to the launcher;
- bounded appearance projection using an explicit finite allow-list and safe defaults matching the server `AppearanceConfig` contract;
- viewport-bounded desktop/mobile shell styling;
- light/dark/system presentation hooks;
- visible control labels and a stable accessible dialog name;
- no arbitrary CSS/class/HTML projection from public configuration.

## TDD Chronology

### Open / focus behavior

- `c9f5221cb4fb04a3b07f275f1bce5020ec8b5480` / CI `34723561305` — **NOT RED**. WordPress ESLint rejected direct `document.activeElement` access before Jest reached the intended behavior assertion.
- `5d96df7b9637f80b8f49ee98017a70ee02b20946` / CI `34723630420` — **RED**. Lint/typecheck passed and Jest failed because launcher activation left `aria-expanded="false"`.
- `e90026469012064e53e878232dc8efbe6c156928` / CI `34723719103` — **GREEN** across all permanent jobs. Launcher activation exposes the panel and focuses its close control.

### Close / focus restoration

- `6a6a5e4ca6fe289fe5ce57c448f3d5dad7bdb01f` / CI `34723893312` — **RED**. Jest reached the intended assertion and showed the close control did not collapse the panel.
- `d64d518473c8791a07d8a4d1bcc9e9685f14dac0` / CI `34723959061` — **GREEN**. Close collapses the panel and restores launcher focus.

### Idempotent mount

- `89d1534cf77bae6d8c6506aba2117a6b4298998b` / CI `34724118298` — **RED**. The second widget entry execution produced two launchers.
- `cc6c7dd5bcb86bb5047e37047e952852cbaa6903` / CI `34724185942` — **GREEN**. A deterministic mount marker prevents duplicate controls.

### Escape behavior

- `44ea5d840ee856b7008fd7c1855f5200f7ff4d7f` / CI `34724365056` — **RED**. Escape left the open panel expanded.
- `d53f9dd05bf500223b5bf5a24e8cfe8f6af7f702` / CI `34724452058` — **GREEN**. Escape reuses the same close path and restores launcher focus.

### Bounded appearance and responsive shell

- `afb525c78bf9a4faa5a83f7a8bc4d192cd8072ae` / CI `34724653470` — **RED**. The normalized appearance config was not projected into presentation tokens.
- `12b25506fd122cc3b83b13eb3df46a56106343d4` — implementation of explicit finite appearance validation/default mapping.
- `fa19c2a03578d01bc660d6f75c612d120a7674eb` / CI `34724793507` — **GREEN** across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`; includes viewport-bounded responsive CSS.

### Review-driven accessibility remediation

Repository-approved fallback review found one **Important** issue: the launcher and close controls were visually blank despite having accessible labels, and the panel itself lacked stable dialog semantics/name.

- `8320fa84a797390260cc981cb6112509972e6be1` / CI `34724956542` — **NOT RED**. Prettier stopped the JS path before Jest because of test formatting.
- `ea52177e22b78a032d669c63cbeb1d70c3a6c135` / CI `34725009158` — **RED**. Lint and typecheck passed; Jest failed because the visible launcher label was empty.
- `772a125fd4cf7beb8705a3eecc2c136d4c9c71a6` / CI `34725063417` — **GREEN** across all permanent jobs. The launcher visibly renders `Chat`, the close control visibly renders `Close`, and the panel has `role="dialog"` plus the bot-scoped accessible name.

## Review

Independent reviewer transport was not available in this connector-only scheduled run. The repository-approved scoped fallback review covered correctness, security, performance, accessibility, and architecture/duplication.

Resolved finding:

- **Important — accessibility/UX:** visually blank launcher/close controls and unnamed dialog. Resolved by the final RED/GREEN cycle above.

Final Task 6A review state: **0 Critical / 0 Important unresolved**.

## Security / Architecture

The browser runtime continues to consume only the Task 5 public-safe bootstrap projection. Client appearance handling recognizes only the finite server-backed fields and safe values; invalid values fall back to deterministic defaults. It does not expose or accept provider credentials, provider/model overrides, embedding/vector-store settings, retrieval limits, raw HTML, or arbitrary CSS.

Task 6A does not execute chat/retrieval/generation and therefore cannot fork the production M10/M11 authority. Task 6B will call the existing Task 4 public chat REST surface only.

## Verification

Final Task 6A implementation head: `772a125fd4cf7beb8705a3eecc2c136d4c9c71a6`.

Exact-head CI `34725063417`: **GREEN** for:

- `php-quality`;
- `js-quality`;
- `package`;
- `wordpress-smoke`.

## Next Unfinished Unit

Task 6 remains in progress. Continue the authoritative plan at **Task 6B — non-streaming conversation submit/loading/error/retry behavior**, reusing the existing public `POST /wp-rag-ai-chatbot/v1/chat` contract and sending only `bot_id`, `question`, and optional `conversation_id`.
