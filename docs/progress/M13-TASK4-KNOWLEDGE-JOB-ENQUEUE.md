# M13 Task 4 — Knowledge Job Enqueue UI Evidence

## Scope

This bounded Task 4 slice integrates the existing Task 3 enqueue contract into the Knowledge admin UI without changing the server contract.

The UI submits only the existing validated indexing identifiers:

- `document_key`
- numeric `source_id`
- `collection_id`
- `configuration_id`
- `generation`

Successful enqueue is followed by a server-authoritative refresh of `GET /admin/knowledge/jobs?page=1&per_page=20`. The browser does not retain optimistic queue state.

## TDD evidence

The initial test-only checkpoint was not counted as RED because formatting/style verification stopped before Jest.

Genuine RED was established at `99ff3bea50c89df4ec135852932c0529894b3fe4` using verification probe `34326208932`:

- engine checks passed;
- package lint passed;
- JavaScript lint passed;
- TypeScript typecheck passed;
- the enqueue-specific Jest step alone failed because the production enqueue form/action did not exist.

Minimum GREEN was implemented at `a0f185bd3b17f62833d6e799e16bafacbfd32f54`. Before that commit was pushed, the full `npm run verify:js` gate passed in runner `34326380942`.

The transient RED/GREEN verification workflows were removed from the product tree after they had served their verification purpose.

## Exact-head verification

Clean implementation head `38e44dd72246713bbe97bfb0deb3a79749c01843` passed permanent CI run `34326516720`:

- `php-quality`: GREEN;
- `js-quality`: GREEN, including `npm run verify:js` and live/package gating;
- `package`: GREEN;
- `wordpress-smoke`: GREEN, including environment startup, activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and cleanup.

## Review

Scoped correctness/security/accessibility/performance review found **0 Critical / 0 Important** findings.

Correctness:

- the request shape matches the existing Task 3 identifier-only contract;
- `source_id` is projected as a number;
- successful enqueue is followed by a bounded authoritative inventory refresh;
- cancel/retry behavior is unchanged.

Security/privacy:

- the existing same-origin nonce-authenticated admin client is reused;
- no source config, credentials, raw document data, raw provider payload, queue payload internals, idempotency keys, or lease/lock data is added to browser state;
- server validation remains authoritative.

Accessibility:

- the enqueue UI uses a native form, native submit button, and explicitly associated labels;
- all five inputs are required, with `source_id` using a numeric input and minimum value of 1;
- the controls are keyboard-operable without custom key handling.

Performance/state:

- enqueue performs one mutation followed by one bounded `per_page=20` inventory read;
- no unbounded client cache or polling loop is introduced.

Independent reviewer-subagent transport was unavailable in this runtime, so no independent review result is fabricated. Final Task 4 independent closeout review remains required before Task 4 can be declared complete.

## Continuation

Task 4 remains ACTIVE. The exact next slice is a fresh Jest RED for stable `invalid_transition` and sanitized enqueue/cancel/retry mutation-error presentation without exposing raw exceptions or provider payloads. After that GREEN slice, finish explicit loading/empty/error behavior, constrained-width and long-content responsive coverage, keyboard/accessibility closeout, final Task 4 correctness/security/accessibility/performance plus independent review, and exact-final-SHA CI before advancing to Task 5.
