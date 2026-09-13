# M14 Task 8 Administrator Customizer / Live Preview Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an administrator appearance customizer with immediate unsaved live preview and protected server-authoritative persistence while keeping preview and the public widget on one normalized appearance/rendering authority.

**Architecture:** Extract the browser appearance projection/application code from `widget-runtime.ts` into one focused shared TypeScript module. Both the public widget and administrator preview consume that module. The customizer loads/saves only through the existing protected `/admin/bots/{id}/appearance` Task 3 REST contract; unsaved draft changes update only the preview and persistence always remains server-normalized.

**Tech Stack:** TypeScript, WordPress `wp.element` createElement/render API, Jest/jsdom, existing `AdminApiClient`, PHP `AppearanceConfig`, existing M14 widget CSS/runtime, GitHub Actions permanent gates.

**Spec:** `docs/superpowers/specs/2026-09-12-m14-frontend-chatbot-customizer-design.md`

## Global Constraints

- Status/design approval: `AUTO-APPROVED — SCHEDULED MODE`.
- The PHP `AppearanceConfig` seven-field allow-list remains the server authority: `primary_color`, `color_mode`, `position`, `launcher_style`, `panel_size`, `radius_px`, `font_family`.
- No arbitrary CSS, raw HTML, provider/model/credential/embedding/vector/retrieval settings enter the customizer payload.
- Preview may render unsaved normalized draft state but may not persist without the existing protected Task 3 endpoint.
- Runtime and preview must share the same TypeScript appearance application function and enum/default projection rather than maintaining separate rendering rules.
- Keep the public widget bundle separate from administrator boot behavior; do not mount public chat/network behavior inside the preview.
- Every behavior change requires genuine RED -> GREEN evidence; lint/format/type failures before Jest are NOT RED.
- Every meaningful unit closes with exact-head CI and correctness/security/performance/accessibility/architecture review.

---

### Task 8A: Shared browser appearance renderer

**Files:**
- Create: `src-js/widget-appearance.ts`
- Create: `src-js/widget-appearance.test.ts`
- Modify: `src-js/widget-runtime.ts`

**Interfaces:**
- Produce `WidgetAppearance` with the exact seven normalized browser fields.
- Produce `normalizeWidgetAppearance(value: unknown): WidgetAppearance`, defensively projecting known values to the same defaults already used by the runtime.
- Produce `applyWidgetAppearance(element: HTMLElement, appearance: WidgetAppearance): void`, setting the existing `data-wp-rag-ai-chatbot-*` attributes plus `--wp-rag-ai-chatbot-primary-color` and `--wp-rag-ai-chatbot-radius`.
- `widget-runtime.ts` consumes this authority rather than maintaining its own duplicate enum/default readers.

- [ ] **Step 1: Write the smallest failing shared-renderer test.** Assert that a complete valid seven-field appearance object produces the existing runtime data attributes/CSS variables, and invalid/unknown individual values fall back to existing safe defaults without accepting arbitrary style keys.
- [ ] **Step 2: Commit/push the test-only state and require a genuine Jest RED.** Expected failure: `widget-appearance` module/exports do not exist after lint/typecheck reach the intended test.
- [ ] **Step 3: Implement `widget-appearance.ts` minimally.** Move only the existing runtime appearance constants/readers/application logic into the module; preserve current defaults and CSS/data-attribute names exactly.
- [ ] **Step 4: Refactor `widget-runtime.ts` to consume `normalizeWidgetAppearance` + `applyWidgetAppearance`.** Do not change request, chat, typing, message, citation, or accessibility behavior.
- [ ] **Step 5: Verify focused Jest plus full exact-head CI GREEN.** Require `js-quality`, `php-quality`, `package`, and `wordpress-smoke` success.
- [ ] **Step 6: Review duplication/security/performance/accessibility.** Confirm no runtime visual drift, no arbitrary CSS acceptance, and no extra network work.

---

### Task 8B: Bounded customizer and unsaved live preview component

**Files:**
- Create: `src-js/appearance-customizer.ts`
- Create: `src-js/appearance-customizer.test.ts`
- Consume: `src-js/widget-appearance.ts`

**Interfaces:**
- `AppearanceCustomizerProps` receives normalized `appearance`, `saving`, optional stable error state, `onChange(next: WidgetAppearance)`, and `onSave(next: WidgetAppearance)`.
- Controls cover all seven fields only: color input for `primary_color`; selects for `color_mode`, `position`, `launcher_style`, `panel_size`, `font_family`; number input with `min=0`/`max=32` for `radius_px`.
- Preview renders a non-network static widget shell and calls the same `applyWidgetAppearance()` used by production runtime.
- Changing a control immediately updates the supplied draft/preview through `onChange`; saving is an explicit submit action.

- [ ] **Step 1: Write failing component tests.** Prove all seven labelled controls reflect loaded values; changing primary color/radius updates the preview before save; enum changes project the same data attributes as runtime; submit emits exactly the seven-field draft.
- [ ] **Step 2: Commit/push test-only RED and verify Jest reaches the intended missing-component behavior.**
- [ ] **Step 3: Implement the minimal accessible customizer.** Use native labelled inputs/selects/button, a named preview region, and no `dangerouslySetInnerHTML`/raw CSS text.
- [ ] **Step 4: GREEN exact-head verification and review.** Check keyboard/labelling, safe bounded inputs, one shared appearance renderer, and no network calls inside the component.

---

### Task 8C: Administrator bot-screen load/save integration

**Files:**
- Modify: `src-js/index.ts`
- Create: `src-js/appearance-customizer-integration.test.ts`
- Consume: `src-js/appearance-customizer.ts`
- Reuse: `createAdminApiClient()` and Task 3 protected appearance REST route.

**Interfaces:**
- On the bots screen, the selected bot owns the appearance customizer.
- Load: `GET /admin/bots/{encodeURIComponent(bot.id)}/appearance` -> `{ appearance: WidgetAppearance }`.
- Save: `PUT /admin/bots/{encodeURIComponent(bot.id)}/appearance` with the seven-field appearance object as the request body -> server-normalized `{ appearance: WidgetAppearance }`.
- Selection generation/token ensures a slower appearance response for a previously selected bot cannot replace the current bot's draft/preview.
- Read/save failures map to bounded administrator-safe customizer error state without leaking raw response/provider diagnostics or collapsing unrelated admin state.

- [ ] **Step 1: Write failing boot/integration tests.** Cover initial selected-bot GET, stale selected-bot response suppression, live draft isolation per current bot, PUT body exact shape, normalized save response replacing the draft, and safe read/save failure presentation.
- [ ] **Step 2: Commit/push genuine RED and verify intended Jest failures after lint/typecheck pass.**
- [ ] **Step 3: Extend admin state/render plumbing minimally.** Add selected appearance/draft/loading/error state and generation tracking; avoid unrelated `index.ts` refactors.
- [ ] **Step 4: Wire protected GET/PUT through the existing `AdminApiClient`.** Reuse nonce/same-origin behavior automatically; do not add a second fetch client.
- [ ] **Step 5: Verify focused tests and exact-head full CI GREEN.**
- [ ] **Step 6: Scoped review and regression TDD for all Critical/Important findings.** Review CSRF/nonce reuse, stale response handling, exact payload allow-list, accessibility, renderer/schema reuse, and absence of provider/runtime override channels.
- [ ] **Step 7: Update `docs/progress/M14-TASK8-ADMIN-CUSTOMIZER.md`, `docs/progress/STATUS.md`, and `docs/milestones/M14-frontend-chatbot-customizer.md`, then exact-final-head CI.** Mark Task 8 complete only after the final documentation head satisfies repository verification policy, then recover and continue Task 9.

## Plan self-review

- Spec coverage: shared renderer/schema, unsaved live preview, protected server-authoritative save, safe seven-field controls, and preview/runtime alignment are all mapped.
- Placeholder scan: no TODO/TBD or deferred implementation step remains.
- Type consistency: every subtask uses the same `WidgetAppearance` type and `applyWidgetAppearance()` authority introduced in Task 8A.
- Scope: block/direct/fullscreen embedding remains Task 9; final visual/mobile/integration closeout remains Task 10.
- Security: no new provider/retrieval authority, raw CSS, raw HTML, or credentials enter the browser contract.

**Plan decision:** **AUTO-APPROVED — SCHEDULED MODE**. Execute Task 8A first after the current documentation head is exact-SHA GREEN.
