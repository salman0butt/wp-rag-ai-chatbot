# Global Status

- Completed milestones on `main`: **M00-M15**.
- Latest completed milestone on `main`: **M15 — Display Rules, Proactive Triggers, Multilingual/RTL & Accessibility**.
- M15 PR: **#20 — MERGED** at merge SHA `dc889a6664ae29dadcad6a2775d65d229d43a191`.
- M15 post-merge `main` CI: **`34796323781` — GREEN** at merge SHA `dc889a6664ae29dadcad6a2775d65d229d43a191`.
- Current maintenance track: **provider-first admin UX plus direct Google Gemini and Groq provider support**.
- Next roadmap milestone after this bounded maintenance integration remains **M16**.

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
- PR #20 merged to `main` at `dc889a6664ae29dadcad6a2775d65d229d43a191`; post-merge CI `34796323781` — GREEN.

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

## Post-M15 maintenance — Provider-first admin UX and direct providers

This bounded maintenance track simplifies the WordPress admin around a provider-first setup flow and extends the existing provider architecture without creating a parallel generation authority.

Delivered behavior:
- compact WordPress-style navigation: Providers, Chatbots, Knowledge, Test Chat;
- provider catalog cards with safe local connection state and provider-specific configuration screens;
- direct provider IDs and credential configuration for Google Gemini and Groq alongside OpenAI and OpenRouter;
- a shared fixed-endpoint OpenAI-compatible generation/model-catalog adapter for Gemini and Groq;
- safe credential-source labeling (`option` is shown as plugin-managed) without rehydrating stored secrets;
- provider-specific API-key configuration controls using native WordPress classes;
- idempotent admin DOM enhancement under the MutationObserver path;
- real WordPress provider smoke updated to require all five runtime provider IDs.

TDD / verification evidence:
- provider-panel RED: `c6a489ec63d08043b69d0a7bf99ff1cdd0fa4435`, CI `34952988439` — the new Gemini panel regression failed for the intended generic-heading reason after lint/typecheck passed;
- provider-panel GREEN implementation: `6d2c867ce6afb205c127e59504d5935461b5c0ee`, CI `34953102787` — `php-quality`, `js-quality`, `wordpress-smoke`, and `package` all GREEN;
- the WordPress provider smoke in the GREEN run validates activation/runtime integration and the five-provider bootstrap registry without requiring live paid-provider calls.

Security/review boundary:
- provider endpoints remain fixed in the composition root;
- credentials continue through the existing resolver/encrypted store/`Secret::with_value()` boundary;
- provider error bodies/request IDs pass through existing secret redaction before diagnostic propagation;
- bootstrap/configuration remains local-only and does not issue outbound provider requests;
- live-provider calls remain opt-in through the existing credential-gated smoke path.

No unresolved Critical or Important findings are known in this maintenance scope. Final integration still requires exact-final-head CI, mergeability/review checks, merge, and fresh post-merge `main` CI before the maintenance track is considered integrated.
