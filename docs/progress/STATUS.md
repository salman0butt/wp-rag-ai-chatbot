# Global Status

- Completed milestones on `main`: **M00-M14**.
- Latest completed milestone: **M14 — Frontend Chatbot / Customizer**.
- M14 PR: **#19 — MERGED** at merge SHA `937ee81d55b0007147a6c764ce29d5d5fba9459b`.
- M14 exact-final PR-head CI: **`34748534203` — GREEN** for `php-quality`, `js-quality`, `package`, and `wordpress-smoke` at `2e8c15ba8a09ecc6a6355b8057a61e28741ed7e8`.
- M14 post-merge `main` CI: **`34750216460` — GREEN** for `php-quality`, `js-quality`, `package`, and `wordpress-smoke` at merge SHA `937ee81d55b0007147a6c764ce29d5d5fba9459b`.
- Current milestone: **M15 — Display Rules, Proactive Triggers, Multilingual/RTL & Accessibility**.

This file is the concise recovery index. Detailed RED/GREEN chronology, invalid checkpoints, reviews, security/accessibility/performance findings, implementation notes, and CI evidence remain in milestone ledgers and `docs/progress/MXX-*` evidence files.

## M13 — COMPLETE

PR #18 merged at `a514dd658f20e3103bbe676a0eef8b00a37a23ea`; post-merge CI `34683129496` is GREEN. M13 delivered the Knowledge Manager, indexing/job lifecycle UI, protected Playground execution, and bounded RAG debugger over existing production authorities. Detailed evidence remains in `docs/progress/M13-*` and `docs/milestones/M13-knowledge-manager-playground-debugger.md`.

## M14 — COMPLETE

M14 delivered the production public chatbot surfaces and shared administrator appearance customizer while preserving the M10/M11 production retrieval/chat authorities exactly once. Floating, embedded, fullscreen, shortcode, and Gutenberg adapters share one public runtime; public chat resolves persisted production configuration server-side; browser DTOs remain allow-listed and secret-free; public assets load conditionally; appearance preview/runtime share normalized appearance authority.

Task-level evidence remains under `docs/progress/M14-*`. Final scoped correctness/security/performance/accessibility/architecture review has **0 Critical / 0 Important unresolved**. PR #19 exact final head `2e8c15ba8a09ecc6a6355b8057a61e28741ed7e8` passed CI `34748534203`, merged at `937ee81d55b0007147a6c764ce29d5d5fba9459b`, and fresh post-merge `main` CI `34750216460` passed all four permanent jobs including WordPress activation/database/provider/knowledge/file-ingestion/WooCommerce/Playground/widget-surface smoke coverage.

Durable M14 references:
- `docs/milestones/M14-frontend-chatbot-customizer.md`
- `docs/progress/M14-TASK1-APPEARANCE-CONFIG.md` through `docs/progress/M14-TASK10-CLOSEOUT.md`
- `docs/superpowers/specs/2026-09-12-m14-frontend-chatbot-customizer-design.md`
- PR #19 — merged.

## M15 — IN PROGRESS

Goal: make chatbot presence and engagement deterministic, localized, RTL-capable, responsive, and accessible.

Milestone authority: `docs/milestones/M15-display-rules-rtl-accessibility.md`.

Current work:
1. recover M14 runtime/config boundaries and M15 scope;
2. write and self-review the scheduled-mode auto-approved M15 design/spec;
3. write the executable TDD implementation plan;
4. begin Task 1 with a real RED checkpoint for validated deterministic display-rule configuration/evaluation;
5. preserve server authority for role/auth/post/Woo facts and pass browser-only signals as explicit bounded facts rather than trusting request-level privilege state.

No M15 implementation branch or PR existed at the start of this milestone recovery.
