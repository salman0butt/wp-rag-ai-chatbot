# M14 Task 6 Launcher / Panel UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the accessible, responsive public launcher/panel conversation UI on top of the Task 5 mount/bootstrap contract without adding streaming or a second runtime authority.

**Architecture:** Keep `src-js/widget.ts` as the tiny browser entry and move deterministic widget behavior into focused public-widget modules. The browser discovers server-rendered `.wp-rag-ai-chatbot-widget[data-wp-rag-ai-chatbot-bot]` mounts, resolves only the matching allow-listed bootstrap config from `window.wpRagAiChatbotWidgetConfigs`, and renders launcher/panel state using DOM APIs. Task 6 may call the existing public REST chat endpoint for non-streaming responses, but it must not reimplement provider/retrieval/generation policy; streaming/simulated typing remains Task 7.

**Tech Stack:** TypeScript, Jest via `@wordpress/scripts`, browser DOM APIs, dedicated `src-js/widget.ts` build entry, existing PHP `WidgetConfig`/Task 5 bootstrap contract.

**Spec:** `docs/superpowers/specs/2026-09-12-m14-frontend-chatbot-customizer-design.md`

## Global Constraints

- Reuse the existing Task 5 mount/bootstrap and M14 public-safe `WidgetConfig`; never expose or accept provider credentials, provider/model overrides, embedding/vector-store configuration, or retrieval limits.
- Raw model HTML must never be trusted; Task 6 renders response text safely and defers full safe Markdown/link/citation presentation to later Task 6 slices where explicitly tested.
- Launcher and close/send controls must be native focusable buttons with accessible names.
- Focus must move predictably when the panel opens and closes.
- Public UI must not load the administrator bundle.
- Keep the public bundle bounded; do not introduce a new framework dependency unless existing repository conventions require it.
- Use genuine RED -> GREEN chronology for every behavior-changing slice and preserve NOT RED / NOT GREEN checkpoints honestly.

---

### Task 6A: Deterministic mount discovery and accessible launcher/panel state

**Files:**
- Create: `src-js/widget-runtime.ts`
- Create: `src-js/widget-runtime.test.ts`
- Modify: `src-js/widget.ts`
- Modify: `assets/css/widget.css`
- Progress: `docs/progress/M14-TASK6-LAUNCHER-PANEL.md`

**Interfaces:**
- Consumes: server-rendered mount `.wp-rag-ai-chatbot-widget[data-wp-rag-ai-chatbot-bot]` and `window.wpRagAiChatbotWidgetConfigs[]`, where each bootstrap item has `botId`, `restBase`, and public `config`.
- Produces: `mountWidgets(document, configs): number`, which mounts each valid unmounted node at most once and returns the number mounted.
- Produces DOM contract: launcher `button[data-wp-rag-ai-chatbot-launcher]`, panel `[data-wp-rag-ai-chatbot-panel]`, close `button[data-wp-rag-ai-chatbot-close]`, and a focusable message/input shell for later slices.

- [ ] **Step 1: Write the failing mount/state tests**

Create `src-js/widget-runtime.test.ts` with focused tests that:

```ts
import { mountWidgets } from './widget-runtime';

const config = {
	botId: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
	restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
	config: {
		bot_id: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
		name: 'Support bot',
		appearance: {
			primary_color: '#1d4ed8',
			color_mode: 'light',
			position: 'bottom-right',
			launcher_style: 'bubble',
			panel_size: 'medium',
			radius_px: 16,
			font_family: 'system',
		},
	},
};
```

Assert all of the following:

1. a mount with a matching `botId` gets exactly one native launcher button and one initially-hidden panel;
2. a missing bootstrap config leaves the mount untouched and does not throw;
3. calling `mountWidgets` twice does not duplicate controls;
4. launcher `aria-expanded` changes `false -> true`, panel becomes visible, and focus moves to the close control;
5. close restores hidden panel state and focus to the launcher;
6. `Escape` while open closes the panel and restores launcher focus;
7. launcher and close controls have stable accessible names derived from bounded UI copy, never raw HTML.

- [ ] **Step 2: Push the test-only checkpoint and verify real RED**

Run/CI expectation: `npm run test:js -- --runInBand` reaches the new Jest tests and fails because `./widget-runtime` / `mountWidgets` does not exist. Lint/type/build failures before Jest are **NOT RED** and must be repaired without claiming behavioral RED.

- [ ] **Step 3: Implement the smallest runtime state machine**

Create `src-js/widget-runtime.ts` with explicit public bootstrap types and no runtime/provider authority. Implement:

```ts
export function mountWidgets(
	documentRoot: Document,
	configs: readonly WidgetBootstrapConfig[]
): number
```

For each eligible mount:

- read only `data-wp-rag-ai-chatbot-bot`;
- locate the exact matching bootstrap config;
- skip invalid/missing configs without throwing;
- guard idempotence with an internal mount marker;
- create native `button` launcher and close controls;
- create a panel with `hidden = true` initially;
- update `aria-expanded` / panel hidden state on open/close;
- focus close on open and launcher on close;
- close on `Escape` only while the panel is open.

Do not implement chat fetch/streaming in this slice.

- [ ] **Step 4: Wire the browser entry**

Change `src-js/widget.ts` so it normalizes the existing global config array and calls `mountWidgets(document, configs)` once when the bundle executes. Keep the file as an entry/composition seam rather than moving UI behavior back into it.

- [ ] **Step 5: Add bounded responsive shell styles**

Extend `assets/css/widget.css` only for the launcher/panel shell:

- fixed side position from normalized appearance;
- bounded panel width using `min()`/viewport-safe sizing;
- `max-width: calc(100vw - ...)` and `max-height` viewport bounds;
- `overflow` handling that prevents horizontal page overflow;
- visible focus treatment via browser/default-safe CSS;
- no arbitrary CSS injection or unvalidated class names.

- [ ] **Step 6: Verify GREEN**

Required exact-head CI: `js-quality`, `php-quality`, `package`, and `wordpress-smoke` all GREEN. The new Jest tests must pass; TypeScript, ESLint, build, package assertion, and real WordPress smoke must remain green.

- [ ] **Step 7: Review and document**

Perform scoped correctness/security/performance/accessibility/architecture review. Resolve all Critical/Important findings. Record RED/GREEN SHAs and CI in `docs/progress/M14-TASK6-LAUNCHER-PANEL.md` and immediately continue to Task 6B.

---

### Task 6B: Non-streaming conversation submit / loading / error / retry

**Files:**
- Modify: `src-js/widget-runtime.ts`
- Modify: `src-js/widget-runtime.test.ts`
- Modify: `assets/css/widget.css`
- Modify: `docs/progress/M14-TASK6-LAUNCHER-PANEL.md`

**Interfaces:**
- Consumes existing public REST base plus server-authoritative public chat endpoint from Task 4.
- Produces only browser presentation state; server remains the sole runtime/retrieval/provider authority.

- [ ] **Step 1: Write failing interaction tests**

Add Jest tests proving:

- native textarea/input and send button have accessible names;
- empty/whitespace-only submissions do not call `fetch`;
- one submit sends only the bounded public request fields already accepted by Task 4 (`bot_id`, `question`, optional server-issued conversation identifier if present);
- send is disabled while one request is active, preventing duplicate execution;
- loading status uses an appropriate live-status node;
- success appends escaped text content without `innerHTML`;
- stable public API errors render bounded safe copy and a retry button;
- retry replays the last bounded user question once;
- failed requests never surface raw stack traces/provider payloads.

- [ ] **Step 2: Push test-only checkpoint and verify real RED**

A genuine RED must reach Jest and fail on missing submit/loading/error/retry behavior rather than lint/type/environment failures.

- [ ] **Step 3: Implement minimal non-streaming transport UI**

Add a small fetch adapter inside the public widget runtime that posts only to the Task 4 public chat route derived from the trusted server-provided `restBase`. Render all user/assistant/error strings through `textContent`; never use raw HTML. Keep request state per mounted widget and reject duplicate in-flight sends.

- [ ] **Step 4: Verify GREEN and review**

Require exact-head all-permanent-gates GREEN. Review correctness, security (request fields/output rendering), performance (bounded DOM/request concurrency), and accessibility (live status, labels, focus after errors). Update the Task 6 progress record and continue.

---

### Task 6C: Bounded message presentation, copy, safe links/citation-ready surface, responsive closeout

**Files:**
- Modify: `src-js/widget-runtime.ts`
- Modify: `src-js/widget-runtime.test.ts`
- Modify: `assets/css/widget.css`
- Modify: `docs/progress/M14-TASK6-LAUNCHER-PANEL.md`
- Modify: `docs/progress/STATUS.md`
- Modify: `docs/milestones/M14-frontend-chatbot-customizer.md`

**Interfaces:**
- Consumes Task 4 response DTO fields already exposed by the public route; unknown fields are ignored.
- Produces the Task 6 conversation renderer and stable DOM seams that Task 7 streaming can update without introducing a second UI implementation.

- [ ] **Step 1: Write failing rendering/accessibility tests**

Prove that:

- long plain-text answers/URLs cannot create unsafe HTML execution;
- copy control copies only rendered assistant text and has an accessible name;
- any rendered link accepts only `http:`/`https:` and external links receive `rel="noopener noreferrer"`;
- bounded source/citation data, if present in the established public response DTO, is rendered as disclosure/list semantics without raw HTML;
- message history DOM is capped to a documented finite maximum so long sessions cannot grow unbounded;
- keyboard operation remains complete after multiple messages/errors/retries.

- [ ] **Step 2: Push test-only checkpoint and verify real RED**

The new behavior tests must be the reason Jest fails. Preserve invalid checkpoints honestly.

- [ ] **Step 3: Implement minimal safe presentation helpers**

Keep safe text rendering as the default. If linkification is introduced, construct anchor nodes from parsed/allow-listed URLs instead of injecting HTML. Bound message/source collection rendering and keep the helpers reusable by Task 7 streaming.

- [ ] **Step 4: Verify exact-head GREEN and perform Task 6 closeout review**

Run permanent CI and require exact-head green across PHP, JS, package, and WordPress smoke. Perform scoped correctness/security/performance/accessibility/architecture review and resolve all Critical/Important findings.

- [ ] **Step 5: Update durable project state and continue automatically**

Mark Task 6 complete in the milestone/status ledger only after final exact-head GREEN. Record all genuine RED/GREEN and NOT RED/NOT GREEN evidence in `docs/progress/M14-TASK6-LAUNCHER-PANEL.md`. Set Task 7 streaming/simulated typing integration as current work and immediately recover/replan that unit.