# M14 Task 6 Floating Launcher/Panel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the public floating launcher/panel shell with deterministic mount discovery, bounded appearance projection, and keyboard-accessible open/close behavior on the existing Task 5 bootstrap contract.

**Architecture:** Keep `src-js/widget.ts` as the tiny browser entrypoint and `src-js/widget-runtime.ts` as the deterministic DOM renderer/state controller. Consume only the already public-safe bootstrap projection; do not add chat/provider/retrieval authority. Task 7 will own network/streaming conversation behavior.

**Tech Stack:** TypeScript, Jest/jsdom, existing WordPress JS quality/build pipeline.

**Spec:** `docs/superpowers/specs/2026-09-12-m14-frontend-chatbot-customizer-design.md`

## Global Constraints

- Reuse the Task 5 public-safe bootstrap contract; no provider credentials or runtime authority in browser state.
- No request-level provider/model/embedding/vector-store/retrieval overrides.
- Launcher and close controls are native buttons with accessible names.
- Opening the panel moves focus predictably into the panel; closing returns focus to the launcher.
- Appearance is applied only from the finite normalized public appearance schema; no arbitrary CSS or HTML.
- Task 7 owns streaming/network chat behavior; Task 6 must not create a second chat/retrieval authority.

---

### Task 1: Recover and verify the closed-state mount baseline

**Files:**
- Test: `src-js/widget.test.ts`
- Modify: `src-js/widget-runtime.ts`
- Modify: `src-js/widget.ts`

**Interfaces:**
- Consumes: `window.wpRagAiChatbotWidgetConfigs`, Task 5 mount elements.
- Produces: `mountWidgets(documentRoot, configs): number`, launcher `[data-wp-rag-ai-chatbot-launcher]`, hidden panel `[data-wp-rag-ai-chatbot-panel]`.

- [x] **Step 1: Write the failing closed-state mount test.**
- [x] **Step 2: Verify RED on the test-only SHA through CI.**
- [x] **Step 3: Add the minimal runtime mount and wire `src-js/widget.ts`.**
- [x] **Step 4: Verify exact-head GREEN through CI.**
- [ ] **Step 5: Record the recovered RED/GREEN chronology in `docs/progress/M14-TASK6-FLOATING-WIDGET.md`.**

### Task 2: Toggle open/close state with focus restoration

**Files:**
- Test: `src-js/widget.test.ts`
- Modify: `src-js/widget-runtime.ts`

**Interfaces:**
- Consumes: mounted launcher and panel from Task 1.
- Produces: click-driven `aria-expanded`/`hidden` synchronization, an accessible close button, focus entering the panel on open and returning to the launcher on close.

- [ ] **Step 1: Add one failing Jest test that clicks the launcher and expects `aria-expanded="true"`, `panel.hidden === false`, a native close button, and focus on that close button.**
- [ ] **Step 2: Push the test-only checkpoint and verify CI fails for those missing behaviors rather than lint/type/infrastructure errors.**
- [ ] **Step 3: Add the smallest event handlers and close button implementation required by the test.**
- [ ] **Step 4: Add one failing test for closing the panel and restoring focus to the launcher.**
- [ ] **Step 5: Implement the minimal close behavior, then verify focused and project-wide GREEN.**
- [ ] **Step 6: Review correctness/accessibility/duplication, fix findings, and record evidence.**

### Task 3: Apply bounded normalized appearance to the shell

**Files:**
- Test: `src-js/widget.test.ts`
- Modify: `src-js/widget-runtime.ts`
- Modify if already established by Task 5: public widget stylesheet entry.

**Interfaces:**
- Consumes: normalized `config.appearance` finite schema from the public-safe projection.
- Produces: finite data/class/style tokens for position, launcher style, panel size, radius, primary color, color mode, and font family without accepting arbitrary CSS.

- [ ] **Step 1: Add a failing Jest test proving supported normalized appearance values become deterministic widget tokens/properties.**
- [ ] **Step 2: Verify real RED on the test-only SHA.**
- [ ] **Step 3: Implement explicit finite mapping from normalized appearance fields to widget tokens/properties; do not spread arbitrary appearance keys into styles.**
- [ ] **Step 4: Add a failing test proving unknown appearance keys cannot become inline CSS/attributes.**
- [ ] **Step 5: Implement the minimal fail-closed behavior and verify GREEN.**
- [ ] **Step 6: Review security/performance/accessibility and record evidence.**

### Task 4: Responsive shell semantics and Task 6 closeout

**Files:**
- Test: `src-js/widget.test.ts`
- Modify: public widget stylesheet/runtime files only as required.
- Create/update: `docs/progress/M14-TASK6-FLOATING-WIDGET.md`
- Modify: `docs/progress/STATUS.md`
- Modify: `docs/milestones/M14-frontend-chatbot-customizer.md`

**Interfaces:**
- Consumes: verified launcher/panel shell from Tasks 1-3.
- Produces: bounded responsive shell ready for Task 7 streaming UX.

- [ ] **Step 1: Add focused tests for stable accessible labels/roles and bounded responsive hook/classes needed by the existing stylesheet contract.**
- [ ] **Step 2: Verify RED where behavior is missing, then implement only the required shell hooks/styles.**
- [ ] **Step 3: Run full exact-head CI: PHP quality, JS quality, package, and WordPress smoke.**
- [ ] **Step 4: Perform scoped correctness, security, performance, accessibility, and architecture/duplication review; resolve all Critical/Important findings.**
- [ ] **Step 5: Record RED/GREEN/NOT RED/NOT GREEN chronology, exact CI runs, review result, and Task 7 handoff in durable docs.**
- [ ] **Step 6: Re-check the remote branch head and continue automatically to Task 7 only after Task 6 exact-final-head CI is GREEN.**

## Self-review

- Spec coverage: launcher/panel shell, keyboard focus, bounded appearance, conditional existing bootstrap, responsive/accessibility gates are covered; streaming/network rendering remains intentionally deferred to Task 7.
- Placeholder scan: no TODO/TBD implementation placeholders.
- Type consistency: the plan preserves the existing `WidgetBootstrapConfig` and `mountWidgets(Document, readonly WidgetBootstrapConfig[]): number` seam.

Status: **AUTO-APPROVED — SCHEDULED MODE**.
