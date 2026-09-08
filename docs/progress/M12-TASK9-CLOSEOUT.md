# M12 Task 9 — Provider/Model Configuration Closeout

## Status

**COMPLETE** — Task 9 satisfies the planned provider/model configuration acceptance surface and is ready to hand off to Task 10 integration/E2E closeout work.

Implementation boundary reviewed: Task 8 closeout head `935668795b5c941fd70bce0113c734740e080b95` through Task 9 final implementation `394da863b54f6663743c98bf8dd978e675041535`.

## Acceptance reconciliation

Task 9 required four behavior families. All are now covered:

1. **Browser-safe credential state**
   - Existing credentials render only `configured` / `source` metadata.
   - The replacement password field is empty and uses `autocomplete="new-password"`.
   - Plaintext/ciphertext is never returned, rendered, or rehydrated.
   - Evidence: `docs/progress/M12-TASK9-CREDENTIAL-STATE.md`.

2. **Safe credential replacement**
   - Only a newly entered credential is written.
   - The existing nonce-authenticated Task 4 REST contract is reused.
   - Successful writes refresh server-authoritative credential state.
   - Evidence: `docs/progress/M12-TASK9-CREDENTIAL-REPLACEMENT.md`.

3. **Capability-compatible server-authoritative model selection**
   - The UI renders only normalized model choices supplied by the existing Task 5 `/admin/models` resource.
   - Requests are scoped to the selected provider and `purpose=generation`.
   - Provider changes clear stale credential/model state before refetch.
   - No browser-side catalog, compatibility engine, or direct provider call exists.
   - Evidence: `docs/progress/M12-TASK9-MODEL-SELECTOR.md` and `docs/progress/M12-TASK9-MODEL-WIRING.md`.

4. **Safe actionable provider/capability errors**
   - Only `missing_credential`, `provider_unavailable`, and `unsupported_capability` are translated to fixed provider-local guidance.
   - Guidance uses `role="alert"` and never displays arbitrary server/upstream message content.
   - Unknown failures retain the generic administration error path.
   - Evidence: `docs/progress/M12-TASK9-PROVIDER-ERRORS.md`.

## Final RED/GREEN evidence

Representative genuine Task 9 REDs:

- Credential state: `ad6f92ff91ad35c6c2da9bd3da7ed20dedb2987f` / CI `34212072818`.
- Credential replacement: `c1217349c3ba0548445e0624591350e8df46d4a1` / CI `34217761100`; REST integration `a3a695f452d41ffdb6b3c362faa1fde6cae56bb8` / CI `34218314402`.
- Model selector: `715abf2110dc1d95e726bccd2c75d1a507ea5839` / CI `34223027930`.
- Production model wiring: `d12a293902a6acb752a0aac08fbf5d9578c1f540` / CI `34228553287`.
- Provider error states: `50da73358f5b5923c423c83e40858f3fcbe4d00e` / CI `34231571334`, with 45 Jest tests: **42 passed / exactly 3 expected failures**.

Final Task 9 implementation:

- `394da863b54f6663743c98bf8dd978e675041535`
- CI `34232581709` — `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all GREEN.
- JS verification: **15/15 suites, 45/45 tests GREEN**, plus typecheck, build, and live-gating checks.

## Final review

- Provider-error slice review `5142418396`: **0 Critical / 0 Important**.
- Whole Task 9 closeout review `5142448686`: **0 Critical / 0 Important**.
- PR #17 had **0 unresolved review threads** at closeout review time.
- The independent Superpowers subagent transport is unavailable in this runtime (outer Codex MCP probe returns 404). No false independent-review claim is made; Task 10 retains the final milestone-level independent-review gate where available.

## Security / accessibility / performance

- Secrets remain write-only from the browser perspective.
- REST operations remain same-origin and nonce authenticated.
- Server compatibility authority is preserved.
- Stable issue copy is deterministic and independent of provider exception/server message text.
- Credential fields/selectors remain labelled; credential status uses a polite live region; provider failures use alerts.
- No polling, public provider request, client catalog, or unbounded browser work was introduced.

## Exact continuation

Advance to **Task 10 — M12 integration/E2E, security, accessibility, performance, final review, exact-final-SHA CI, merge, and post-merge `main` verification**. Task 10 must exercise the completed M12 flows end to end, resolve all Critical/Important findings, keep PR #17 draft until every closeout gate is satisfied, merge only an exact CI-green head, then verify fresh post-merge `main` CI before declaring M12 complete.
