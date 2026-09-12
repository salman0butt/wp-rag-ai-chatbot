# M13 Task 4 — Selected-source Detail UI Slice

Status: **COMPLETE BOUNDED SLICE — TASK 4 REMAINS ACTIVE**

## Scope

This slice connects the existing Task 4 knowledge manager to the allow-listed Task 2 source-detail/document contracts and closes a persisted-ID compatibility defect discovered during verification.

Delivered behavior:

- selected persisted source routes load the allow-listed source detail through the existing nonce-authenticated same-origin admin client;
- the first bounded document page is loaded from the existing Task 2 endpoint (`page=1&per_page=20`);
- selected source/detail/document state is cleared when selection changes or Knowledge is left, preventing stale cross-source rendering;
- the source list accepts the persisted numeric IDs emitted by the server DTO while hash-route IDs remain strings;
- selected-source matching normalizes only the comparison boundary with `String(item.id)` and keeps URI-encoded hash navigation;
- no raw source config/hash, raw document content/metadata/hash, provider payload, credential, or unbounded collection is introduced into browser state.

## Strict TDD evidence

- `6302f3786946b05be27d4fbb15979c694a82b947` / CI `34286857568` is **not RED**: `npm run verify:js` stopped at a Prettier error before Jest.
- Test-only formatting checkpoint `7c01f3fb77b73c4bea47b27e410114fa239b854c` / CI `34292237492` is the genuine behavioral RED. Lint and TypeScript typecheck passed; Jest ran 50 tests with 49 passed / exactly 1 failed. The selected numeric DTO ID `17` incorrectly fell back to `Support Articles` instead of selecting `Product Catalog` because the route selection value was the string `"17"`.
- GREEN implementation `65ff1f83f1a232c2bedf510cce8117fceb4e2c0f` / CI `34292560442`: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed.

## Root cause and correction

Task 1/2 persistence projects source identifiers as numeric values, while hash parsing correctly produces strings. The UI source-item type and equality check had assumed string-only IDs. The minimum correction widens the browser-side source ID type to `string | number` and compares `String(item.id)` to the selected route ID. Server contracts, pagination, detail requests, and fallback behavior are otherwise unchanged.

## Review

Scoped review `5148247521`: **0 Critical / 0 Important**.

- Correctness: numeric persisted IDs now correlate with string-valued hash selection.
- Security: no new request/data boundary and no sensitive/unbounded fields introduced.
- Accessibility: existing `aria-current` selection now marks the actual numeric DTO item.
- Performance: selection remains one linear scan of the bounded source page.

Separate subagent transport was unavailable in this runtime, so this is a repository-scoped review rather than a claimed independent-subagent review. Final Task 4/M13 independent review remains required at closeout.

## Exact continuation

Task 4 remains active. Next establish a fresh genuine Jest RED for **selected-document bounded chunk inspection**, consume only the existing Task 2 chunk DTO/page contract, keep navigation/state server-authoritative and bounded, then integrate Task 3 job inventory/actions and stable lifecycle-error UI states before final accessibility/responsive review and Task 4 closeout.
