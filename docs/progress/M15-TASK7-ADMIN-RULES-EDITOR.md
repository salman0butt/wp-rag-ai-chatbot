# M15 Task 7 — Admin rules editor + deterministic preview

Status: COMPLETE.

## Scope

Build the M15 bot-scoped display-rules editor by reusing the existing protected display-rules REST authority and the Task 1 deterministic evaluator. The editor keeps preview-only visitor facts separate from persisted configuration and does not introduce provider/model/retrieval runtime authority.

## Task 7A — deterministic preview adapter

### TDD evidence

- `4a85d9fc31663e2fcffa3efcfa58d4af34958d46` — **RED**, CI `34789240727`. JavaScript package lint, ESLint/Prettier and TypeScript passed; Jest ran all suites. All pre-existing suites/tests passed and only the new admin preview test failed because `./display-rules-editor` did not yet exist.
- `3350b7962f86437753130e778dbe428d29cc7469` — **GREEN**, CI `34789360184`. Exact-head `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed.

`previewDisplayRules()` delegates directly to the existing Task 1 `evaluateDisplayRules()` authority. Simulated visitor facts affect preview decisions without mutating or entering persisted `DisplayRulesConfig`.

## Task 7B — normalized native editor controls

### TDD evidence

- `d8add6901b160c531c34f79f62a21287940056e1` — **NOT RED**, CI `34789609139`. Prettier failed before TypeScript/Jest.
- `070f8ce35e4704b5ec96afc28364f0bd64b59f2c` — **RED**, CI `34789665252`. Package lint, ESLint/Prettier and TypeScript passed; pre-existing suites/tests passed and only the new editor-controls test failed because `DisplayRulesEditor` did not exist.
- `789d847e0e405171e7f2f7f783dcdeebf8e3b953` — **NOT GREEN**, CI `34789729946`. JavaScript verification stopped on Prettier before typecheck/Jest.
- `e7691340aae5a033eb975d23185c98896fc92668` — **NOT GREEN**, CI `34789806457`. Two Prettier findings remained.
- `86d23928ac629a8166bb53db539b2cad32334f02` — **GREEN**, CI `34789901143`. Exact-head permanent CI fully passed.

`DisplayRulesEditor` renders bounded native controls for URL rules, audience, locale and direction. Changes flow through `normalizeDisplayRules()`. Labels are programmatically associated, submit is native, error feedback uses `role="alert"`, and save state disables submit.

## Task 7C — protected load/save integration and harness repair

The bot editor reuses the existing protected `/admin/bots/{id}/display-rules` GET/PUT authority with nonce headers, normalized payloads and bot-scoped generation guards.

During recovery, exact-head `0c5eb9105a5236c09144d9e029fd98cf7501d964` / CI `34791549864` reached Jest and failed one integration assertion because the test element factory assigned a `<select>` value before appending its `<option>` children. Production normalization and persistence state were correct; the jsdom harness reset the select to its first option.

- `d502e9b38d759c1c0afea7f075825c45c6bcc354` — **NOT GREEN**, CI `34792282536`. The harness fix was behaviorally correct but exact-head JavaScript verification stopped on Prettier.
- `4e38c6e0f4325eebf25058f2ceaedbfcbd18c06c` — **GREEN**, CI `34792380242`. Exact-head `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed.

The test harness now applies a select value after options are appended, matching browser/jsdom selection semantics without changing production behavior.

## Task 7D — bounded validation feedback

- `928cf60afd3eee8617150fdfe1541df18403c70e` — **NOT RED**, CI `34792610009`. Prettier failed before Jest.
- `d531937cf31b42197b3211f0b852ec39d7442a50` — **NOT RED**, CI `34792699730`. TypeScript fixture typing failed before Jest.
- `3c17780cd268cd1aa6a431c6035063f88a3fa165` — Jest reached a failure, but the test expectation was over-specific: it required echoing the backend route message rather than merely requiring bounded validation feedback. This is not recorded as missing-production-behavior RED.
- `deaf1d19d8f0ad66796218d1b3d8246aeff08f13` — **GREEN COVERAGE OF EXISTING BEHAVIOR**, CI `34793048003`. Exact-head permanent CI fully passed. The UI retains the bounded generic alert `Display rules could not be saved.` rather than coupling presentation to raw backend error text.

## Task 7E — stale save response protection

The existing `botDisplayRulesGeneration` guard already prevents an older save response from overwriting a newer bot/reload state.

- `87f0504473e7bfe4d26bbd02142270bea1dea142` — **NOT RED**, CI `34793142624`. Prettier failed before typecheck/Jest.
- `38cadbef21fd8387fb5e4bf6733cc33930536148` — **GREEN COVERAGE OF EXISTING BEHAVIOR**, CI `34793226261`. Exact-head `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed. The new regression switches bot A → bot B → bot A while an old bot-A save is unresolved, then proves the old response cannot replace the newer `/fresh` reload.

No production implementation change was required for stale-save protection, so no RED→GREEN implementation chronology is fabricated.

## Review

Independent reviewer/subagent transport was unavailable in this execution runtime, so the repository-approved scoped fallback review was used.

- Correctness: 0 unresolved Critical/Important findings. The editor delegates normalization/evaluation to existing authorities and generation guards protect load/save races.
- Security/privacy: 0 unresolved Critical/Important findings. Persisted writes contain only the allow-listed display-rules DTO; simulated visitor facts, credentials, provider/model selection, embeddings, vector-store and retrieval authority are not persisted.
- Performance: 0 unresolved Critical/Important findings. Preview/normalization are bounded local operations and persistence adds no polling or unbounded listeners.
- Accessibility: 0 unresolved Critical/Important findings. Native controls remain labeled, save state is conveyed through disabled state, and bounded errors use `role="alert"`.
- Architecture/duplication: 0 unresolved Critical/Important findings. No parallel evaluator, validator or persistence authority was introduced.

## Verification

- Task 7A implementation GREEN: `3350b7962f86437753130e778dbe428d29cc7469`, CI `34789360184`.
- Task 7B implementation GREEN: `86d23928ac629a8166bb53db539b2cad32334f02`, CI `34789901143`.
- Task 7C harness/integration final GREEN: `4e38c6e0f4325eebf25058f2ceaedbfcbd18c06c`, CI `34792380242`.
- Task 7D validation-feedback coverage GREEN: `deaf1d19d8f0ad66796218d1b3d8246aeff08f13`, CI `34793048003`.
- Task 7E stale-save coverage GREEN: `38cadbef21fd8387fb5e4bf6733cc33930536148`, CI `34793226261`.

All listed exact final checkpoints passed `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.

## Next unfinished unit

Task 8A — bounded widget message catalog: establish real Jest RED proving runtime labels resolve through one catalog, unsupported locales fall back deterministically, and rendering remains text-only; then extract existing hard-coded widget labels without behavior drift.
