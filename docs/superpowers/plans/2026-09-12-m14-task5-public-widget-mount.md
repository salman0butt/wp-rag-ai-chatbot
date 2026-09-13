# M14 Task 5 — Public Widget Mount / Conditional Assets Implementation Plan

Status: ACTIVE — auto-approved by `docs/AUTONOMOUS-DEVELOPMENT.md`

## Goal

Introduce the smallest WordPress-native public mount seam for M14 without creating a second chat/runtime authority. A shortcode may identify one bot by stable ID, resolve only the existing `WidgetConfig` public projection, render a deterministic mount boundary, and enqueue the future widget bundle only when a valid enabled bot is actually mounted.

## Security / authority boundary

- Accept only an explicit bot identifier at the shortcode/mount boundary.
- Do not accept provider, generation-model, embedding, vector-store, credential, retrieval-limit, prompt, arbitrary CSS, or other runtime overrides.
- Reuse `WidgetConfigResolver` as the sole browser configuration projection.
- Missing/disabled/invalid bots fail closed and must not enqueue public assets.
- Browser bootstrap configuration contains only public-safe bot identity/name/appearance plus the stable public REST base required by the client.
- Do not duplicate M10/M11 retrieval/generation composition.

## 5A — shortcode mount contract and conditional enqueue

**Files**
- Create `src/Frontend/PublicWidgetBootstrap.php`.
- Create `tests/Unit/Frontend/PublicWidgetBootstrapTest.php`.

**RED**
1. Specify that `register()` wires the stable shortcode.
2. Specify invalid/missing bot IDs fail closed without script/style enqueue.
3. Specify an enabled bot resolved through the existing public projection produces one deterministic mount element and conditionally enqueues the public widget assets.
4. Specify the inline browser bootstrap contains only the explicit public projection + REST base; no shortcode/runtime override fields are accepted or serialized.
5. Push the test-only commit and require CI to reach PHPUnit's intended missing-class/behavior failure. Style/static/infrastructure failures before the intended test are `NOT RED` and must be repaired without rewriting chronology.

**GREEN**
Implement the smallest bootstrap fulfilling the contract, using WordPress escaping/JSON helpers and `WidgetConfigResolver`. No widget UI behavior belongs in this unit. Verify exact-head permanent CI.

## 5B — core hook composition

**Files**
- Modify `src/Core/Bootstrap.php`.
- Modify `tests/Unit/Core/BootstrapTest.php`.

Strict RED -> GREEN: prove `plugins_loaded` (or the repository-consistent public registration point) wires `PublicWidgetBootstrap::register()` exactly once. Keep REST registration unchanged.

## 5C — dedicated public build entry / package gate

Recover `@wordpress/scripts` build conventions before implementation. Add the minimum public widget entry/bundle naming that does not make admin pages load public assets. Extend package/build assertions so the production archive contains the public bundle. Execute strict JS/package RED -> GREEN and preserve invalid checkpoints honestly.

## 5D — real WordPress smoke / closeout

Add real WordPress smoke proving:
- plugin activation registers the shortcode;
- a page without a widget mount does not enqueue the public widget bundle;
- a valid enabled-bot shortcode renders the mount and enqueues assets;
- invalid/disabled bot mounting fails closed;
- generated bootstrap data contains no provider/model/credential/vector/retrieval authority.

Run permanent CI on exact final Task 5 head, perform correctness/security/performance/accessibility/architecture review, resolve all Critical/Important findings, record evidence, then activate Task 6 and continue.

## Verification gates

PHP: Composer validation/install/audit, PHPCS, PHPStan, PHPUnit.  
JS: engine/package lint, ESLint, TypeScript, Jest, build, provider/vector gating.  
Package: production install/build/plugin zip/assertion.  
WordPress: relevant activation/public-widget smoke plus the permanent smoke suite.

## Review gates

- Correctness: no duplicate registration/enqueue, deterministic multi-shortcode behavior.
- Security/privacy: closed attributes; public projection only; escaped HTML; JSON-safe config; no secrets/runtime authority in HTML.
- Performance: no public bundle on pages without a valid mount; avoid duplicate enqueue for multiple mounts.
- Accessibility: mount itself must not introduce misleading interactive semantics; actual launcher/panel accessibility begins in Task 6.
- Architecture: reuse `WidgetConfigResolver`, existing public REST authority, and WordPress enqueue APIs rather than parallel configuration/runtime code.
