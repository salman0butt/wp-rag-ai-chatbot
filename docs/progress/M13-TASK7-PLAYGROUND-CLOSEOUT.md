# M13 Task 7 — Playground UI closeout

Status: **COMPLETE**

Task 7 extends the existing M12 administrator shell with a bounded Playground UI that consumes only the Task 6 DTO. The browser does not own retrieval, scoring, normalization, fusion, reranking, grounding, provider/model selection, embedding selection, vector-store selection, or request-level runtime configuration.

## Delivered behavior

- labelled question form for only `bot_id`, positive `source_id`, `collection_id`, and a bounded question;
- nonce-authenticated POST through the existing administrator client to `/admin/debug/playground`;
- structured answer, citations, execution/model, latency, usage, retrieval-channel/rerank and bounded candidate sections;
- native `details`/`summary` candidate disclosure semantics;
- repository-owned safe error copy only, with generic fallback for unknown error codes and no arbitrary backend/provider message rendering;
- polite live loading status;
- latest-request-wins submission handling and route-lifecycle invalidation so stale requests cannot overwrite or resurface after navigation;
- responsive two-column desktop / one-column mobile form layout;
- wrap-safe long URLs, identifiers, candidate text and diagnostics;
- 44px mobile submit and disclosure targets.

## Existing Task 7 evidence

Earlier Task 7 screen/form/runtime and concurrency evidence remains authoritative in git/CI and `docs/progress/M13-TASK7-PLAYGROUND-CONCURRENCY.md`.

Important concurrency checkpoints include:

- latest-submission genuine RED `ff0a7e958a77ae90a12eaa69a4694df38348cef3` / CI `34679600807` → GREEN `b8d6265d8ba87d86624a8985c9be4539713514c7` / CI `34679662984`;
- explicit invalidation genuine RED `b4969c09486b887c838583535b1809f9f88b0878` / CI `34680531954` → GREEN `1ec642894735a0bfed7a427545a38c0ba2abfcc7` / CI `34680601250`;
- navigation invalidation genuine RED `94537f2af133b1c1429de2e38d8125999cb5fa98` / CI `34680762825` → intermediate `19bff6a555d994f1e202002ef2e1952ad9043b0c` **NOT GREEN** because Prettier stopped verification → GREEN `bca845488eab2bcaae71a64880f088e5871c6c5a` / CI `34681069621`.

The first Playground rendering cycle also preserves its original chronology in git/CI: `10e7bf35860e36979803119cd82159fd2a3cd987` was **NOT RED** because Prettier stopped verification before Jest; `64d6fb227ff118fedf413a5ebf70ae67eaa57ba1` / CI `34672155508` is the genuine screen-rendering RED.

## Responsive/accessibility hardening chronology

### Invalid test checkpoint — NOT RED

- SHA: `50273d9694fc2c4387bd4d3be354c501a907d48b`
- CI: `34681551478`
- Status: **NOT RED**
- Reason: JavaScript verification stopped before reliable evidence that Jest reached the intended missing responsive hook. No production implementation was added at this checkpoint.

### Genuine RED

- SHA: `ffe63b8145c0b6ef7647a254359e888e1196abfb`
- CI: `34681634157`
- Lint and TypeScript completed successfully before Jest.
- Jest ran 37 suites: 36 passed and only `src-js/playground-responsive.test.ts` failed.
- The two intended assertions failed because result and empty Playground roots did not expose the dedicated `wp-rag-ai-chatbot-playground` responsive hook.

This is a genuine behavioral RED.

### Minimal genuine GREEN

- SHA: `afafa56e6defc79800ab19ecd2fa0d60cdd795c7`
- CI: `34681766674`
- Implementation adds the dedicated responsive root class to both result and empty Playground screen states without changing the DTO, request schema, or backend authority.
- Exact-head permanent jobs all passed: `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.

### Responsive CSS hardening

- SHA: `1a89a2a62ef6be685080ddf8c32327f0a587ef09`
- CI: `34681912450`
- Exact-head permanent jobs all passed.
- CSS adds bounded-width/min-width rules, wrap-safe long diagnostic text, desktop/mobile form grids, full-width controls, vertical textarea resize, pointer/native disclosure affordance, and 44px mobile action/disclosure targets.

This is a refactor/hardening checkpoint over already-GREEN behavior, not a fabricated new RED/GREEN cycle.

### Long-content and native disclosure verification

- SHA: `5d5f734aa89a0a837c1db95a73dd34d74ad2f282`
- CI: `34682140441`
- Exact-head permanent jobs all passed: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.
- The new Jest regression verifies long bounded candidate content and long canonical URLs remain present instead of being silently client-truncated, and candidate inspection remains a native `DETAILS` / `SUMMARY` disclosure usable by keyboard.

This is verification of existing server-bounded rendering behavior after CSS hardening, not a behavior-change RED.

## Final scoped review

### Correctness

- The UI renders the Task 6 allow-listed DTO without recomputing backend ranking/scoring.
- Structured result/error/loading states remain covered.
- Submission order and route navigation cannot publish stale result/error state.
- Long bounded server content is preserved; CSS handles layout rather than mutating diagnostics.

### Security

- The browser submits only the Task 6 persisted selector/question contract.
- No credentials, provider/model overrides, embedding overrides, vector-store options, retrieval-limit overrides, or raw provider/backend exception messages are accepted or rendered.
- Unknown error codes map to repository-owned generic safe copy.

### Performance

- Candidate collections are already server bounded.
- New client behavior adds only O(1) request-generation guards and CSS/layout rules.
- No duplicate retrieval, generation, polling, or network work was introduced.

### Accessibility

- Form controls remain explicitly labelled.
- Loading uses `role="status"` plus `aria-live="polite"`; errors use `role="alert"`.
- Candidate inspection uses native keyboard-reachable `details`/`summary` semantics.
- Long diagnostic content wraps instead of forcing destructive horizontal layout.
- Mobile submit/disclosure targets use a 44px minimum height.

### Architecture / duplication

- M10/M11 retrieval/chat authority remains server-side.
- Task 7 is a projection/rendering layer only.
- No parallel Playground RAG implementation exists.

Independent reviewer/subagent transport was unavailable and is not falsely claimed. The repository-approved scoped fallback review found **0 unresolved Critical** and **0 unresolved Important** findings for Task 7.

## Closeout

Task 7 is complete. Task 8 is now the active milestone unit: recover existing M13 smoke coverage first, add only genuinely missing integration/smoke behavior under strict TDD, perform final M13 review, reconcile milestone/progress/PR state, obtain exact-final-head CI, merge only when every gate is satisfied, and verify post-merge `main` before marking M13 complete.
