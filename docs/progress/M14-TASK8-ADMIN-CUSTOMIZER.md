# M14 Task 8 — Administrator Visual Customizer / Live Preview

Status: **COMPLETE pending exact-final-documentation-head CI**

## Scope

Task 8 adds a bounded administrator appearance customizer and live preview while preserving one appearance authority across PHP persistence, administrator preview, and the public widget runtime.

The implementation:

- reuses the existing seven-field normalized appearance contract: `primary_color`, `color_mode`, `position`, `launcher_style`, `panel_size`, `radius_px`, and `font_family`;
- shares `WidgetAppearance`, `normalizeWidgetAppearance()`, and `applyWidgetAppearance()` between preview and public runtime instead of creating parallel rendering rules;
- loads and saves only through the existing protected `/admin/bots/{id}/appearance` REST authority and existing `AdminApiClient` nonce/same-origin behavior;
- keeps unsaved preview state browser-local until explicit save;
- accepts no provider/model/credential/embedding/vector/retrieval overrides, arbitrary CSS, or raw HTML;
- uses generation correlation so stale appearance loads/saves cannot overwrite the currently selected bot state.

## Implementation chronology

### Task 8A — shared browser appearance renderer

Key checkpoints:

- `b43347f1f53083f5b4edd57b72f2882915a750fb` — initial shared-renderer test checkpoint.
- `5ed71af22a27f4b46b9d20b750d25b4ce70d9da4` — repaired Task 8A test formatting.
- `76e579c18fec2828b1352e9d575a7a44658e41ac` — shared widget appearance renderer implementation.
- `f5b2657cf2c39d066415ea5a54177967c2f47a8d` — formatting/verification repair for the shared renderer.

Result: public widget and administrator preview consume the same bounded browser appearance normalization/application authority.

### Task 8B — bounded customizer and live preview

Key checkpoints:

- `c6e328e7e6493799a6b49105fb88117982950600` — initial customizer/live-preview test checkpoint.
- `56f5c222a3776f5d194aae0e8678d77e61f5948a`, `663eb907a2ce9e9ec4326cf5b681472e4ca5660e`, `7c802e113775d58b4dbe0e02a00d408e20605559`, `0dd85af23e624d016c730cac12d7885d69607f4c` — test-harness/formatting recovery checkpoints preserved in history.
- `a5e2b61908a99d27dc40205a9e57d99c095e9519` — appearance customizer/live-preview implementation.
- `62249709b40c5bf081acf91d09c4e658a4212b49`, `d67b21c9c7ae1596aa1c554318ae200352bcf9ad` — verification/formatting repairs.

Result: seven labelled bounded controls drive an immediate non-network preview, while save remains explicit and server-authoritative.

### Task 8C — administrator bot-screen load/save integration

Primary integration chronology:

- `0c12324e63922ee5cf20f3c9dd08023ab5cf85cb` — persistence integration test checkpoint.
- `25a55369ab7c9af05ffed9e4f2e1eabb0563d536`, `01b794913e48da15c5f5c85e3bf71cfe555bef7c` — formatting recovery checkpoints.
- `c645443dbf03975c5fdd9c041054d9be3c5e6628` — Task 8C behavioral RED checkpoint.
- `22bbceeed244c13d336b4180bdffa38cf7d5af07` — administrator bot appearance integration.
- `48fedc9ece6ec11873df216c1c30d6396c00b574` — formatting verification repair.
- `a05fcfa9a53db8fc668834bb33f3f590b098f878`, `b1719b47399f0ae352c51e5be946467fd89bb4d3`, `b17739473aa0372b0d0a4814ad0207e0a0822b0f`, `a4982fbec200492112d1bf97f6d4f2a0052e114f` — integration regression coverage and bot-selection/load ordering repair.

Result: the selected bot owns the customizer; protected GET/PUT requests use the existing admin API client; safe bounded error state is rendered without leaking raw diagnostics; bot-switching appearance loads use latest-request-wins correlation.

## Review-driven stale-save regression TDD

Fallback review identified one Important correctness/concurrency risk: an older save for bot A could complete after navigation A → B → A and overwrite a newer reload for bot A because the previous save completion guard compared only the active bot ID.

### NOT RED

- Commit: `a4a565586364adcd24213515e6420c990ee26e6d`
- CI: `34739706111`
- Classification: **NOT RED**.
- Reason: JavaScript verification stopped at Prettier before Jest reached the intended stale-save regression assertion. WordPress smoke also had a separate environment-start failure in that run; neither failure is behavioral RED evidence.

### Genuine RED

- Commit: `79e1609cd693661a80a919812dd86af4ecc0f1d9`
- CI: `34741381595`
- Test: `src-js/appearance-customizer-stale-save.test.ts`
- Lint/typecheck reached Jest successfully.
- Jest result: **1 failed, 108 passed; 1 failed suite, 45 passed suites**.
- Intended failure: after bot A → bot B → bot A, a delayed older bot-A save changed the newer reloaded color from expected `#16a34a` back to stale `#dc2626`.

This is the genuine behavioral RED checkpoint.

### Genuine GREEN

- Commit: `362d47c65d4cafd43086e2862d606595341baa99`
- CI: `34741614657`
- Change: `saveBotAppearance()` now participates in the existing `botAppearanceGeneration` latest-request-wins authority. Save success/error/finally mutations are accepted only while that request generation remains current, the admin screen remains `bots`, and the same bot remains active.
- Scope: one existing state authority was reused; no second save-generation mechanism, fetch client, schema, or API surface was introduced.

Exact implementation-head CI `34741614657` is **GREEN** across:

- `php-quality`;
- `js-quality` including `npm run verify:js` and gating checks;
- `package`;
- `wordpress-smoke`, including activation/database/provider/knowledge/file-ingestion/WooCommerce/Playground REST smoke.

## Scoped review

Independent reviewer transport was unavailable in the connector-only execution environment, so the repository-approved fallback scoped review was used and this limitation is recorded explicitly.

Review results:

- **Correctness:** generation correlation closes stale load/save races including A → B → A and superseded same-lifecycle requests.
- **Security:** existing protected admin REST route, WordPress nonce, same-origin credentials, seven-field allow-list, and server normalization remain unchanged; no credential/runtime override channel was added.
- **Performance:** only bounded integer generation increments/comparisons were added; no extra network/provider/retrieval work.
- **Accessibility:** the regression repair changes no markup or interaction semantics; the customizer uses native labelled controls, explicit save, and a named preview region.
- **Architecture/duplication:** preview/runtime share one browser appearance application authority and save/load correlation reuses `botAppearanceGeneration` rather than introducing parallel state.

Final scoped review state: **0 Critical / 0 Important unresolved**.

## Completion gate

Task 8 implementation is complete and exact implementation-head CI is GREEN. This progress record, `docs/progress/STATUS.md`, and `docs/milestones/M14-frontend-chatbot-customizer.md` must receive exact-final-documentation-head CI before Task 8 is considered durably closed.

After that verification, the next unfinished work is **Task 9 — block/direct/fullscreen embedding surfaces**. Task 9 must reuse the existing Task 5 conditional mount/bootstrap authority and Task 6–7 widget runtime rather than creating a parallel chat/retrieval/generation path.
