# M14 — Frontend Floating/Embedded Chatbot & Complete Visual Customizer

Status: COMPLETE pending merge and post-merge `main` verification.

## Goal
Deliver the production public chatbot surfaces and shared live appearance customizer.

## Dependencies
M11 backend chat, M12 admin shell.

## In Scope
Floating launcher; embedded/fullscreen/mobile; shortcode; Gutenberg block; bounded progressive message presentation; safe links/citations; loading/errors/retry/session/history; normalized appearance schema; live preview; bounded theme support.

## Security-driven scope clarification
Arbitrary custom CSS/raw HTML is intentionally excluded from M14 despite earlier aspirational wording because the reviewed public/browser boundary is allow-listed and must not become an unrestricted injection channel. Advanced proactive rules remain M15; commerce actions remain M18.

## Architecture
Public chatbot surfaces are adapters over one `PublicWidgetBootstrap` mount authority and one public widget runtime. Public chat resolves persisted production configuration server-side and reuses the existing M10/M11 retrieval/generation composition rather than duplicating semantic/lexical retrieval, fusion, reranking, grounding, prompt construction, memory, citations, provider/model selection, embedding selection, or vector-store selection. Administrator preview and public runtime reuse the same normalized appearance authority.

## Acceptance Criteria
- No provider secrets or runtime authority in bundles/HTML/public API.
- Floating, embedded, fullscreen, shortcode, and Gutenberg surfaces use the shared runtime.
- Desktop/mobile behavior remains bounded and accessible.
- Long answers/URLs/source cards do not create unbounded presentation.
- Public assets load conditionally.
- Appearance preview uses the same normalized application authority as runtime.
- Real WordPress smoke covers widget surfaces.
- Final exact-head CI, review, merge, and post-merge `main` verification are required before marking the milestone fully integrated.

## Tasks
- [x] Task 1 — shared normalized appearance configuration.
- [x] Task 2 — bot-scoped appearance persistence and public-safe widget configuration.
- [x] Task 3 — protected administrator appearance save/read contracts.
- [x] Task 4 — public chat request/runtime composition, abuse controls, owner-scoped conversation history, REST integration, and review closeout.
- [x] Task 5 — conditional public asset/bootstrap/shortcode mount.
- [x] Task 6 — floating launcher/panel UI.
  - [x] Task 6A — deterministic accessible responsive launcher/panel shell.
  - [x] Task 6B — non-streaming public conversation submit/loading/error/retry behavior.
  - [x] Task 6C — safe message/citation/link/history presentation and Task 6 closeout.
- [x] Task 7 — bounded streaming/simulated public chat UX.
- [x] Task 8 — administrator visual customizer/live preview.
- [x] Task 9 — block/direct/fullscreen embedding surfaces.
- [x] Task 10 — milestone integration, real WordPress smoke, accessibility/mobile/performance/security review and closeout.

## TDD / Implementation Evidence
Detailed RED/GREEN chronology, invalid NOT RED/NOT GREEN checkpoints, focused review findings, and fixes are preserved under `docs/progress/M14-*`.

Key final implementation evidence:
- Task 4 production path: `e850863d5487ee7603547413bdb9c667c8d04b8e` / CI `34717786952` GREEN.
- Task 5 public mount/bootstrap: `18436c394f826e83885cbb43bff30b509a00a205` / CI `34722486144` GREEN.
- Task 6 final widget conversation/presentation: `ddd4de2e1cd1af2ada84a0f703226377c958e030` / CI `34729280110` GREEN.
- Task 7 progressive presentation: `82262b96c6088917d67e44922d007669c4e88065` / CI `34734183151` GREEN.
- Task 8 customizer/live preview: `362d47c65d4cafd43086e2862d606595341baa99` / CI `34741614657` GREEN.
- Task 9 package/build GREEN: `02962ed49882089548dffb813a75ade048bb9abb` / CI `34747631044`; real WordPress widget-surface smoke GREEN at `2faf9677da3e8759021e22324b3d1f005f5a33de` / CI `34747870930`; repaired regression-test head `a4150af0c29d46a23f24bbb9452079ca970c4224` / CI `34748133442` GREEN.
- Integrated pre-closeout branch head: `d170d56f725b6dabad704b9cad0fdbcba68f93a7` / CI `34748315547` GREEN across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.

Task 10 introduces no production behavior change, so no new behavioral RED/GREEN cycle is appropriate. It is a verification/review/documentation unit. Evidence: `docs/progress/M14-TASK10-CLOSEOUT.md`.

## Integration / Visual Verification
The permanent WordPress smoke job includes activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, Playground REST, and widget-surface tests. The widget-surface smoke verifies the production shortcode/direct/embed/fullscreen/Gutenberg adapters in a real WordPress environment. Existing Tasks 6–9 tests cover responsive launcher/panel behavior, always-open embedded/fullscreen surfaces, Escape/focus behavior, bounded progressive presentation, safe citation/link rendering, finite transcript history, and shared appearance application.

## Security Review
Final scoped security review has **0 Critical / 0 Important unresolved**. The browser request/config boundary remains closed: no provider credentials, provider/model overrides, embedding/vector-store configuration, retrieval limits, raw HTML, arbitrary CSS, or unrestricted bot configuration are accepted/exposed. Public abuse controls execute before expensive runtime work. Administrator appearance persistence remains capability protected and allow-listed.

## Accessibility / Mobile Review
Final scoped accessibility/mobile review has **0 Critical / 0 Important unresolved**. Floating mode uses visible native controls, named dialog semantics, focus transfer/restoration, Escape dismissal, and polite status messaging. Embedded/fullscreen surfaces remain always-open adapters rather than inheriting floating-only close behavior. Customizer controls are labelled native bounded controls.

## Performance Review
Final scoped performance review has **0 Critical / 0 Important unresolved**. Assets remain conditional; one public chat request is in flight per widget; citations/transcript history are bounded; progressive presentation uses one active timer and at most 48 reveal ticks; live appearance preview is local until explicit save.

## Code Review
Per-task fallback reviews resolved all recorded Important findings. At Task 10 recovery PR #19 had no unresolved inline review threads. Independent-review transport remained unavailable during connector-only runs, so the repository-approved scoped fallback review was used and this limitation is recorded in the task evidence.

## Fresh Verification
Pre-closeout exact head `d170d56f725b6dabad704b9cad0fdbcba68f93a7` passed CI `34748315547` across all permanent jobs. The documentation-only Task 10 closeout commits require a new exact-final-head GREEN run before merge.

## Durable Evidence
- `docs/progress/STATUS.md`
- `docs/progress/M14-TASK1-APPEARANCE-CONFIG.md` through `docs/progress/M14-TASK10-CLOSEOUT.md`
- `docs/superpowers/specs/2026-09-12-m14-frontend-chatbot-customizer-design.md`
- `docs/superpowers/plans/2026-09-13-m14-task7-streaming-simulated-typing.md`
- `docs/superpowers/plans/2026-09-13-m14-task8-admin-customizer-live-preview.md`
- `docs/superpowers/plans/2026-09-13-m14-task9-embed-surfaces.md`
- PR #19 — active milestone branch and merge gate.

## Completion Gate
Task implementation/review is complete. Remaining integration steps are mechanical evidence gates:
1. exact-final-documentation-head CI GREEN;
2. PR remains mergeable with no unresolved Critical/Important findings or review threads;
3. merge PR #19 with expected-head protection;
4. recover the new default-branch SHA;
5. verify fresh post-merge `main` CI;
6. update durable global status to M14 COMPLETE and proceed to M15 only after post-merge verification is green.

## Next Milestone
M15 — Display Rules/RTL/Accessibility.
