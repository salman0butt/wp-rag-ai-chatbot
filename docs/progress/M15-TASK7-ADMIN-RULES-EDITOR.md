# M15 Task 7 — Admin rules editor + deterministic preview

Status: IN PROGRESS.

## Scope

Build the M15 bot-scoped display-rules editor by reusing the existing protected display-rules REST authority and the Task 1 deterministic evaluator. The editor must keep preview-only visitor facts separate from persisted configuration and must not introduce any provider/model/retrieval runtime authority.

## Task 7A — deterministic preview adapter

### TDD evidence

- `4a85d9fc31663e2fcffa3efcfa58d4af34958d46` — **RED**, CI `34789240727`. JavaScript package lint, ESLint/Prettier and TypeScript passed; Jest ran all suites. All 58 pre-existing suites / 154 pre-existing tests passed, and only the new admin preview test failed because `./display-rules-editor` did not yet exist.
- `3350b7962f86437753130e778dbe428d29cc7469` — **GREEN**, CI `34789360184`. Exact-head `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed.

### Implementation

`src-js/display-rules-editor.ts` exposes a deliberately small `previewDisplayRules()` adapter that delegates directly to the existing Task 1 `evaluateDisplayRules()` authority.

The test verifies that simulated visitor facts such as path, authentication state and device bucket affect the preview decision without being written into or mutating the normalized persisted `DisplayRulesConfig` object.

## Task 7B — normalized native editor controls

### TDD evidence

- `d8add6901b160c531c34f79f62a21287940056e1` — **NOT RED**, CI `34789609139`. The test-only checkpoint stopped on two Prettier findings before TypeScript/Jest, so it is not behavioral RED evidence.
- `070f8ce35e4704b5ec96afc28364f0bd64b59f2c` — **RED**, CI `34789665252`. Package lint, ESLint/Prettier and TypeScript passed; all 59 pre-existing suites / 155 pre-existing tests passed, and only the new editor-controls test failed because `DisplayRulesEditor` did not exist.
- `789d847e0e405171e7f2f7f783dcdeebf8e3b953` — **NOT GREEN**, CI `34789729946`. PHP quality passed, but JavaScript verification stopped on eight Prettier-only findings in the new implementation before typecheck/Jest.
- `e7691340aae5a033eb975d23185c98896fc92668` — **NOT GREEN**, CI `34789806457`. The first formatting repair reduced the JavaScript failure to two remaining Prettier findings; no behavioral failure was reached.
- `86d23928ac629a8166bb53db539b2cad32334f02` — **GREEN**, CI `34789901143`. Exact-head `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed.

### Implementation

`DisplayRulesEditor` now renders bounded native controls for URL include/exclude patterns, audience selection, locale and direction. Each change flows through the existing `normalizeDisplayRules()` authority before `onChange` or `onSave`, so the component does not introduce a parallel validator or rule model.

Labels are programmatically associated with their controls. The save action is a native form submit, exposes an accessible `role="alert"` validation/error surface, and disables the submit button while saving. The component remains persistence-agnostic: it accepts normalized config and callbacks and does not own REST/provider/model/retrieval authority.

### Review

Independent reviewer/subagent transport remains unavailable in this execution runtime, so the repository-approved scoped fallback review was used.

- Correctness: 0 unresolved Critical/Important findings. Editing is normalized through the production config authority and submit sends the current normalized draft.
- Security/privacy: 0 unresolved Critical/Important findings. The editor only edits the allow-listed display-rules DTO; visitor simulation facts, credentials, provider/model selection, embeddings, vector-store and retrieval authority are absent.
- Performance: 0 unresolved Critical/Important findings. Editing is local deterministic normalization with no extra I/O.
- Accessibility: 0 unresolved Critical/Important findings. Native textarea/select/input/button controls have associated labels; errors use `role="alert"`; save state disables the button.
- Architecture/duplication: 0 unresolved Critical/Important findings. The editor reuses `normalizeDisplayRules()` and remains transport-agnostic; no duplicate rule engine or persistence authority was created.

## Verification

- Task 7A final implementation GREEN: `3350b7962f86437753130e778dbe428d29cc7469`, CI `34789360184`.
- Task 7B final implementation GREEN: `86d23928ac629a8166bb53db539b2cad32334f02`, CI `34789901143`.

Both exact implementation heads passed `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.

## Next unfinished unit

Continue Task 7 with bot-scoped admin load/edit/save behavior through the existing protected `/admin/bots/{id}/display-rules` GET/PUT authority. Required remaining coverage includes per-bot isolation, stale load/save response protection, route validation feedback, and deterministic preview facts that are never persisted.
