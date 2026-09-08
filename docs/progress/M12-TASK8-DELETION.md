# M12 Task 8 — Confirmed Bot Deletion Checkpoint

Status: **COMPLETE SLICE — TASK 8 REMAINS ACTIVE**

## Scope completed

This checkpoint adds the destructive bot-management behavior required by Task 8 without changing the existing Task 3 server contract:

- the currently selected server-derived bot exposes a native `Delete bot` button;
- deletion requires explicit browser confirmation;
- cancelling confirmation performs no mutation or refresh request;
- confirming calls the existing capability-protected `DELETE /admin/bots/{id}` resource through the same-origin typed admin client and WordPress REST nonce header;
- after a successful delete, the UI reloads `/admin/bots?page=1&per_page=20` and renders only the refreshed server-authoritative state;
- mutation failures fail closed into the existing safe admin error state;
- bot identifiers are URL-encoded before being placed in the item route.

The Task 3 deletion contract is identifier-only. This UI therefore does not invent an optimistic-version or request-body contract for DELETE.

## Strict TDD evidence

### Static-only checkpoints that do not count as behavioral RED/GREEN

- `11c8dce6d04a6f9df7423b280d3ec46b51248faa` / CI `34191957383`: the new deletion test was stopped by Prettier before Jest. This is not behavioral RED.
- `5df8d68a29cb5027838977fb88930586dbcd94cf` / CI `34192326208`: the minimum production behavior was stopped by ESLint `no-alert` plus a Prettier newline finding before Jest. This is not GREEN.

### Genuine RED

- Test-format correction: `158c9f17c4c1020d06919b58782d8fe727589835`.
- CI: `34192114972`.
- JS lint and TypeScript passed, then Jest ran **35 tests with exactly 1 failure**.
- The new `bot-delete-persistence` test failed because `button[data-delete-bot-id="bot-existing"]` did not exist.
- Existing tests remained green: **34 passed / 1 failed**.

The RED proves the missing user-visible destructive action rather than a formatting, typing, or harness failure.

### GREEN

- Minimum production behavior: `5df8d68a29cb5027838977fb88930586dbcd94cf`.
- Formatting/lint-only correction preserving behavior: `e0d36cc634ff27cdf2e9892d348276e76e7121bc`.
- Exact-head CI: `34192469533`.
- `js-quality`: GREEN, including **9/9 suites and 35/35 Jest tests**, TypeScript, lint, build, live-provider/vector gating, and package assertion.
- `php-quality`: GREEN.
- `package`: GREEN.
- `wordpress-smoke`: GREEN across activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and cleanup.

## Review

Scoped Task 8 destructive-deletion review: PR review `5137911125`, anchored to exact implementation head `e0d36cc634ff27cdf2e9892d348276e76e7121bc`.

Findings:

- Critical: **0**
- Important: **0**

Correctness review confirmed cancellation prevents mutation, confirmation targets only the selected persisted bot, and successful deletion is followed by authoritative list reload. Security review confirmed reuse of same-origin nonce-authenticated transport, URL-encoded bot IDs, and no credential/secret exposure. Accessibility review confirmed a native `type="button"` destructive control plus an explicit blocking confirmation boundary. Performance review confirmed zero network work on cancellation and only one DELETE plus one bounded page refresh on confirmation.

The previously deferred selected-record context improvement (for example `aria-current`) remains part of the next Task 8 navigation/accessibility slice and is not a regression introduced here.

## Files changed in this slice

- `src-js/bot-delete-persistence.test.ts`
- `src-js/index.ts`
- this progress checkpoint

## Exact next unfinished Task 8 work

Continue Task 8 under strict TDD with **real bot-list pagination/hash navigation and clearer selected-record accessibility context**. The next slice should make the existing page contract navigable without browser-owned data authority and expose selected-record state (for example `aria-current`) while preserving unsaved-state isolation. After that, complete narrow/mobile admin usability and run the final Task 8 correctness/security/accessibility/performance closeout review before starting Task 9.
