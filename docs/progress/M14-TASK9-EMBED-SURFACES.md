# M14 Task 9 — Block / Direct / Fullscreen Embed Surfaces

Status: COMPLETE pending only the normal exact-head documentation CI that follows this durable closeout update.

## Scope

Task 9 adds WordPress-native adapters for embedded/fullscreen/direct and Gutenberg usage without creating a second chatbot implementation. Every surface reuses the existing Task 5 `PublicWidgetBootstrap` mount/bootstrap authority and the Task 6–7 `widget-runtime.ts` conversation authority. The browser still calls the existing Task 4 public chat REST path exactly once per submitted question; no surface accepts provider/model/credential/embedding/vector-store/retrieval-limit overrides.

## Implemented surfaces

- Existing floating shortcode remains the floating adapter.
- Embedded shortcode emits the shared mount contract with `surface=embedded`.
- Fullscreen shortcode emits the same mount contract with `surface=fullscreen`.
- Dynamic Gutenberg block `wp-rag-ai-chatbot/chatbot` accepts only the bounded bot identifier and renders through the same server authority.
- Block editor code is an adapter only; it does not implement chat/retrieval/generation.
- `widget-runtime.ts` interprets only the finite `floating|embedded|fullscreen` surface enum and reuses one conversation renderer.
- The Gutenberg metadata/runtime are built and included in the production plugin archive.

## Package/build TDD evidence

### Genuine RED

- SHA: `3cf46e8f4f0b79472d26a1e5593d0e3c45aad1fe`
- CI: `34747519640`
- Change: package assertion gained a negative fixture requiring both `blocks/chatbot/block.json` and `build/chatbot-block.js`.
- Classification: **RED**. JavaScript verification and unrelated gates passed; only the new package-assertion behavior failed because the production package contract did not yet require the block runtime.

### GREEN

- Build/package wiring commits include `d4194f2adf621a18b83f017e174d15ec9e439a03` and `02962ed49882089548dffb813a75ade048bb9abb`.
- Final implementation SHA for this cycle: `02962ed49882089548dffb813a75ade048bb9abb`.
- CI: `34747631044`.
- Result: `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all GREEN. Production build compiles `src-js/chatbot-block`; npm package inputs include `blocks/**`; archive assertions require block metadata and bundle.

## Real WordPress surface smoke

A dedicated real-WordPress smoke was added and made permanent in `wordpress-smoke`:

- `scripts/test-wp-widget-surfaces.sh`
- `scripts/test-wp-widget-surfaces.php`
- npm command `test:wp:widget-surfaces`

The smoke verifies:

- floating, embedded and fullscreen shortcodes are registered;
- the dynamic Gutenberg block is registered;
- no public widget asset is enqueued before a valid mount;
- missing and disabled bots fail closed across all surfaces and do not enqueue assets;
- enabled floating/embedded/fullscreen/block surfaces render the expected shared mount contract;
- Gutenberg projects the embedded surface;
- the same public widget JS/CSS assets are reused;
- public bootstrap contains the selected public bot identity but not fixture provider/model names, `retrieval_limit`, `vector_store`, or credential authority.

Verification head: `2faf9677da3e8759021e22324b3d1f005f5a33de`.
CI: `34747870930` — all four permanent jobs GREEN, including the new real widget-surface smoke.

## Review and invalid checkpoint

Independent reviewer transport was unavailable in this connector-only execution, so the repository-approved scoped fallback correctness/security/performance/accessibility/architecture review was used.

Fallback review initially suspected embedded/fullscreen Escape could close an always-open panel with no launcher. A regression test was committed at `6ba3783f5b83d87790039d0774e0ad6f41d99231` / CI `34748005486`.

That checkpoint is explicitly **NOT RED**. Detailed CI logs showed lint/typecheck succeeded and Jest reached the two new assertions, but the meaningful `panel.hidden === false` behavior already passed. Only an invalid additional assertion requiring the non-floating panel to carry the floating-only `is-open` class failed. Source inspection confirmed the Escape listener is already registered only inside `if ( isFloating )`; therefore the proposed production fix was unnecessary and would have been blind review implementation.

The test was repaired without production changes at `a4150af0c29d46a23f24bbb9452079ca970c4224`.
Exact-head CI: `34748133442` — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all GREEN, including `test:wp:widget-surfaces`.

## Final scoped review

- Correctness: one server renderer and one browser runtime serve all four surfaces; invalid/disabled bots fail closed.
- Security: no new public runtime authority, secrets, raw HTML, or arbitrary provider/model/retrieval override surface was introduced.
- Performance: adapters reuse one conditional widget bundle; no duplicate retrieval/generation request was added.
- Accessibility: floating keeps launcher/close/Escape/focus restoration; embedded/fullscreen remain open because they intentionally have no launcher to restore focus to; native form controls and named dialog behavior remain shared.
- Architecture/duplication: block/direct/fullscreen are adapters over existing production authorities; there is no parallel RAG/chat path.
- Unresolved Critical findings: 0.
- Unresolved Important findings: 0.

## Completion state

Task 9 implementation and verification are complete. The exact documentation head created by this closeout update must pass the permanent CI gates before Task 9 is treated as durably closed. After that gate, Task 10 is the only remaining M14 task: milestone integration, visual/accessibility/mobile/performance/security review, final exact-head verification, merge gate, and post-merge verification.
