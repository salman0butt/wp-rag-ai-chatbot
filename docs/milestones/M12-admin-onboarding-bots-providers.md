# M12 — Admin Onboarding, Bot Management & Provider Configuration

Status: **IMPLEMENTATION COMPLETE — MERGE / POST-MERGE VERIFICATION PENDING**

## Goal
Build a professional WordPress-native admin shell, onboarding, multi-bot management, and provider/model configuration UI.

## Dependencies
M03, M11 backend contracts, M01 frontend tooling.

## In Scope
Admin navigation/shell; onboarding; bot CRUD/config; provider credential/model/capability UI; validation/errors; settings progressive disclosure; usage-safe model selection.

## Out of Scope
Knowledge/debugger UI M13; appearance editor M14; analytics M21.

## Architecture
React/TypeScript where useful, backed by granular capability-protected REST endpoints. Provider secrets remain write-only and are never returned to JavaScript.

Selected design: `docs/superpowers/specs/2026-09-07-m12-admin-onboarding-bots-providers-design.md`.
Implementation plan: `docs/superpowers/plans/2026-09-07-m12-admin-onboarding-bots-providers.md`.
Both are **AUTO-APPROVED — SCHEDULED MODE**.

## Acceptance Criteria
Admin capabilities are enforced; onboarding handles unavailable provider/capability states; multiple bots remain isolated; secrets remain write-only; admin transport fails safely; typecheck/build/component/WordPress integration flows pass; admin assets do not load on public or unrelated admin screens; accessibility and performance gates are reviewed before milestone closeout.

## Tasks
1. **COMPLETE** — Admin foundation and capability-protected REST bootstrap.
2. **COMPLETE** — Bot aggregate/repository and persistence.
3. **COMPLETE** — Bot CRUD REST resources with pagination/isolation.
4. **COMPLETE** — Provider credential/configuration REST resource with write-only secrets.
5. **COMPLETE** — Provider model/capability and onboarding-readiness resources.
6. **COMPLETE** — React admin shell and typed API layer.
7. **COMPLETE** — Onboarding flow.
8. **COMPLETE** — Bot management screens.
9. **COMPLETE** — Provider/model configuration screens.
10. **COMPLETE** — M12 integration/E2E, security, accessibility, performance, and milestone-level review.

## Task 9 Closeout
Task 9 satisfies all planned provider/model configuration behavior families:

- browser-safe credential state exposes only `configured` / `source` metadata;
- credential replacement submits only newly entered secrets through the existing nonce-authenticated Task 4 resource;
- model selection renders only normalized choices returned by the existing Task 5 `/admin/models` resource, scoped to provider and `purpose=generation`;
- provider changes clear stale provider/model state before refetch;
- only stable server codes `missing_credential`, `provider_unavailable`, and `unsupported_capability` map to deterministic accessible guidance;
- arbitrary server/upstream messages, plaintext, ciphertext, and browser-side compatibility catalogs remain excluded.

Final Task 9 implementation: `394da863b54f6663743c98bf8dd978e675041535`; CI `34232581709` GREEN across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`; whole-Task-9 review `5142448686`: **0 Critical / 0 Important**. Detailed evidence: `docs/progress/M12-TASK9-CLOSEOUT.md`.

## Task 10 Closeout
Task 10 added a real WordPress administration smoke and exercised the completed M12 integration boundary. It verifies admin authorization/bootstrap, plugin-screen-only asset enqueueing, non-secret boot data, credential read serialization, and the completed WordPress smoke chain covering activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, bots, models/readiness, and credential persistence/resolution.

The initial integrated smoke head `ff125b79c078da4dfa0d22f0bef892d8777d74fd` failed only because the harness used `openai` instead of canonical `ProviderIds::OPENAI_DIRECT`. Systematic debugging corrected the harness in `a8518ac2cbc5aeb27968a1bc4ef3291c26e34524`; exact-head CI `34235369601` then passed all permanent jobs.

Final milestone-level correctness/security/accessibility/performance review `5142753820`: **0 Critical / 0 Important**. PR #17 has no unresolved review threads. Detailed evidence: `docs/progress/M12-TASK10-CLOSEOUT.md`.

## Security / Accessibility / Performance State
- Admin REST resources use the centralized `manage_options` capability boundary.
- Browser REST transport is same-origin and nonce authenticated.
- Provider secrets remain write-only; localized boot config and credential reads contain no plaintext/ciphertext/API-key material.
- Provider/model compatibility remains server authoritative.
- Stable provider/capability failures render deterministic safe guidance rather than arbitrary upstream messages.
- Navigation/selected records expose `aria-current`; loading/status/error states use status/alert semantics; bot validation identifies/focuses invalid controls; controls and pagination remain labelled.
- Admin JavaScript/config/CSS are plugin-screen-only.
- Bot list work remains bounded/paginated; provider/model work is route-driven/on-demand; no polling or public-widget runtime work was added.

## Merge Gate
Implementation and review gates are complete. Before declaring M12 globally complete:

1. require fresh exact-head CI on the final durable branch head;
2. confirm PR #17 remains mergeable with no blocking review threads;
3. mark PR #17 ready for review;
4. merge only with the expected exact head SHA;
5. verify fresh post-merge `main` CI;
6. then advance durable global state to M13.

## Durable Recovery Sources
- `docs/progress/STATUS.md` — global/current merge-gate status.
- `docs/progress/M12-TASK9-CLOSEOUT.md` — final Task 9 acceptance/TDD/review evidence.
- `docs/progress/M12-TASK10-CLOSEOUT.md` — Task 10 integration/review/merge-gate evidence.
- PR #17 — active integration point, review state, and exact-SHA CI.
- Auto-approved M12 design and implementation plan referenced above.

## Completion Checklist
Tasks **1-10 are complete**. M12 remains not globally closed until the final durable PR head is CI-green, PR #17 is merged, and fresh post-merge `main` CI passes.

## Next Milestone
M13 — Knowledge Manager/Debugger, only after M12 post-merge verification is genuinely complete.
