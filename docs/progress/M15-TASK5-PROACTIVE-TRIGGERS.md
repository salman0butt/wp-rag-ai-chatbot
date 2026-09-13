# M15 Task 5 — Proactive trigger coordinator

Status: COMPLETE.

## Task 5A — delay and once-only lifecycle

Status: COMPLETE.

### Scope

Add the first bounded proactive trigger to the existing M14/M15 shared widget runtime without creating a second chat path or issuing a chat request merely because the UI opens.

Required behavior:

- configured delay is bounded to 0..600000 ms;
- at most one delay timer exists per mounted coordinator;
- automatic open fires at most once;
- automatic open does not send a chat request;
- manual launcher open cancels pending proactive work;
- proactive open does not steal keyboard focus;
- coordinator cancellation/disposal clears a pending timer.

### TDD evidence

- `83bfb47c0607a834a651f65fa250af8372af61d1` — **RED**, CI `34780698077`. JavaScript package/lint/typecheck reached Jest. All 52 pre-existing suites passed; only the new proactive-delay assertions failed because the floating panel remained closed after the configured delay. The manual-cancel assertion passed trivially because no proactive timer existed yet, so it is supporting regression coverage rather than the reason this checkpoint is RED.
- `ae9eb56e99bebaafcddb22d6026f5359a39e4f0e` — introduced the bounded one-timer coordinator and defensive public-config reader after the real RED was observed.
- `df5a27c8f3d6bb610c85ef0282d54be13dff9cbb` — **NOT GREEN**, CI `34780936055`. PHP quality and package were healthy, but `js-quality` stopped at Prettier on one multiline condition in `src-js/widget-proactive.ts`; no GREEN claim is made for this checkpoint.
- `2cbceba211889c0f18a8d860f03da6169206ec76` — **GREEN**, CI `34781009718`. Exact-head `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed.
- `2f2a4fecf302d9fa49cdac140c25f43903785541` — post-GREEN regression coverage proving coordinator cancellation clears a pending delay. This is intentionally **not** relabeled as RED evidence because production behavior already existed. Exact-head CI `34781248361` passed all four permanent jobs.

### Implementation

`src-js/widget-proactive.ts` owns the bounded delay coordinator:

- one timer maximum;
- one-shot completion state;
- defensive integer delay parsing with the same 600000 ms ceiling as the persisted PHP authority;
- explicit `cancel()` cleanup.

`src-js/widget-runtime.ts` integrates that coordinator at the existing floating-widget lifecycle seam:

- eligible floating mounts start the configured delay;
- proactive open only changes the existing launcher/panel state and does not call the chat endpoint;
- proactive open does not move focus;
- manual launcher open cancels pending proactive work and preserves the existing manual focus behavior;
- embedded/fullscreen surfaces do not create a floating proactive delay.

### Review

Independent reviewer/subagent transport is unavailable in this execution runtime, so the repository-approved scoped fallback review was used and the limitation is recorded honestly.

- Correctness: 0 unresolved Critical/Important findings. The coordinator is one-shot, bounded, manually cancellable, and integrated at one existing widget seam.
- Security/privacy: 0 unresolved Critical/Important findings. Only public presentation config is read; no credentials, provider/model, retrieval, vector-store, conversation ownership, or authorization authority is introduced.
- Performance: 0 unresolved Critical/Important findings. At most one delay timer exists per coordinator; no polling/network work is added.
- Accessibility: 0 unresolved Critical/Important findings. Proactive open updates `aria-expanded`/panel visibility but deliberately does not steal focus; manual open retains the M14 focus behavior.
- Architecture/duplication: 0 unresolved Critical/Important findings. The trigger coordinator only controls presentation state and never duplicates chat/RAG authority. Remaining trigger slices will extend this same coordinator rather than add parallel trigger runtimes.

### Verification

Task 5A implementation GREEN: `2cbceba211889c0f18a8d860f03da6169206ec76`, CI `34781009718`.

Task 5A final regression head: `2f2a4fecf302d9fa49cdac140c25f43903785541`, CI `34781248361` — GREEN across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.

## Task 5B — bounded scroll trigger

Status: COMPLETE.

### Scope

Extend the same proactive coordinator with bounded scroll-percentage opening while preserving the existing floating-widget lifecycle and chat/RAG authority.

Required behavior:

- persisted/browser scroll threshold semantics stay aligned at 1..100 percent;
- the page opens once when the configured percentage is reached;
- a page with no scrollable distance does not count as 100 percent scrolled;
- scroll work is coalesced through `requestAnimationFrame` instead of evaluating every raw scroll event;
- the scroll listener is passive and is removed after completion/cancellation;
- any pending animation frame is cancelled on completion/cancellation;
- delay and scroll triggers race through the same one-shot completion path;
- automatic open sends no chat request and does not steal focus;
- manual launcher open cancels pending proactive scroll work.

### TDD evidence

- `f4b0597c3688dfe197891c64529e83587d22839b` — **RED**, CI `34781502024`. JavaScript lint and typecheck passed and Jest reached the new scroll-trigger test. All 52 pre-existing suites passed; only the intended threshold-crossing behavior failed because the widget did not yet open on scroll.
- `07f96aee635e655bf5425e07c7476d642111a2e1` — initial scroll-trigger implementation. CI `34781948326` is **NOT GREEN** because `verify:js` stopped at Prettier before behavioral verification.
- `ac3a42948fc90a1a72d6234601fb4a57843cf2de` — formatting repair; CI `34782005939` passed all four permanent jobs. Scoped review then found Important correctness/performance gaps, so this was a behavioral GREEN checkpoint but not Task 5B completion: browser normalization accepted 0 despite persisted 1..100 bounds, raw scroll handlers evaluated synchronously on every event, and zero-scrollable-distance pages were treated as fully scrolled.
- `d5d95dd3f39dcd62b798a359a472cbb87ecf35dd` — **RED**, CI `34782256899`, adding review-regression assertions for the 1-percent lower bound, animation-frame coalescing, and no-scroll-page handling. Lint/typecheck passed and all 53 existing suites passed; only the three new hardening assertions failed.
- `1d76d894fe542ead3a3f378fa10806655f5a9790` — refined **RED**, CI `34782330479`, explicitly exercising scheduled-frame behavior on a non-scrollable page. Lint/typecheck again passed and the same three intended hardening assertions failed while all existing suites remained green.
- `233887a78d7cb695ab7040138df7df6d44b7db42` — **GREEN**, CI `34782396162`. Exact-head `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed after the hardening implementation.

### Implementation

`src-js/widget-proactive.ts` remains the single proactive coordinator authority and now:

- normalizes browser scroll percentage to the persisted 1..100 contract;
- keeps at most one pending animation frame for scroll evaluation;
- uses one stable passive scroll-listener function;
- schedules scroll evaluation with `requestAnimationFrame`, coalescing burst events;
- ignores threshold opening when the document has no scrollable distance;
- cancels a pending frame and removes the listener on completion or `cancel()`;
- shares the existing one-shot `complete()` path with the delay trigger.

The existing `src-js/widget-runtime.ts` integration remains unchanged in authority: proactive opening only changes floating presentation state, does not issue a chat request, does not move focus, and manual opening cancels the coordinator.

### Review

Independent reviewer/subagent transport was unavailable in this runtime, so the repository-approved scoped fallback review was used and the limitation is recorded honestly.

Initial review of `ac3a42948fc90a1a72d6234601fb4a57843cf2de` found three Important issues: unthrottled scroll evaluation, a browser-side 0-percent bound inconsistent with the persisted 1-percent minimum, and automatic opening on a non-scrollable page. All three were converted to real failing regression tests before production fixes.

Final review of `233887a78d7cb695ab7040138df7df6d44b7db42`:

- Correctness: 0 unresolved Critical/Important findings. Bounds match persisted config, zero-distance pages stay closed, first eligible delay/scroll trigger wins once, and cancellation removes pending work.
- Security/privacy: 0 unresolved Critical/Important findings. Only normalized public presentation configuration and browser scroll geometry are used; no credentials/provider/model/retrieval/user authority is introduced.
- Performance: 0 unresolved Critical/Important findings. Scroll evaluation is passive and `requestAnimationFrame`-coalesced with at most one pending frame; listeners/frames are cleaned up deterministically.
- Accessibility: 0 unresolved Critical/Important findings. Proactive opening continues to use the existing no-focus-steal path; manual opening retains the established focus behavior.
- Architecture/duplication: 0 unresolved Critical/Important findings. The existing single coordinator/runtime seam is extended; no parallel trigger, chat, or RAG path exists.

### Verification

Task 5B final implementation: `233887a78d7cb695ab7040138df7df6d44b7db42`, CI `34782396162` — GREEN across `php-quality`, `js-quality`, `package`, and the complete `wordpress-smoke` suite.

## Task 5C — bounded inactivity trigger

Status: COMPLETE.

### Scope

Extend the same proactive coordinator with a bounded inactivity timer that resets only on a small visitor-activity event set and races through the existing one-shot completion path.

Required behavior:

- persisted/browser inactivity duration semantics stay aligned at 0..600000 ms;
- one inactivity timer is active at most;
- `pointerdown` and `keydown` reset that timer;
- the first eligible proactive trigger wins once;
- completion or cancellation clears the inactivity timer and listeners;
- automatic open keeps using the existing no-chat/no-focus-steal runtime path.

### TDD evidence

- `791dd8425a29543c8c048e1485cdece0b3a5f019` — **NOT RED**, CI `34782751155`. The intended test-only fixture existed, but `js-quality` stopped at Prettier before Jest, so no behavioral RED claim is made.
- `8ee7928e23870e37ebd594f4cb641b2d8ccb739a` — **NOT RED**, CI `34782828483`. A first formatting repair still stopped in Prettier before Jest.
- `8c10401602e16463109d4020201c6c66714287f5` — **RED**, CI `34782939387`. Package/lint/typecheck reached Jest; 54 existing suites passed and only `widget-proactive-inactivity.test.ts` failed with the three intended missing behaviors: inactivity normalization was absent, activity did not reset/open the timer, and cancellation had no inactivity listeners to remove.
- `83124518b4bc05d95d58d1d98dd9f30ef92ba7eb` — **GREEN**, CI `34783022482`. Exact-head `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all passed.

### Implementation

`src-js/widget-proactive.ts` remains the only proactive coordinator and now:

- reads `proactive.inactivity_ms` using the same bounded integer normalizer as the persisted PHP authority;
- keeps one inactivity timer and one stable reset callback;
- listens only to the bounded `pointerdown` and `keydown` activity set;
- resets the inactivity timer on those events;
- removes both listeners and clears the timer on completion/cancellation;
- shares the existing `complete()` path with delay and scroll so only one proactive opening can win.

### Review

Independent reviewer/subagent transport is unavailable in this execution runtime, so the repository-approved scoped fallback review was used and the limitation is recorded honestly.

- Correctness: 0 unresolved Critical/Important findings. Browser bounds match persisted 0..600000 semantics, resets are deterministic, and delay/scroll/inactivity converge on the same one-shot completion state.
- Security/privacy: 0 unresolved Critical/Important findings. The coordinator observes only public presentation config plus coarse local activity events; it records/transmits no input contents, user identifiers, credentials, provider/model configuration, retrieval configuration, or other private data.
- Performance: 0 unresolved Critical/Important findings. The slice adds one timer and two stable listeners at most, with deterministic cleanup and no polling/network activity.
- Accessibility: 0 unresolved Critical/Important findings. Keyboard activity only resets the inactivity deadline; it does not itself open or steal focus. Proactive opening still delegates to the established no-focus-steal presentation path.
- Architecture/duplication: 0 unresolved Critical/Important findings. The existing coordinator is extended in place; there is no second trigger runtime and no chat/RAG duplication.

### Verification

Task 5C final implementation: `83124518b4bc05d95d58d1d98dd9f30ef92ba7eb`, CI `34783022482` — GREEN across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.

## Task 5D — exit-intent trigger

Status: COMPLETE.

### Scope

Extend the same proactive coordinator with a desktop/fine-pointer exit-intent signal that opens only at the document top boundary, stays once-only, and cleans up deterministically.

### TDD evidence

- `1bcf5853791c604c9d9f3008a964944c40328421` — **NOT RED**, CI `34785172977`. The test fixture existed, but `js-quality` stopped at Prettier before typecheck/Jest, so this checkpoint is not behavioral RED.
- `48e7e230c155fddd7dfef904760a62a050afbbc2` — **RED**, CI `34785276007`. JavaScript lint and typecheck passed and Jest ran. All 55 existing suites passed; only the three new exit-intent assertions failed because `exit_intent` normalization, fine-pointer top-boundary opening, and cancellation cleanup did not yet exist.
- `82e78bcfc25496b087961f5523d1a5251d5d9a1f` — **GREEN**, CI `34785336442`. Exact-head CI passed all permanent jobs.

### Implementation

The single coordinator now:

- normalizes `proactive.exit_intent` as a strict boolean;
- enables exit intent only when `matchMedia('(hover: hover) and (pointer: fine)')` matches;
- treats only top-boundary `mouseout` with no related target as exit intent;
- routes exit intent through the same one-shot `complete()` path as delay/scroll/inactivity;
- removes the `mouseout` listener on completion/cancellation.

### Review

Independent reviewer/subagent transport is unavailable in this execution runtime, so the repository-approved scoped fallback review was used.

- Correctness: 0 unresolved Critical/Important findings. The trigger is pointer-capability gated, top-boundary constrained, once-only, and cancellable.
- Security/privacy: 0 unresolved Critical/Important findings. Only coarse pointer-exit presentation facts are observed and nothing is persisted or transmitted.
- Performance: 0 unresolved Critical/Important findings. At most one stable `mouseout` listener is installed and removed deterministically.
- Accessibility: 0 unresolved Critical/Important findings. Keyboard/touch visitors are not treated as exit-intent signals and proactive opening does not steal focus.
- Architecture/duplication: 0 unresolved Critical/Important findings. Exit intent extends the same coordinator and presentation path; it does not create chat/RAG authority.

### Verification

Task 5D final implementation: `82e78bcfc25496b087961f5523d1a5251d5d9a1f`, CI `34785336442` — GREEN across all permanent CI jobs.

## Task 5E — bounded click selector and first-visit gate

Status: COMPLETE.

### Scope

Finish the proactive-trigger matrix with delegated click matching plus a bot-scoped first-visit gate, while keeping selector input deliberately constrained and storage optional.

### TDD evidence

- `298ff06bb24bdbb9b18a0a3ac21c425b8ee81362` — **NOT RED**, CI `34785601845`. The first test-only fixture stopped at Prettier before Jest, so no behavioral RED claim is made.
- `4ae5bccb5f3aebd6d3a86a6c4db1bc07a21f95ea` — intermediate fixture-format checkpoint; its failed CI is intentionally not used as RED evidence.
- `e0c00d1813c5a73c1f3c9485f50e957523dafda5` — **RED**, CI `34785787332`. Lint and typecheck passed and Jest ran. All 56 existing suites/148 existing tests passed; only the four intended new assertions failed: first-visit normalization was absent, delegated descendant clicks did not open, no bot-scoped localStorage marker was written, and the storage-failure fallback did not preserve bot-local behavior.
- `b65f5b2ecf38a2fd4283fd414e4ef97148130771` — introduced click and first-visit behavior after the genuine RED.
- `0011f7d3897e6681ade3b62eecf1287fd1494127` — implementation formatting checkpoint.
- `c2d5b04df5dbef00ec5bf6d90ad6305dfdcbaad3` — **NOT GREEN**, CI `34786083233`. PHP/package were healthy, but `js-quality` stopped at `no-nested-ternary`/Prettier findings in the UTF-8 selector byte-length helper.
- `c60b63313556a6fc2ea5c1f6329e7802a6964c24` — **GREEN**, CI `34787752550`. Exact-head `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed.

### Implementation

`src-js/widget-proactive.ts` remains the sole proactive coordinator and now:

- normalizes `first_visit_only` as a strict boolean;
- accepts only the existing bounded safe selector grammar and enforces the 160-byte UTF-8 ceiling without relying on `TextEncoder`;
- uses delegated `documentRoot` click handling and `Element.closest()` so descendant clicks can satisfy a configured selector;
- stores a bot-scoped first-visit marker under `wp-rag-ai-chatbot:proactive-seen:<botId>` when storage is available;
- falls back to session-local bot scoping when storage access throws;
- prevents same-bot reactivation without leaking state between different bots;
- routes click completion through the same one-shot cleanup path and removes delegated listeners on completion/cancel.

### Review

Independent reviewer/subagent transport is unavailable in this execution runtime, so the repository-approved scoped fallback review was used and the limitation is recorded honestly.

- Correctness: 0 unresolved Critical/Important findings. Selector normalization, delegated matching, bot-scoped first-visit suppression, storage fallback, once-only completion, and cleanup are covered by focused tests.
- Security/privacy: 0 unresolved Critical/Important findings. Arbitrary CSS is not accepted; selectors are grammar- and byte-bounded. The first-visit marker contains only a fixed bot-scoped presence value, with no user identifiers, credentials, provider/model, retrieval, or conversation data.
- Performance: 0 unresolved Critical/Important findings. One delegated click listener at most is used and removed deterministically; no polling/network work is added. UTF-8 byte counting is linear over an already bounded selector.
- Accessibility: 0 unresolved Critical/Important findings. The click trigger observes existing user interaction and proactive opening retains the established no-focus-steal path; manual controls remain unchanged.
- Architecture/duplication: 0 unresolved Critical/Important findings. All proactive signals share one coordinator and one existing widget presentation path; no alternate chat/RAG request pipeline was created.

### Verification

Task 5E final implementation: `c60b63313556a6fc2ea5c1f6329e7802a6964c24`, CI `34787752550` — GREEN across `php-quality`, `js-quality`, `package`, and the complete `wordpress-smoke` suite.

## Task 5 final status

Task 5 is COMPLETE. Delay, scroll, inactivity, exit-intent, click-selector, and first-visit signals are bounded, cancellable, once-only, and converge on the existing floating-widget presentation authority. No proactive signal sends a chat request merely by opening the UI.

## Next unfinished unit

Task 6 — page-specific starter suggestions. Establish test-only RED proving that selected suggestions render as bounded native buttons/plain text, never raw HTML, and that selecting a starter uses the existing input/submission path exactly once rather than creating a parallel chat request path.
