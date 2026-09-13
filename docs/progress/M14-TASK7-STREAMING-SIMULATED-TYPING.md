# M14 Task 7 — Streaming / Simulated Typing UX

Status: **COMPLETE**

## Scope

Task 7 adds bounded progressive public-chat presentation without creating a second retrieval, generation, provider-selection, or persistence path. The browser continues to issue exactly one request through the established M14 Task 4 public REST authority and progressively presents the completed normalized response.

The implementation plan is `docs/superpowers/plans/2026-09-13-m14-task7-streaming-simulated-typing.md` and is auto-approved under scheduled mode.

## Architecture decision

M11 owns provider-neutral application streaming contracts, ordering, cancellation, and bounded stream normalization. Its closeout explicitly leaves public REST/widget transport to later milestones. M14 Task 4 exposes the production M10/M11 composition through the existing completed public JSON response, and no reviewed public incremental transport seam currently projects normalized M11 stream events to the browser.

Task 7 therefore retains the plan's bounded simulated-typing fallback rather than inventing SSE, a provider-specific browser stream, a second generation request, or a parallel public RAG path. Real incremental transport may replace this presentation layer only after a separately reviewed public transport seam exists; it must still reuse the same production orchestration exactly once.

## Delivered behavior

- Exactly one public `/chat` request is made for one user submission.
- The completed assistant answer is presented progressively in one assistant message container.
- Presentation is bounded to at most 48 reveal ticks with a 24 ms interval between scheduled ticks.
- UTF-16 surrogate pairs are not split across reveal boundaries.
- Repeated partial announcements are suppressed with `aria-live="off"`; the completed answer returns to polite announcement semantics.
- A concise `Assistant is typing…` status is exposed while progressive presentation is active.
- Copy and citation/source controls are attached only after the full answer is visible.
- Closing the panel cancels pending presentation timers, restores the send control, clears typing status, and prevents stale timer work from resuming after reopen.
- Assistant/model/citation strings continue to render through text nodes; citation links remain restricted to `http:` / `https:`.
- The browser receives no provider credentials or provider/model/embedding/vector/retrieval selection authority.

## TDD evidence

### Primary behavioral RED

- Test-only head: `0a018e848d6087291761aedac2e46037d86bc260`
- CI: `34729760794`
- Classification: **genuine RED**.
- JavaScript lint/typecheck reached Jest normally; the new simulated-typing behavior failed because the widget still exposed the completed assistant response immediately instead of progressively presenting it. Unrelated permanent jobs remained healthy.

### Cancellation / Unicode regression RED

- Test-only head: `9fb1b2c8e15f86630b85eff4abc14ca92a0ff644`
- CI: `34732327263`
- Classification: **genuine RED**.
- The focused Task 7 tests reached Jest and demonstrated the missing UTF-16-safe progressive boundary and stale-presentation cancellation behavior.

### Implementation chronology and invalid checkpoints

- Implementation head `78cee1885406502e67810c1c2cacc9139e350e1b` / CI `34732421653`: **NOT GREEN** and the JavaScript failure is **NOT RED** because Prettier stopped verification before Jest at the Task 7 `setTimeout` formatting.
- Formatting repair `10a36bc469d50a117474600c03feff5c12b7f0fc` / CI `34734096420`: **NOT GREEN** and **NOT RED** because the GitHub-native full-file replacement omitted the final newline and Prettier again stopped before Jest.
- Serialization-only newline repair `82262b96c6088917d67e44922d007669c4e88065` restores the exact intended source formatting. A compare against `78cee188...` confirms the net production-code change is only the Prettier-required multiline `setTimeout` formatting; no behavioral drift was introduced by the repairs.

## GREEN verification

Exact implementation head `82262b96c6088917d67e44922d007669c4e88065` / CI `34734183151` is **GREEN** across all permanent jobs:

- `php-quality` — SUCCESS;
- `js-quality` — SUCCESS, including ESLint, Prettier, TypeScript, Jest, dependency audit, provider gating, and vector-store gating;
- `package` — SUCCESS;
- `wordpress-smoke` — SUCCESS.

The focused Task 7 suite verifies progressive presentation, one backend request, delayed completion controls, UTF-16-safe reveal boundaries, cancellation on close, no stale resumption after reopen, and one assistant container.

## Review

Independent reviewer transport was attempted after implementation but remained unavailable because the native reviewer/Codex MCP tunnel returned HTTP 404. The repository-approved scoped fallback review was therefore performed and this limitation is recorded rather than represented as independent review.

- Correctness: **0 Critical / 0 Important unresolved**. One request produces one bounded presentation sequence; cancellation clears timer work and does not create an additional assistant response.
- Security/privacy: **0 Critical / 0 Important unresolved**. Progressive rendering uses the existing safe text/citation projection; no raw HTML, credentials, runtime selectors, provider selectors, or additional server authority is introduced.
- Architecture/duplication: **0 Critical / 0 Important unresolved**. Task 7 is presentation-only over the Task 4 REST result. The existing M10/M11 production composition executes exactly once. No SSE/provider-specific/parallel generation pipeline was introduced.
- Performance: **0 Critical / 0 Important unresolved**. At most 48 scheduled reveal ticks are created for a completed response; cancellation clears the active timer and no extra network/provider call occurs.
- Accessibility: **0 Critical / 0 Important unresolved**. Repeated partial chunks are not pushed through a polite live region; completion restores polite semantics and the existing accessible status/dialog/close behavior remains intact.

## Durable next work

Task 7 is complete. Continue M14 Task 8 — administrator visual customizer/live preview. Reuse the shared normalized `AppearanceConfig`, the protected Task 3 appearance REST authority, and the same runtime appearance projection so preview and public runtime cannot diverge into separate schemas.
