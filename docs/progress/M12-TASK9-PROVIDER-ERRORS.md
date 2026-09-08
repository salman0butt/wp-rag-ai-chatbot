# M12 Task 9 — Provider Error States

## Scope

This checkpoint completes the provider/capability error-state slice of M12 Task 9. The provider settings screen now translates only the stable server error codes `missing_credential`, `provider_unavailable`, and `unsupported_capability` into deterministic provider-local guidance.

Arbitrary REST/provider/upstream messages remain outside browser state and DOM output. Unknown errors continue through the existing generic administration error path.

## Design decision

The server remains authoritative for provider/model capability. The browser does not create a provider catalog, compatibility engine, or provider-network path.

`AdminApiError` already retained only HTTP status and a stable code. Task 9 therefore adds a narrow stable-code mapper and fixed UI copy rather than accepting server error text. Recognized model-resource failures remain on the provider screen with `role="alert"`; any other failure is rethrown to the existing generic error boundary.

## Strict TDD evidence

### Formatting checkpoints — not RED

- `120d7152043d708869ec826374c6f7a764206f91` / CI `34231222903` stopped at Prettier before Jest and is **not** counted as RED.
- `f49534e7b89eb0fdfd1af402ca699a5a172e166a` / CI `34231401090` also stopped at formatting before Jest and is **not** counted as RED.

### Genuine RED

- Commit: `50da73358f5b5923c423c83e40858f3fcbe4d00e`
- CI: `34231571334`
- Lint and TypeScript typecheck passed.
- Jest ran 45 tests: **42 passed / 3 failed**.
- The only failures were the three newly added stable provider-code cases because `[data-provider-settings] [role="alert"]` did not yet exist.
- Each test injected a server message containing `sk-should-never-render` and required it to remain absent from the DOM.

### GREEN

- UI copy/issue contract: `ea6aba031b199579838767c582bc0d20bf7ceb60`
- Verified implementation: `394da863b54f6663743c98bf8dd978e675041535`
- CI: `34232581709`
- Jest: **45/45 passed** across 15 suites.
- JS lint, typecheck, build, Pinecone/Chroma/provider/Qdrant live-gating and package assertion passed.
- `php-quality`, `package`, and the complete `wordpress-smoke` job also passed.

## Security and correctness

- Only the three stable server codes are translated into provider-local messages.
- Server/upstream `message` fields are discarded by the existing `AdminApiError` boundary and are never rendered.
- No credential plaintext or ciphertext is read, logged, or rehydrated.
- Recognized failures clear model choices for the selected provider and do not leak choices across provider routes.
- Unknown errors fail closed through the existing generic admin error state.
- A previous `missing_credential` condition is cleared and the model resource is retried after a successful credential replacement without changing the established replacement flow for already-configured providers.

## Accessibility and performance

- Provider-local error guidance uses `role="alert"`.
- Existing credential labels, password semantics, and credential status live region remain intact.
- No polling, extra provider calls, browser-to-provider networking, or unbounded processing was introduced.

## Review

- Scoped review: `5142418396` — **0 Critical / 0 Important**.
- Final Task 9 closeout review: `5142448686` — **0 Critical / 0 Important**.
- PR #17 had 0 unresolved review threads at closeout review time.
- The separate Superpowers subagent transport was unavailable in this runtime (outer Codex MCP probe returned 404); no false independent-review claim was made.

## Continuation

Task 9 behavior is complete after credential-state security, credential replacement, server-supplied model selection, production Task 5 model-resource wiring, and stable provider/capability error states. After durable ledger reconciliation and exact-final-head CI, the next work unit is Task 10 — M12 integration/E2E, security, accessibility, performance, final independent review where available, merge, and post-merge `main` verification.
