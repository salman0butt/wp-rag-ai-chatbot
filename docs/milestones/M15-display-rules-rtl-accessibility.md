# M15 — Display Rules, Proactive Triggers, Multilingual/RTL & Accessibility

Status: IMPLEMENTATION COMPLETE — PR / MERGE / POST-MERGE VERIFICATION PENDING

## Goal
Make chatbot presence/engagement deterministic, localized, RTL-capable, responsive, and accessible.

## Dependencies
M14 — COMPLETE.

## In Scope
Include/exclude URL; post type; role/auth state; Woo areas; device; schedule; delay; scroll; exit intent; inactivity; CSS click; URL pattern; first visit; page starters; deterministic rule engine; RTL/localization; keyboard/screen reader semantics.

## Out of Scope
Building a general marketing automation platform.

## Architecture
Validated rule data + one pure/testable TypeScript evaluation engine; browser-only signals are explicit bounded facts. PHP owns validation/normalization/persistence/public projection and trusted WordPress server facts, not a duplicate evaluation engine. The existing M14 widget runtime remains the sole production presentation runtime.

## Acceptance Criteria
- deterministic rule fixtures and documented precedence — COMPLETE;
- normalized/persisted bot-scoped configuration and protected admin REST — COMPLETE;
- trusted WordPress server-fact projection — COMPLETE;
- one runtime evaluator composition with server/browser facts — COMPLETE;
- bounded proactive triggers with lifecycle cleanup — COMPLETE;
- page starter suggestions — COMPLETE;
- admin configuration + deterministic preview — COMPLETE;
- bounded localization, RTL logical layout and local `lang`/`dir` — COMPLETE;
- reduced-motion, focus, keyboard and screen-reader regressions — COMPLETE;
- permanent PHP/JS/package/real-WordPress verification and final scoped review — COMPLETE.

## Tasks

1. **COMPLETE** — deterministic TypeScript display-rule domain.
2. **COMPLETE** — immutable PHP normalized config, bot persistence, schema migration, protected admin REST and public projection.
3. **COMPLETE** — trusted WordPress server context fact projection.
4. **COMPLETE** — runtime visibility integration using the Task 1 authority exactly once.
5. **COMPLETE** — bounded proactive trigger coordinator and browser signals with cleanup.
6. **COMPLETE** — page-aware starter suggestions over the shared display decision.
7. **COMPLETE** — admin rules editor, protected load/save, deterministic preview, validation and stale-response protection.
8. **COMPLETE** — bounded localization catalog, widget-local RTL semantics, logical CSS, reduced motion and focus lifecycle.
9. **COMPLETE** — permanent integration/WordPress smoke and final correctness/security/performance/accessibility/architecture review.

Detailed executable plan: `docs/superpowers/plans/2026-09-13-m15-display-rules-rtl-accessibility.md`.

## Durable Evidence
- `docs/progress/M15-TASK1-DISPLAY-RULES.md`
- `docs/progress/M15-TASK3-SERVER-CONTEXT.md`
- `docs/progress/M15-TASK4-RUNTIME-VISIBILITY.md`
- `docs/progress/M15-TASK5-PROACTIVE-TRIGGERS.md`
- `docs/progress/M15-TASK6-STARTER-SUGGESTIONS.md`
- `docs/progress/M15-TASK7-ADMIN-RULES-EDITOR.md`
- `docs/progress/M15-TASK8-LOCALIZATION-RTL.md`
- `docs/progress/M15-TASK9-CLOSEOUT.md`
- Git/CI history plus PHP repository/config/REST/migration tests preserve Task 2 chronology where no standalone Task 2 evidence file exists.

## Key Verification Evidence
- Task 1 domain final GREEN: `e9d4adbd076333142e000a38332cfdc8fd9e8853`, CI `34755743017`.
- Task 7 integration/stale-save final GREEN coverage: `38cadbef21fd8387fb5e4bf6733cc33930536148`, CI `34793226261`.
- Task 8 localized runtime final GREEN: `a875299414e709a182d7e361e328b07c9c80bc7e`, CI `34794272117`.
- Task 8 widget-local direction final GREEN: `29bbe3b34ba7616d08b23c2aabfc276c61073944`, CI `34794699995`.
- Task 8 logical RTL CSS final GREEN: `b05ec97078c790b50a7cdbdfa647234bb174fa9f`, CI `34794973838`.
- Task 8 reduced-motion final GREEN: `fe69aacaf5aee32341e682d2daf1c55bb3bcc888`, CI `34795234377`.
- Task 8 focus/lifecycle final GREEN coverage: `4431d09f1cc3d3ca3fac5c639b50b1054b4c0424`, CI `34795395647`.
- Task 9 permanent WordPress integration GREEN: `8bd5af09a435bafb3f000f42ae7cce91d86dacfb`, CI `34795728393`.

Every listed final checkpoint passed permanent `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` gates. Detailed `NOT RED` / `NOT GREEN` chronology is preserved in task evidence and GitHub Actions history.

## Final Review
Independent reviewer/subagent transport was unavailable for final closeout, so the repository-approved scoped fallback review was used.

- Correctness: **0 unresolved Critical / 0 unresolved Important**.
- Security/privacy: **0 unresolved Critical / 0 unresolved Important**.
- Performance/listener cleanup: **0 unresolved Critical / 0 unresolved Important**.
- Accessibility/mobile/RTL: **0 unresolved Critical / 0 unresolved Important**.
- Architecture/duplication: **0 unresolved Critical / 0 unresolved Important**.

One earlier Important empty-schedule semantic defect discovered during Task 1 review was fixed by dedicated regression work and verified GREEN. No unresolved Critical/Important findings remain.

## Security Boundary
Public presentation configuration remains allow-listed and does not expose credentials, provider/model authority, embeddings, vector-store/retrieval configuration, user IDs/emails, capability maps, or arbitrary request-level runtime overrides. Click selectors, patterns, prompts and collections are bounded and normalized.

## Accessibility / RTL
Widget-visible text and accessible names resolve through one bounded English/Urdu catalog with deterministic English fallback. Widget `lang` / `dir` are applied locally from the shared evaluator decision. Layout uses logical inline positioning. Reduced motion bypasses simulated typing. Proactive open does not steal focus; manual open/Escape focus lifecycle remains deterministic.

## Permanent WordPress Smoke
Task 9 real WordPress smoke creates real persisted display rules, renders all public widget surfaces through production authorities, verifies normalized M15 public bootstrap projection, conditional shared asset loading, fail-closed invalid/disabled bots, and absence of provider/model/retrieval/vector-store/credential leakage. Complementary permanent JS/PHP tests verify URL visibility, server fact projection, proactive no-chat behavior, RTL root semantics, lifecycle cleanup and presentation boundaries.

## Known Limitations
No dedicated browser automation job is added in M15; deterministic jsdom runtime tests plus the existing real WordPress smoke are the permanent integration boundary. This does not block the documented M15 acceptance criteria.

## Completion Checklist
Implementation, tests, final review, task documentation and Task 9 permanent smoke are complete. Remaining milestone gate: exact-final documentation-head CI, single PR creation/recovery, mergeability/review/concurrency recheck, protected merge, then fresh post-merge `main` CI.

## Next Unfinished Unit
Verify the exact closeout-documentation branch head, create/recover the single M15 PR, satisfy the merge gate, merge with expected-head protection, verify fresh post-merge `main` CI, then recover M16 and continue.

## Next Milestone
M16 — Conversations/Leads/Feedback/Forms, only after M15 is merged and post-merge verified.
