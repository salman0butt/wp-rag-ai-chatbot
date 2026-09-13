# M15 Task 5 — Proactive trigger coordinator

Status: IN PROGRESS.

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

## Next unfinished unit

Task 5B — scroll trigger. Establish test-only RED for bounded threshold crossing, one-shot behavior/no duplicate open, listener cleanup/cancellation, and bounded scroll calculation. Extend the same proactive coordinator and the same widget-runtime integration seam; do not create a second trigger/chat path.
