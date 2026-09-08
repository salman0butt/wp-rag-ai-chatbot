# M12 — Admin Onboarding, Bot Management & Provider Configuration

Status: **COMPLETE**

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

## Closeout Evidence
Task 9 final implementation `394da863b54f6663743c98bf8dd978e675041535`; CI `34232581709` GREEN; whole-task review `5142448686`: **0 Critical / 0 Important**. Detailed evidence: `docs/progress/M12-TASK9-CLOSEOUT.md`.

Task 10 real-WordPress integration was corrected at `a8518ac2cbc5aeb27968a1bc4ef3291c26e34524`; CI `34235369601` GREEN. Final milestone-level correctness/security/accessibility/performance review `5142753820`: **0 Critical / 0 Important**. Detailed evidence: `docs/progress/M12-TASK10-CLOSEOUT.md`.

Final reconciled PR head `85c1e18ba64626653c6ecc7733517d930bc8d695` passed exact-head CI `34240807501` across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`, with zero blocking review threads. PR #17 was merged as `206dbfcef42cfcee2a998d7f3c386abdb97425a0`.

Fresh post-merge `main` CI `34241199445` passed all four permanent jobs, including the complete WordPress smoke chain. M12 is therefore globally complete.

## Security / Accessibility / Performance State
- Admin REST resources use the centralized `manage_options` capability boundary.
- Browser REST transport is same-origin and nonce authenticated.
- Provider secrets remain write-only; localized boot config and credential reads contain no plaintext/ciphertext/API-key material.
- Provider/model compatibility remains server authoritative.
- Stable provider/capability failures render deterministic safe guidance rather than arbitrary upstream messages.
- Navigation/selected records expose `aria-current`; loading/status/error states use status/alert semantics; bot validation identifies/focuses invalid controls; controls and pagination remain labelled.
- Admin JavaScript/config/CSS are plugin-screen-only.
- Bot list work remains bounded/paginated; provider/model work is route-driven/on-demand; no polling or public-widget runtime work was added.

## Durable Recovery Sources
- `docs/progress/STATUS.md` — global milestone status.
- `docs/progress/M12-TASK9-CLOSEOUT.md` — final Task 9 evidence.
- `docs/progress/M12-TASK10-CLOSEOUT.md` — Task 10 integration/review evidence.
- PR #17 — merged M12 integration record.

## Next Milestone
M13 — Knowledge Manager/Debugger.
