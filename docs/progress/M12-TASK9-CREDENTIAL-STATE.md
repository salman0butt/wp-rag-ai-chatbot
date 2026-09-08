# M12 Task 9 — Provider credential state checkpoint

Status: **Task 9 IN PROGRESS — configured credential-state slice COMPLETE**

This checkpoint establishes the browser-side security boundary for provider credentials before any Task 9 mutation or model-selection integration is added.

## Scope completed

- Added a focused provider settings component for credential state.
- The component consumes only the existing Task 4 browser-safe credential shape: `configured` plus `source`.
- Configured state is rendered as non-secret status/source copy.
- The credential control is an empty `type="password"` replacement field with `autocomplete="new-password"`.
- Neither plaintext nor ciphertext is accepted as component state, rendered into text, assigned to an input value/default value, or otherwise rehydrated into browser state.
- Unknown source values fall back to constant non-secret copy.
- No new REST endpoint, credential storage path, provider network call, URL parameter, or logging path was introduced.

## Strict TDD evidence

### Genuine RED

Commit: `ad6f92ff91ad35c6c2da9bd3da7ed20dedb2987f`
CI: `34212072818`

The JavaScript quality job passed lint and typecheck, then Jest executed 38 tests with exactly the new provider credential-state behavior failing because `ProviderSettingsScreen` did not exist:

- 37 passed
- 1 failed
- failure: expected the provider settings surface to exist as a function

This is the genuine behavioral RED for the slice.

### Non-GREEN implementation checkpoint

Commit: `ff021b62fe1da8ce714c96a4938d0ad960c2f6d8`
CI: `34212424840`

This checkpoint is **not counted as GREEN**. Package and PHP passed, but JavaScript verification stopped at four Prettier findings before typecheck/Jest could verify behavior.

The unnecessary `src-js/index.ts` re-export introduced in that checkpoint was subsequently removed so this focused component remains isolated until its mounted/provider-route integration receives its own TDD cycle. The final diff from the Task 8 closeout head contains only the provider settings component and its test.

### GREEN

Implementation head: `6fd758118683bea331f597676c2d7d5d505e8cc6`
CI: `34212835966`

Exact-head CI is fully GREEN:

- `php-quality` — GREEN
- `js-quality` — GREEN
  - lint and typecheck passed
  - 11/11 Jest suites passed
  - 38/38 Jest tests passed
  - build and live-gating checks passed
- `package` — GREEN
- `wordpress-smoke` — GREEN, including environment start, activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and cleanup

## Review

Scoped correctness/security/accessibility/performance review: `5140265520`, anchored to `6fd758118683bea331f597676c2d7d5d505e8cc6`.

Findings:

- Critical: 0
- Important: 0

Review conclusions:

- Correctness: state is deterministic from the browser-safe `{configured, source}` contract.
- Security: no plaintext/ciphertext is rendered, rehydrated, placed in DOM text, or assigned to the replacement input.
- Accessibility: state uses a polite status region and the replacement input has an explicit label.
- Performance: local render-only behavior; no provider/network work.

PR #17 has no unresolved review threads at this checkpoint.

## Exact continuation point

Task 9 remains **ACTIVE**.

Next, under a new genuine behavioral RED, integrate provider settings with the existing Task 4 capability-protected credential REST contract and prove safe credential replacement:

1. load configured/source state without receiving any secret value;
2. submit only a newly entered replacement credential through the existing nonce-authenticated admin client;
3. never prefill or restore the old credential after load, mutation, rerender, or error;
4. refresh from server-authoritative configured/source state after a successful replacement;
5. expose safe, actionable failure state without upstream/secret leakage.

After credential replacement, continue Task 9 with capability-compatible model selection and actionable provider/capability error states. Do not advance to Task 10 until the complete Task 9 acceptance scope has focused/full verification, exact-head green CI, and final review with zero unresolved Critical/Important findings.
