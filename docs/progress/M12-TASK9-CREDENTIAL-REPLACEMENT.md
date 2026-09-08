# M12 Task 9 — Credential Replacement Checkpoint

Status: COMPLETE — bounded Task 9 credential-replacement slice

## Scope

This checkpoint closes the safe credential-replacement slice of M12 Task 9. It does not close Task 9 overall.

Implemented behavior:

- the provider screen loads only browser-safe credential metadata: `configured` and `source`;
- an administrator can enter only a new replacement credential in an empty password field;
- the browser never receives, restores, or renders an existing plaintext or ciphertext credential;
- replacement uses the existing Task 4 capability-protected provider credential REST resource through the nonce-authenticated admin API client;
- the write sends only `{ credential: <new value> }` to the selected provider route;
- after a successful write, configured/source state is refreshed from the server rather than inferred from browser state;
- provider identifiers are URI-encoded in the REST path;
- no parallel credential storage/encryption path, URL-secret path, localized secret, or credential logging path was introduced.

## TDD evidence

### Component-level RED

Commit: `c1217349c3ba0548445e0624591350e8df46d4a1`

CI: `34217761100` — FAILURE as expected for the new replacement-submit behavior. `php-quality`, `package`, and `wordpress-smoke` passed; `js-quality` failed after the new provider-settings replacement expectation was introduced before implementation.

The new test required the form to submit only the newly typed credential and clear the credential input after successful callback completion.

### Integration RED

Commit: `a3a695f452d41ffdb6b3c362faa1fde6cae56bb8`

CI: `34218314402` — FAILURE as expected before the provider-screen bootstrap persisted replacement through the existing REST contract.

The integration test required:

- initial provider credential GET with WordPress REST nonce;
- an empty secret input after load;
- PUT to `/admin/providers/{provider}/credential` containing only the newly entered credential;
- authoritative GET refresh after successful PUT;
- no newly entered credential remaining in rendered DOM after refresh.

### GREEN

Final implementation head for this slice: `998f4a4401649abca27e1dff9c4f635bc9a28c22`

CI: `34219317420` — GREEN across all repository jobs:

- `php-quality` — GREEN;
- `js-quality` — GREEN;
- `package` — GREEN;
- `wordpress-smoke` — GREEN.

## Review

Scoped Task 9 credential-replacement review: `5141026447`, anchored to `998f4a4401649abca27e1dff9c4f635bc9a28c22`.

Findings:

- Critical: 0;
- Important: 0.

The review covered correctness, credential secrecy/browser-state boundaries, nonce/capability reuse, URI handling, accessibility of the replacement field/status state, performance, and milestone-scope containment.

## Security notes

The browser-side credential representation remains metadata-only. Existing credentials are never serialized by Task 4 and are never represented by Task 9 as an input value, default value, component property, URL value, or localized bootstrap value. Only the administrator's newly typed replacement credential exists transiently in the password input and request body required for the write.

Errors continue through the admin shell's safe error boundary; this checkpoint does not claim completion of Task 9's separate actionable provider/capability error-state acceptance criterion.

## Performance notes

Provider credential state is loaded only for the selected provider screen. A replacement performs one bounded PUT followed by one authoritative GET. No public runtime path or additional dependency is introduced.

## Exact continuation

Task 9 remains ACTIVE.

Next implement capability-compatible model selection under a fresh genuine behavioral RED. The model selector must reuse the existing Task 5 server-side model/capability resource, exclude incompatible choices instead of merely disabling them client-side from an unfiltered catalog, preserve persisted server authority, and avoid introducing provider/network work on public paths.

After model selection, complete actionable accessible provider/capability error states, then perform Task 9 closeout review and exact-final-SHA CI before Task 10.
