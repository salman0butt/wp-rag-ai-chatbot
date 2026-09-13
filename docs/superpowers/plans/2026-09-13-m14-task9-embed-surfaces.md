# M14 Task 9 Embed Surfaces Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Gutenberg block, direct embedded, and fullscreen public chatbot surfaces without creating another chat, retrieval, provider, or rendering implementation.

**Architecture:** Keep `PublicWidgetBootstrap` as the single server-side mount/bootstrap authority and `widget-runtime.ts` as the single browser conversation authority. Every surface emits the existing `.wp-rag-ai-chatbot-widget` mount plus one finite `surface` value (`floating|embedded|fullscreen`) and the same public-safe `WidgetBootstrapConfig`. Embedded/fullscreen presentation changes only layout/open-state behavior; public chat requests continue through the Task 4 REST composition exactly once.

**Tech Stack:** PHP 8.2, WordPress 6.9+, Brain Monkey/PHPUnit, TypeScript, Jest/JSDOM, `@wordpress/scripts`.

**Spec:** `docs/superpowers/specs/2026-09-12-m14-frontend-chatbot-customizer-design.md`

## Global Constraints

- Reuse Task 5 `PublicWidgetBootstrap`/`PublicWidgetMount` and Task 6-7 `widget-runtime.ts`; do not create a second public chat client.
- Surface values are a finite allow-list only: `floating`, `embedded`, `fullscreen`.
- Public browser configuration remains allow-listed and must not contain provider credentials, provider/model overrides, embedding/vector-store settings, or retrieval limits.
- Existing `[wp_rag_ai_chatbot bot="..."]` behavior remains backwards-compatible as `floating`.
- Embedded/fullscreen surfaces may change layout/open-state only; they must not alter REST payload shape or production retrieval/generation composition.
- Every behavior change uses real RED -> GREEN evidence and exact-head CI.

---

### Task 1: Stabilize the browser bootstrap contract

**Files:**
- Modify: `src/Frontend/PublicWidgetBootstrap.php`
- Test: `tests/Unit/Frontend/PublicWidgetBootstrapTest.php`

**Interfaces:**
- Consumes: `WidgetConfig::to_array(): array`, `rest_url()`, existing conditional asset enqueue.
- Produces: browser config matching `WidgetBootstrapConfig`: `{ botId, restBase, config }`.

- [x] **Step 1: Write the failing contract test**

Require the inline bootstrap to contain top-level `botId`, normalized `restBase`, nested public `config`, and no provider/model/retrieval authority.

- [x] **Step 2: Verify real RED**

The repaired test-only checkpoint reaches PHPUnit and fails because the old inline payload does not satisfy the browser runtime contract.

- [x] **Step 3: Implement the minimal contract alignment**

Build one `$browser_config` array in `PublicWidgetBootstrap::render_shortcode()` and encode/push it before mounting.

- [x] **Step 4: Verify GREEN**

Require PHPCS, PHPStan, PHPUnit, JS quality, package, and WordPress smoke on the exact implementation/fix head.

---

### Task 2: Add one finite surface value to the shared mount/runtime contract

**Files:**
- Modify: `src/Frontend/PublicWidgetBootstrap.php`
- Modify: `src-js/widget-runtime.ts`
- Modify: `assets/widget.css`
- Test: `tests/Unit/Frontend/PublicWidgetBootstrapTest.php`
- Test: `src-js/widget-runtime.test.ts`

**Interfaces:**
- Consumes: existing `WidgetBootstrapConfig`, `.wp-rag-ai-chatbot-widget` mount selector, existing launcher/panel renderer.
- Produces: `surface: 'floating' | 'embedded' | 'fullscreen'` in browser config and `data-wp-rag-ai-chatbot-surface` on the existing mount.

- [ ] **Step 1: Write PHP RED for finite server surface projection**

Add a test proving the existing shortcode projects `surface: floating` and mount data `floating`, while a new internal render helper rejects any value outside the finite allow-list.

- [ ] **Step 2: Verify PHP RED**

Run the focused PHPUnit test and require failure because no surface contract exists yet; PHPCS/PHPStan must reach the test.

- [ ] **Step 3: Implement the minimal server surface helper**

Refactor `render_shortcode()` to delegate to one private/internal renderer such as:

```php
private function render_surface( array $attributes, string $surface ): string {
    if ( ! in_array( $surface, array( 'floating', 'embedded', 'fullscreen' ), true ) ) {
        return '';
    }
    // Resolve existing mount once, enqueue existing assets once, and project the same public config.
}
```

Keep `render_shortcode()` delegating with `floating` so the old shortcode remains unchanged.

- [ ] **Step 4: Write JS RED for embedded/fullscreen presentation**

Require `WidgetBootstrapConfig.surface`, then prove `embedded` and `fullscreen` mounts start with the existing panel open and do not expose the floating launcher; `floating` keeps current behavior.

- [ ] **Step 5: Verify JS RED**

Run the focused Jest suite and require the new surface assertions to fail for missing behavior rather than formatting/type/lint failures.

- [ ] **Step 6: Implement minimal runtime/CSS presentation**

Use the existing panel, transcript, submit, retry, citation, and simulated-typing code. Add only finite surface dataset/classes and layout rules; do not fork rendering or network code.

- [ ] **Step 7: Verify GREEN and review**

Run focused PHP/Jest plus exact-head permanent CI. Review correctness, security, performance, accessibility, and architecture; resolve all Critical/Important findings.

---

### Task 3: Add direct embedded and fullscreen WordPress adapters

**Files:**
- Modify: `src/Frontend/PublicWidgetBootstrap.php`
- Test: `tests/Unit/Frontend/PublicWidgetBootstrapTest.php`

**Interfaces:**
- Consumes: Task 2 single `render_surface()` authority.
- Produces: stable adapters that only choose a finite surface and pass the existing `bot` identifier.

- [ ] **Step 1: Write RED for adapter registration and delegation**

Require registration of:

```php
add_shortcode( 'wp_rag_ai_chatbot_embed', array( $bootstrap, 'render_embed_shortcode' ) );
add_shortcode( 'wp_rag_ai_chatbot_fullscreen', array( $bootstrap, 'render_fullscreen_shortcode' ) );
```

Verify both callbacks resolve through the same mount authority; invalid/disabled bot IDs remain fail-closed with no assets.

- [ ] **Step 2: Verify RED**

Run focused PHPUnit and require missing adapter registration/callback failures.

- [ ] **Step 3: Implement the thin adapters**

```php
public function render_embed_shortcode( array $attributes = array() ): string {
    return $this->render_surface( $attributes, 'embedded' );
}

public function render_fullscreen_shortcode( array $attributes = array() ): string {
    return $this->render_surface( $attributes, 'fullscreen' );
}
```

No adapter may accept runtime/provider/model/retrieval overrides.

- [ ] **Step 4: Verify GREEN and review**

Run focused PHPUnit and exact-head CI; verify no duplicate asset/runtime authority and no public secret/config expansion.

---

### Task 4: Add a dynamic Gutenberg block adapter over the embedded surface

**Files:**
- Create: `blocks/chatbot/block.json`
- Create: `src-js/chatbot-block.ts`
- Modify: `package.json`
- Modify: `src/Frontend/PublicWidgetBootstrap.php`
- Test: `tests/Unit/Frontend/PublicWidgetBootstrapTest.php`
- Test: `src-js/chatbot-block.test.ts`

**Interfaces:**
- Consumes: Task 3 embedded renderer and existing public runtime.
- Produces: `wp-rag-ai-chatbot/chatbot` dynamic block with one bounded string attribute `bot`.

- [ ] **Step 1: Write PHP RED for block registration/render delegation**

Require `init` registration of `wp-rag-ai-chatbot/chatbot` and prove its server render callback passes only `bot` to the embedded surface renderer.

- [ ] **Step 2: Verify PHP RED**

Run focused PHPUnit and require failure because block registration does not exist.

- [ ] **Step 3: Implement server registration with metadata**

Register the block from plugin-owned metadata and use a render callback on the existing bootstrap. Unknown/missing bot attributes return an empty string.

- [ ] **Step 4: Write editor RED**

Require the editor entry to register exactly `wp-rag-ai-chatbot/chatbot`, expose one bot-id text control/placeholder, and save `null` because rendering is dynamic.

- [ ] **Step 5: Verify JS RED**

Run focused Jest/TypeScript; the failure must be missing block-editor behavior, not tooling.

- [ ] **Step 6: Implement the smallest editor adapter**

Use WordPress block APIs only for authoring the bounded `bot` attribute. The editor must not contain provider/model/retrieval settings or duplicate the public chat runtime.

- [ ] **Step 7: Add the block entry to the existing build**

Extend the existing `wp-scripts build` entry list with `src-js/chatbot-block` and keep package assertions green.

- [ ] **Step 8: Verify GREEN and review**

Run focused tests, TypeScript/lint/build, and exact-head permanent CI. Review correctness/security/performance/accessibility/architecture.

---

### Task 5: Task 9 WordPress smoke and durable closeout

**Files:**
- Create or modify: `scripts/test-wp-widget-surfaces.sh`
- Modify: `.github/workflows/ci.yml` only if the existing smoke aggregator requires an explicit command.
- Create: `docs/progress/M14-TASK9-EMBED-SURFACES.md`
- Modify: `docs/progress/STATUS.md`
- Modify: `docs/milestones/M14-frontend-chatbot-customizer.md`

**Interfaces:**
- Consumes: all Task 9 surface adapters.
- Produces: real WordPress evidence that every surface resolves the same public-safe bot/mount/runtime authority.

- [ ] **Step 1: Write smoke assertions before implementation changes**

Cover floating shortcode, embedded shortcode, fullscreen shortcode, and dynamic block rendering for an enabled bot; cover invalid/disabled bot fail-closed behavior and absence of provider/model/retrieval authority in rendered bootstrap data.

- [ ] **Step 2: Run smoke and classify failures honestly**

A real RED must fail the intended surface behavior after WordPress starts successfully; environment/bootstrap failures are NOT RED.

- [ ] **Step 3: Make only integration fixes required by the smoke**

Do not introduce another transport/runtime implementation.

- [ ] **Step 4: Perform scoped review**

Correctness: adapters map to the right finite surface. Security: no secrets/overrides. Performance: assets stay conditional and one runtime bundle is reused. Accessibility: embedded/fullscreen retain the existing labelled panel/form/focus semantics. Architecture: no duplicate chat/retrieval/generation implementation.

- [ ] **Step 5: Verify exact-final-head CI**

Require `php-quality`, `js-quality`, `package`, and `wordpress-smoke` GREEN on the exact final Task 9 head.

- [ ] **Step 6: Persist durable evidence and continue**

Record all RED/GREEN and invalid checkpoints in `docs/progress/M14-TASK9-EMBED-SURFACES.md`, mark Task 9 complete only after exact-final-head CI, update the PR, then immediately continue Task 10.
