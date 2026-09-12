# M13 Task 8 — Integration, smoke, review and closeout

Status: **COMPLETE — MERGE GATE PENDING**

Task 8 reconciles Tasks 1-7, extends the existing real WordPress M13 smoke to cover both the administrator Playground route and representative Knowledge source route, performs final milestone review, and prepares PR #18 for exact-final-head verification and merge.

## Integration / smoke evidence

Task 8 intentionally does not invent a behavior-change RED where the required behavior already exists. The representative Knowledge capability/route assertions are verification-only coverage over already-delivered Task 1 behavior.

- Task 7 final hardening head: `5d5f734aa89a0a837c1db95a73dd34d74ad2f282` / CI `34682140441` — all permanent jobs GREEN.
- Task 8 representative administrator REST smoke head: `02b237a404fa1b2d8a4b1a62cd9c1eb252aa9bc6` / CI `34682452466` — all permanent jobs GREEN.
- `wordpress-smoke` executed activation, database, provider, knowledge, file-ingestion, WooCommerce-knowledge and M13 Playground/Knowledge REST smoke successfully.
- The M13 smoke verifies route registration, anonymous denial, administrator access to bounded Knowledge projection, bounded Playground request parsing, and fail-closed rejection of request-level provider/runtime overrides without requiring live AI credentials.

## Final milestone review

### Correctness

- Knowledge source/detail/document/chunk projections remain bounded and correlated to persisted repository authority.
- Job lifecycle controls remain constrained to existing M09 state transitions.
- Task 5 projects existing retrieval evidence instead of running a second diagnostic retrieval path.
- Task 6 executes the production M10/M11 retrieval/chat path exactly once and observes the exact retrieval result used for generation.
- Task 7 consumes only the Task 6 DTO and does not recompute ranking/scoring client-side.
- Latest-request-wins and route invalidation prevent stale async Knowledge/Playground state from replacing current state.

Finding: **0 Critical / 0 Important unresolved**.

### Security

- M13 administrator routes use `AdminCapability::can_manage`.
- REST/browser surfaces remain explicit allow-list projections.
- Playground input accepts only persisted `bot_id`, positive `source_id`, persisted `collection_id`, and one bounded UTF-8 question.
- Unknown request keys, including credentials, provider/model overrides, embedding overrides, vector-store options and retrieval-limit overrides, fail closed.
- No credential blobs, authorization headers, unrestricted config, arbitrary provider error bodies or raw exception messages are intentionally projected to the administrator UI.
- The Task 8 WordPress smoke verifies anonymous requests cannot execute representative M13 Knowledge or Playground routes.

Finding: **0 Critical / 0 Important unresolved**.

### Performance

- REST pagination and diagnostic/candidate collections remain bounded.
- Playground executes one production retrieval/generation path; no duplicate semantic/lexical retrieval, reranking or generation path is introduced.
- Browser request-generation guards are O(1) and no polling loop is added.

Finding: **0 Critical / 0 Important unresolved**.

### Accessibility

- Knowledge and Playground controls retain explicit labels and keyboard-native semantics.
- Async status/error output uses live/alert semantics as documented in Task 4/7 evidence.
- Candidate inspection uses native `details`/`summary` behavior.
- Long bounded content wraps and mobile action/disclosure targets use the documented 44px minimum sizing.

Finding: **0 Critical / 0 Important unresolved**.

### Architecture / duplication

- M13 reuses existing persistence, job, provider, vector-store, retrieval and chat authorities.
- Debug/Playground behavior remains projection/orchestration around the production M10/M11 path rather than a parallel RAG implementation.
- M21 evaluation/regression-suite behavior remains out of scope.

Finding: **0 Critical / 0 Important unresolved**.

Independent reviewer/subagent transport was unavailable during final Task 8 closeout and is not falsely claimed. The repository-approved fallback review process was used.

## Merge-gate state

At the start of closeout, exact head `02b237a404fa1b2d8a4b1a62cd9c1eb252aa9bc6` was GREEN for `php-quality`, `js-quality`, `package`, and `wordpress-smoke`; PR #18 was mergeable and had no unresolved inline review threads.

This documentation commit must itself receive exact-final-head GREEN before PR #18 is merged. After merge, fresh `main` CI must pass before M13 is marked complete.
