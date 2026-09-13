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

## Review

Independent reviewer/subagent transport is unavailable in this execution runtime, so the repository-approved scoped fallback review was used and the limitation is recorded honestly.

- Correctness: 0 unresolved Critical/Important findings. Preview uses the same production evaluator and returns the evaluator decision unchanged.
- Security/privacy: 0 unresolved Critical/Important findings. Simulated visitor facts remain call-local and are not merged into persisted rules; no credentials/provider/model/embedding/vector/retrieval authority is introduced.
- Performance: 0 unresolved Critical/Important findings. The adapter adds no I/O and one direct pure evaluator call.
- Accessibility: no UI was introduced in this slice; no accessibility finding applies yet.
- Architecture/duplication: 0 unresolved Critical/Important findings. There is no second rule engine; the adapter reuses Task 1 exactly.

## Verification

Task 7A final implementation GREEN: `3350b7962f86437753130e778dbe428d29cc7469`, CI `34789360184` — GREEN across `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.

## Next unfinished unit

Continue Task 7 with bot-scoped admin load/edit/save behavior through the existing protected `/admin/bots/{id}/display-rules` GET/PUT authority. Required remaining coverage includes normalized native controls, validation feedback, per-bot isolation, stale load/save response protection, keyboard-accessible labels, and deterministic preview facts that are never persisted.
