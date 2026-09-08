# M12 Task 8 — Selected Bot Editor Isolation

Status: **COMPLETE SLICE; TASK 8 REMAINS ACTIVE**

## Scope

This checkpoint closes the Task 8 requirement that switching bot records must not leak unsaved editor state between records.

The bot-management route now uses the existing hash router as the selected-record boundary:

- `#/bots/<id>` identifies the selected bot.
- Bot IDs are URL-encoded when links are emitted and decoded defensively when read.
- Selection is resolved only against the current server-derived bot page.
- Only one edit form is mounted at a time.
- Hash changes rerender from the cached server-authoritative page without an additional network request.
- Unsaved DOM draft values are therefore discarded when a different record is selected instead of becoming a second source of truth.
- Plain `#/bots` and unknown/malformed IDs safely fall back to the first bot in the current protected page, preserving existing behavior.

## Strict TDD evidence

The first two test checkpoints were not counted as behavioral RED because repository formatting gates stopped before Jest:

- `91111d5e1f8902bcf105d585b75f1301df005790` — initial isolation test; CI `34188050527` stopped at Prettier.
- `1e6c1abb67d0d8c600b7ba467182d5b7eb6cda6b` — formatting-only correction; CI `34188157662` still stopped at one Prettier finding.

Genuine behavioral RED:

- `a519a21e177a5db6d3dbe588ad36f9f54fc46588`
- CI `34188306529`
- package lint, JavaScript lint, and TypeScript passed.
- Jest ran 34 tests: **33 passed / exactly 1 failed**.
- The new isolation test failed for the intended reason: it expected one mounted edit form but received two (`bot-a` and `bot-b`).

Minimum production implementation:

- `c962315679dd6c5fc0e5308a902169243c705038` — selected-record hash routing and single mounted editor.
- CI `34188486409` stopped at one Prettier-only source finding before Jest, so it was not counted as GREEN.

Verified GREEN:

- `db3bb18813e558da1e55e8d6e13425602e2f69ab` — formatting-only correction.
- CI `34188611700` passed `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.
- JavaScript verification includes **34/34 Jest tests passing**.
- WordPress smoke passed environment startup, activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and environment shutdown.

## Review

Scoped correctness/security/accessibility/performance review: **`5137470693`**.

Findings: **0 Critical / 0 Important**.

Review conclusions:

- Correctness: selection is deterministic, server-derived, and unsaved values cannot cross record boundaries.
- Security: no new trust boundary or secret exposure; selected IDs are matched only against protected REST results.
- Accessibility: bot records are native links and keyboard reachable.
- Performance: record switching adds no request and reduces edit-form DOM from one form per bot to one selected form.

Minor/deferred: expose clearer selected-record context such as `aria-current` during the remaining Task 8 navigation/accessibility pass.

## Task 8 state after this checkpoint

Already complete within Task 8:

- paginated bot list and explicit empty state foundation;
- accessible create-editor foundation and safe submit containment;
- persisted create with server-authoritative refresh;
- invalid-create validation and accessible error focus;
- persisted edit with optimistic `version` handling and server-authoritative refresh;
- selected-record lifecycle with unsaved editor-state isolation.

Task 8 remains active.

## Exact next unfinished action

Continue Task 8 under strict TDD with the smallest behavioral RED proving destructive bot removal requires an explicit confirmation before the Task 3 delete contract is invoked, and that a confirmed deletion refreshes from server-authoritative bot state. Then finish real pagination/hash navigation, clearer selected-record accessibility context, narrow/mobile WordPress-admin usability, exact-final-SHA full CI, and final independent correctness/security/accessibility/performance closeout review before advancing to Task 9.
