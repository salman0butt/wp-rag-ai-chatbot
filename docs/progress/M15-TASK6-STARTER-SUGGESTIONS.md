# M15 Task 6 — Page-specific starter suggestions

Status: COMPLETE.

## Scope

Render Task 1 selected page-specific starter prompts through the existing M14 public widget entrypoint while preserving one shared chat submission authority.

Required behavior:

- selected starters render as a bounded list of native buttons;
- prompt text is inserted as plain text, never raw HTML;
- page-specific selection continues to come from the existing Task 1 evaluator;
- selecting a starter uses the existing textarea/form submit path exactly once;
- the adapter does not issue its own chat request or introduce provider/model/retrieval authority;
- the existing shared form remains responsible for chat request validation, in-flight duplicate prevention, conversation reuse and transcript rendering.

## Task 6A — bounded plain-text rendering

### TDD evidence

- `2ccb65cb037609cc1c2eb4d2b3d927231fa6f716` — **RED**, CI `34788030331`. JavaScript lint/typecheck reached Jest; all 57 existing suites / 152 existing tests passed and only the new starter-rendering assertion failed because zero starter buttons existed.
- `afdf66e8762b209bff7a73e6d0ae9218f5aaa2e2` — **NOT RED**, CI `34788187143`. The fixture was refined to exercise the real `widget.ts` entrypoint but Prettier stopped verification before Jest.
- `0c4fac4c8eafa28cf32ed71d100360ee25635629` — refined **RED**, CI `34788264685`. Lint/typecheck passed; all pre-existing suites/tests stayed green and only the entrypoint starter-rendering assertion failed.
- `8e6745e32bf0754f9ac9daeb9873a44534363c91` / `beef72884ca5c4ca2a1010356dfc933f7ee3d12e` — introduced the minimal starter adapter and mounted it after the existing shared widget runtime.
- `beef72884ca5c4ca2a1010356dfc933f7ee3d12e` — **NOT GREEN**, CI `34788317144`. JavaScript verification stopped at one Prettier import-formatting finding before typecheck/Jest.
- `6fa42120d7832ce22af5de2522d153977dd09f46` — **GREEN**, CI `34788360363`. Exact-head `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed.

### Implementation

`src-js/widget-starters.ts` is a small presentation adapter that:

- runs only after the existing runtime has mounted a widget;
- reuses `normalizeDisplayRules` + `evaluateDisplayRules` rather than implementing selection logic again;
- caps the rendered collection at four buttons as a browser-side defense in depth;
- creates native `button type="button"` controls inside a labelled suggestion group;
- assigns prompt content with `textContent`, so markup-looking strings remain literal text and cannot execute as HTML.

`src-js/widget.ts` keeps the production ordering explicit: mount the existing shared widget runtime first, then decorate eligible mounts with selected starters.

## Task 6B — reuse existing form/chat submission authority

### TDD evidence

- `e75237d57da1827eaa6d4dceff90c9ac8bbf1904` — **NOT RED**, CI `34788550182`. The test-only submit fixture stopped at Prettier before typecheck/Jest.
- `b08a974cdfc443d8565a2f66d70727ea221525c7` — **RED**, CI `34788618270`. Lint/typecheck passed; 57 existing suites / 153 existing tests remained green and only the new starter-submit assertion failed because clicking `Pricing` produced zero chat requests.
- `b346c0f0474730cf81c7bbd6679d5e28256f076c` — **GREEN**, CI `34788699021`. The adapter now fills the existing shared question textarea and calls `form.requestSubmit()`; exact-head `php-quality`, `js-quality`, `package`, and the complete `wordpress-smoke` matrix all passed.

### Implementation

Starter buttons do not know the REST URL and do not call `fetch`. On click they only:

1. place the selected normalized prompt into `[data-wp-rag-ai-chatbot-question]`;
2. call `requestSubmit()` on `[data-wp-rag-ai-chatbot-form]`.

The existing M14 form handler therefore remains the sole browser chat authority, including trimming, bot scoping, conversation continuation, request construction, in-flight duplicate prevention, transcript updates, error handling and focus behavior.

## Review

Independent reviewer/subagent transport is unavailable in this execution runtime, so the repository-approved scoped fallback review was used and the limitation is recorded honestly.

- Correctness: 0 unresolved Critical/Important findings. Task 1 remains the only starter-selection authority; four selected prompts render, and one starter click reaches the existing submit path exactly once.
- Security/privacy: 0 unresolved Critical/Important findings. Prompts use `textContent`; there is no raw translation/prompt HTML, no direct request construction in the adapter, and no credentials/provider/model/embedding/vector/retrieval authority is exposed.
- Performance: 0 unresolved Critical/Important findings. At most four native buttons/listeners are added per mounted widget and no network work occurs until the user activates a starter.
- Accessibility: 0 unresolved Critical/Important findings. Suggestions are native buttons in a labelled group; they preserve browser keyboard semantics and use the existing form/chat interaction path.
- Architecture/duplication: 0 unresolved Critical/Important findings. The adapter reuses both the pure Task 1 evaluator and the M14 form submission authority; no second chat/RAG path exists.

## Verification

Task 6A final rendering GREEN: `6fa42120d7832ce22af5de2522d153977dd09f46`, CI `34788360363`.

Task 6 final implementation GREEN: `b346c0f0474730cf81c7bbd6679d5e28256f076c`, CI `34788699021` — GREEN across `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.

## Task 6 final status

Task 6 is COMPLETE. Page-specific starter suggestions are bounded, plain-text native controls selected by the existing evaluator and submitted exactly once through the existing shared chat form authority.

## Next unfinished unit

Task 7 — admin rules editor + deterministic preview facts. Begin with test-only RED for bot-scoped load/edit/save isolation, validation errors, stale-response protection, and preview evaluation through the same Task 1 authority without persisting simulated visitor facts.
