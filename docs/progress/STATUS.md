# Global Status

- Completed milestones on `main`: **M00-M14**.
- Latest completed milestone on `main`: **M14 — Frontend Chatbot / Customizer**.
- M14 PR: **#19 — MERGED** at merge SHA `937ee81d55b0007147a6c764ce29d5d5fba9459b`.
- M14 exact-final PR-head CI: **`34748534203` — GREEN** at `2e8c15ba8a09ecc6a6355b8057a61e28741ed7e8`.
- M14 post-merge `main` CI: **`34750216460` — GREEN** at merge SHA `937ee81d55b0007147a6c764ce29d5d5fba9459b`.
- Current milestone: **M15 — Display Rules, Proactive Triggers, Multilingual/RTL & Accessibility**.
- M15 implementation/review/permanent smoke: **COMPLETE on feature branch; PR / merge / post-merge verification pending**.

This file is the concise recovery index. Detailed RED/GREEN chronology, invalid checkpoints, reviews, security/accessibility/performance findings, implementation notes, and CI evidence remain in milestone ledgers and `docs/progress/MXX-*` evidence files.

## M13 — COMPLETE

PR #18 merged at `a514dd658f20e3103bbe676a0eef8b00a37a23ea`; post-merge CI `34683129496` is GREEN. M13 delivered the Knowledge Manager, indexing/job lifecycle UI, protected Playground execution, and bounded RAG debugger over existing production authorities.

## M14 — COMPLETE

M14 delivered the production public chatbot surfaces and shared administrator appearance customizer while preserving the M10/M11 production retrieval/chat authorities exactly once. Task-level evidence remains under `docs/progress/M14-*`; final review has 0 unresolved Critical/Important findings.

Durable M14 references:
- `docs/milestones/M14-frontend-chatbot-customizer.md`
- `docs/progress/M14-TASK1-APPEARANCE-CONFIG.md` through `docs/progress/M14-TASK10-CLOSEOUT.md`
- `docs/superpowers/specs/2026-09-12-m14-frontend-chatbot-customizer-design.md`
- PR #19 — merged.

## M15 — IMPLEMENTATION COMPLETE / MERGE PENDING

M15 adds deterministic bot-scoped display rules and presentation facts, bounded proactive triggers, page-aware starter suggestions, protected admin configuration/preview, bounded English/Urdu localization, widget-local RTL semantics, logical CSS, reduced-motion behavior, and focus/lifecycle hardening without introducing parallel widget/RAG/config authorities.

Final scoped fallback review: **0 unresolved Critical / 0 unresolved Important** across correctness, security/privacy, performance/lifecycle, accessibility/mobile/RTL, and architecture/duplication. Independent reviewer transport was unavailable and that limitation is recorded durably.

Key exact-head evidence:
- Task 1 domain: `e9d4adbd076333142e000a38332cfdc8fd9e8853`, CI `34755743017` — GREEN.
- Task 7 integration/stale-save: `38cadbef21fd8387fb5e4bf6733cc33930536148`, CI `34793226261` — GREEN.
- Task 8 localized runtime: `a875299414e709a182d7e361e328b07c9c80bc7e`, CI `34794272117` — GREEN.
- Task 8 widget-local direction: `29bbe3b34ba7616d08b23c2aabfc276c61073944`, CI `34794699995` — GREEN.
- Task 8 logical RTL CSS: `b05ec97078c790b50a7cdbdfa647234bb174fa9f`, CI `34794973838` — GREEN.
- Task 8 reduced motion: `fe69aacaf5aee32341e682d2daf1c55bb3bcc888`, CI `34795234377` — GREEN.
- Task 8 focus/lifecycle: `4431d09f1cc3d3ca3fac5c639b50b1054b4c0424`, CI `34795395647` — GREEN.
- Task 9 real WordPress integration: `8bd5af09a435bafb3f000f42ae7cce91d86dacfb`, CI `34795728393` — GREEN.

M15 durable references:
- `docs/milestones/M15-display-rules-rtl-accessibility.md`
- `docs/progress/M15-TASK1-DISPLAY-RULES.md`
- `docs/progress/M15-TASK3-SERVER-CONTEXT.md`
- `docs/progress/M15-TASK4-RUNTIME-VISIBILITY.md`
- `docs/progress/M15-TASK5-PROACTIVE-TRIGGERS.md`
- `docs/progress/M15-TASK6-STARTER-SUGGESTIONS.md`
- `docs/progress/M15-TASK7-ADMIN-RULES-EDITOR.md`
- `docs/progress/M15-TASK8-LOCALIZATION-RTL.md`
- `docs/progress/M15-TASK9-CLOSEOUT.md`
- `docs/superpowers/specs/2026-09-13-m15-display-rules-rtl-accessibility-design.md`
- `docs/superpowers/plans/2026-09-13-m15-display-rules-rtl-accessibility.md`

Current unfinished work: verify exact closeout-documentation branch-head CI, create/recover the single M15 PR, recheck mergeability/reviews/concurrency, merge with expected-head protection, verify fresh post-merge `main` CI, then recover M16 and continue if safe.
