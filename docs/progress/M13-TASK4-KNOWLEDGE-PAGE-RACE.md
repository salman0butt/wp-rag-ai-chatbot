# M13 Task 4 — Knowledge Page Navigation Race Closeout

## Scope

Fresh-session Task 4 closeout review found one Important correctness issue in the top-level Knowledge source pagination path. Selected source/document/chunk requests already used latest-request correlation, but the bounded source-page request wrote directly into shared state.

A slow older `#/knowledge?page=1` response could therefore overwrite a newer `#/knowledge?page=2` response. The same missing correlation also allowed an older page failure to replace a successfully loaded newer page with the global Knowledge error state.

## TDD evidence

- `af7a97c5b43edab41fe3658da1642f21d1edad35` / CI `34391616519` — test-first checkpoint, **not RED** because lint/Prettier stopped before Jest.
- `78db64a04c4e960b377a7bb180d69402c8f0431b` / CI `34391739460` — genuine stale-success RED. Lint and TypeScript passed; Jest ran 27 suites / 64 tests with exactly one intended failure: the delayed page-1 response replaced the already-rendered page-2 state.
- `17b6ef3224b88f871ee5bc528c0cf4082db929b2` / CI `34391946670` — stale-error test-first checkpoint, **not RED** because Prettier stopped before Jest.
- `1caa472ed8da7cba4fb76d05bdf38e099800bc13` / CI `34392061167` — combined genuine RED. Lint and TypeScript passed; Jest ran 28 suites / 65 tests with 63 passing and exactly two intended failures: stale success overwrite and stale failure forcing the Knowledge error state.
- `b63e964fe4c2ad37152f8b8b7cff888ebe977712` — first guarded runner staging commit. Its custom workflow failed definition validation before any job was created; this is neither RED nor GREEN.
- `54328ea05f5211acdef82aef93cdd0574ca77a66` — repaired guarded runner staging commit. The runner guarded the exact RED ancestry and exact pre-fix `src-js/index.ts` blob before making any production change.
- `73875144415582b9b77793498840cfa88595c301` — verified production fix, committed only after runner `34392453519` passed full `npm run verify:js`.

## GREEN behavior

The production fix adds a monotonic Knowledge page generation and current-route/page checks around the bounded source-page request.

- Stale successful page responses return without mutating `currentKnowledgePage`.
- Stale page failures are suppressed rather than replacing the current successful page with the global error state.
- Leaving Knowledge invalidates in-flight page requests.
- Hash-change continuation stops when the page request is stale, preventing stale job/detail continuation and stale render.
- Initial bootstrap also stops when its captured Knowledge page becomes stale.

Guarded GREEN runner `34392453519` passed:

- engine/package checks;
- JavaScript lint;
- TypeScript typecheck;
- Jest **28/28 suites, 65/65 tests**;
- production build;
- Pinecone live-gating checks;
- Chroma live-gating checks.

The runner then removed itself and committed `73875144415582b9b77793498840cfa88595c301`.

The automatic PR CI associated with that bot-authored commit was classified `action_required`, not as a test failure. A later human-authored durable closeout head must therefore supply the required permanent exact-SHA CI evidence.

## Independent closeout review

Fresh-session independent review `5158649984` reviewed the complete Task 4 Knowledge surface and recorded:

- Critical: **0**.
- Important found: **1**, the page-navigation race above.
- Important unresolved after the TDD fix: **0**.

Review coverage included correctness, security/privacy, performance, and accessibility.

### Security/privacy

- Same-origin nonce-authenticated admin requests are preserved.
- Existing allow-listed Task 1–3 DTO boundaries remain unchanged.
- `AdminApiError` retains stable status/code only and does not retain backend message bodies.
- No source config/hash, provider credential/provider payload, job payload/idempotency/lease state, raw exception body, or unrestricted document data was added.

### Performance

- Browser source/job/document/chunk reads stay bounded at `per_page=20`.
- Latest-request checks are constant-time generation/hash comparisons.
- No polling, retry fan-out, unbounded client cache, or provider/network fan-out was introduced.

### Accessibility

- Native source/document navigation and lifecycle controls remain keyboard-operable.
- Selected navigation retains `aria-current`.
- Loading retains polite status semantics.
- Read/mutation failures retain `role="alert"`.
- Existing responsive and long-content constraints are unchanged.

## Closeout status

Task 4 behavior and independent review are complete. The remaining closeout action is to reconcile the milestone/global status ledgers and obtain all required permanent CI jobs GREEN on the exact final human-authored Task 4 head. Only then may Task 4 be marked COMPLETE and Task 5 begin.
