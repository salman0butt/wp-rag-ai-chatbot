# Global Status

- Completed milestones on `main`: **M00-M15**.
- Latest completed milestone on `main`: **M15 — Display Rules, Proactive Triggers, Multilingual/RTL & Accessibility**.
- M15 PR: **#20 — MERGED** at merge SHA `dc889a6664ae29dadcad6a2775d65d229d43a191`.
- M15 post-merge `main` CI: **`34796323781` — GREEN** at merge SHA `dc889a6664ae29dadcad6a2775d65d229d43a191`.
- Current milestone: **M16 — Conversations, Leads, Feedback & Conversational Forms**.
- Active M16 branch: `feat/m16-conversations-leads-feedback-forms`.
- M16 Task 1A explicit bot association: **COMPLETE**.
- M16 Task 1B bounded admin conversation query/read model: **COMPLETE**.
- Current unfinished unit: **M16 Task 1C — detail projection and explicit admin delete semantics**.

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

## M15 — COMPLETE

M15 added deterministic bot-scoped display rules and presentation facts, bounded proactive triggers, page-aware starter suggestions, protected admin configuration/preview, bounded English/Urdu localization, widget-local RTL semantics, logical CSS, reduced-motion behavior, and focus/lifecycle hardening without introducing parallel widget/RAG/config authorities.

Final scoped fallback review: **0 unresolved Critical / 0 unresolved Important** across correctness, security/privacy, performance/lifecycle, accessibility/mobile/RTL, and architecture/duplication. Independent reviewer transport was unavailable and that limitation is recorded durably.

M15 PR #20 merged to `main` at `dc889a6664ae29dadcad6a2775d65d229d43a191`; fresh post-merge CI `34796323781` is GREEN on that exact SHA.

Durable M15 references:
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

## M16 — IN PROGRESS

M16 reuses the canonical M11 conversation/message authorities and adds administration, lead capture, feedback, focused forms, visitor integration, and safe export incrementally. Bot identity remains explicit; historical unassigned conversations stay valid; `owner_scope` is never parsed to guess a bot.

Completed current slices:
- Task 1A — explicit bot association and public creation path: COMPLETE. Final GREEN `7be973f5d97b043af5d962cb1e00f0833ecea403`, CI `34801027067`.
- Task 1B pagination authority: GREEN `1ce2c82609af07f7b29fc685dadfb79304679014`, CI `34802027933`.
- Task 1B immutable summary projection: GREEN `c2d2d5725b5a67f38c6c307e9e83fc2100d4f932`, CI `34802423148`.
- Task 1B canonical admin read repository: GREEN `ae185b5f55c954140f230a72c12e19185392d5a1`, CI `34802886455`.
- Task 1B bounded bot/date/transcript query authority: GREEN `060f5614e995ddd23b97c717beaba43ddfd16c7b`, CI `34803852942`.
- Task 1B prepared repository filters/search: GREEN `2d826482d45ec47c1c2b3edfb1d9b3c034774066`, CI `34804366360`.

Task 1B fallback scoped review: **0 Critical / 0 Important**. Filter values are prepared, table identifiers remain repository-owned, transcript search is bounded and correlated by conversation plus owner scope, wildcard characters are escaped, and the outer aggregate still counts/ranks the complete transcript rather than only matching messages. Independent reviewer transport was unavailable; no independent review is claimed.

Current unfinished work: Task 1C — read one conversation plus bounded chronological canonical transcript, then specify explicit administrator deletion with dependent-row cleanup and missing-conversation behavior. After Task 1C, continue directly to protected admin conversation REST.

Durable M16 references:
- `docs/milestones/M16-conversations-leads-feedback-forms.md`
- `docs/superpowers/specs/2026-09-14-m16-conversations-leads-feedback-forms-design.md`
- `docs/superpowers/plans/2026-09-14-m16-conversations-leads-feedback-forms.md`
