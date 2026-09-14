# M15 Task 9 — Integration, WordPress smoke, final review & closeout

Status: COMPLETE, pending milestone PR merge/post-merge verification.

## Permanent integration verification

Task 9 extends the existing permanent WordPress widget-surface smoke through the real database repository and production public rendering/bootstrap authority.

- `8bd5af09a435bafb3f000f42ae7cce91d86dacfb` — **GREEN COVERAGE OF EXISTING/COMPOSED M15 BEHAVIOR**, CI `34795728393`.
- Exact-head `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed.
- Real WordPress smoke creates an enabled bot with persisted M15 display rules, renders floating/embedded/fullscreen/Gutenberg surfaces, verifies shared public asset enqueue, verifies normalized presentation data reaches the public bootstrap, and rejects provider/model/retrieval/vector-store/credential leakage.
- Existing permanent exact-head JS/PHP coverage in the same CI verifies URL-rule visibility, server-fact projection boundaries, proactive triggers without chat submission, widget-local RTL semantics, localized labels, reduced motion, focus lifecycle, listener/timer cleanup, and public-safe DTO boundaries.

No production behavior change was required for this smoke extension, so no RED→GREEN production chronology is fabricated.

## Final milestone review

Independent reviewer/subagent transport was unavailable in this runtime. Repository-approved scoped fallback review was performed over the M15 branch delta and permanent verification evidence.

- Correctness: **0 unresolved Critical / 0 unresolved Important**. One TypeScript evaluator remains authoritative for display decisions; PHP normalizes/persists/projects config and trusted WordPress facts without duplicating browser evaluation.
- Security/privacy: **0 unresolved Critical / 0 unresolved Important**. Public/bootstrap DTOs are allow-listed and presentation-only; credentials, provider/model authority, embeddings, vector-store/retrieval settings, user IDs/emails/capability maps are not exposed. Click selectors/patterns/prompts/collections are bounded.
- Performance/lifecycle: **0 unresolved Critical / 0 unresolved Important**. Trigger timers/listeners are bounded and cleanup/cancellation paths are covered; no polling/unbounded observer authority was introduced.
- Accessibility/mobile/RTL: **0 unresolved Critical / 0 unresolved Important**. Native controls/accessibility names remain paired, widget-local `lang`/`dir` is explicit, CSS uses logical inline positioning, reduced-motion bypasses simulated typing, proactive open does not steal focus, and Escape restores launcher focus.
- Architecture/duplication: **0 unresolved Critical / 0 unresolved Important**. No parallel Playground/RAG, widget runtime, display evaluator, locale authority, or persistence authority was introduced.

## Verification summary

- Task 1 final domain GREEN: `e9d4adbd076333142e000a38332cfdc8fd9e8853`, CI `34755743017`.
- Task 7 final stale-save/integration coverage GREEN: `38cadbef21fd8387fb5e4bf6733cc33930536148`, CI `34793226261`.
- Task 8A localized runtime GREEN: `a875299414e709a182d7e361e328b07c9c80bc7e`, CI `34794272117`.
- Task 8B widget-local direction GREEN: `29bbe3b34ba7616d08b23c2aabfc276c61073944`, CI `34794699995`.
- Task 8B logical CSS GREEN: `b05ec97078c790b50a7cdbdfa647234bb174fa9f`, CI `34794973838`.
- Task 8C reduced-motion GREEN: `fe69aacaf5aee32341e682d2daf1c55bb3bcc888`, CI `34795234377`.
- Task 8C focus/lifecycle coverage GREEN: `4431d09f1cc3d3ca3fac5c639b50b1054b4c0424`, CI `34795395647`.
- Task 9 permanent WordPress integration GREEN: `8bd5af09a435bafb3f000f42ae7cce91d86dacfb`, CI `34795728393`.

Detailed invalid `NOT RED` / `NOT GREEN` chronology remains in the per-task M15 evidence documents and git/CI history.

## Merge gate state

Implementation, tests, Task 9 permanent smoke, final scoped review, and task evidence are complete. Remaining work is documentation reconciliation, exact-final-head CI after closeout documentation, PR creation/recovery, PR mergeability/review-thread/concurrency recheck, merge with expected-head protection, and fresh post-merge `main` CI.

## Next unfinished unit

Reconcile `docs/milestones/M15-display-rules-rtl-accessibility.md` and `docs/progress/STATUS.md`, verify the exact documentation-final branch head, open/recover the single M15 PR, satisfy merge gate, merge, verify post-merge `main`, then recover M16 and continue if safe.
