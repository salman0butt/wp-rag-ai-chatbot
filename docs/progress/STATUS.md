# Global Status

- Completed milestones on `main`: **M00-M11**.
- Current milestone: **M12 — Admin Onboarding, Bot Management & Provider Configuration**.
- M12 branch: `feat/m12-admin-onboarding-bots-providers`.
- M12 PR: **#17**.
- M12 design: `docs/superpowers/specs/2026-09-07-m12-admin-onboarding-bots-providers-design.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- M12 implementation plan: `docs/superpowers/plans/2026-09-07-m12-admin-onboarding-bots-providers.md` — **AUTO-APPROVED — SCHEDULED MODE**.

## M12 state

**IMPLEMENTATION COMPLETE — MERGE / POST-MERGE VERIFICATION PENDING.**

Tasks 1-10 are complete. Task 9 provider/model configuration closed with final implementation `394da863b54f6663743c98bf8dd978e675041535`, exact-head CI `34232581709` GREEN, and whole-task review `5142448686` with **0 Critical / 0 Important**. Detailed evidence: `docs/progress/M12-TASK9-CLOSEOUT.md`.

Task 10 integration/closeout exercised the M12 administration boundary in real WordPress. The first integrated smoke head `ff125b79c078da4dfa0d22f0bef892d8777d74fd` exposed a verification-harness provider-ID mismatch, not a product defect. The harness was corrected in `a8518ac2cbc5aeb27968a1bc4ef3291c26e34524`; CI `34235369601` then passed `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`. Final milestone-level correctness/security/accessibility/performance review `5142753820`: **0 Critical / 0 Important**. Detailed evidence: `docs/progress/M12-TASK10-CLOSEOUT.md`.

The previous durable Task 10 evidence head `ebea9257967bb9f7a5f15d14aa9a6051126624d7` passed exact-head CI `34236425289` with all four permanent jobs GREEN. This status/milestone reconciliation intentionally moves the branch head again, so merge requires fresh CI on the new exact durable head before PR #17 can be integrated.

## M12 completed surface

- capability-protected WordPress admin bootstrap and typed same-origin nonce REST client;
- multi-bot persistence, CRUD, pagination, isolation, validation, optimistic editing, confirmed deletion, selected-record accessibility, and responsive admin layout;
- onboarding driven by server-authoritative readiness;
- credential reads exposing only `configured` / `source`, with plaintext/ciphertext never serialized to JavaScript;
- safe replacement submitting only newly typed credentials;
- server-authoritative capability-compatible model choices from Task 5 `/admin/models`;
- stale model/provider state cleared on route/provider changes;
- deterministic accessible guidance only for stable `missing_credential`, `provider_unavailable`, and `unsupported_capability` codes;
- arbitrary provider/upstream messages excluded from the UI;
- plugin-screen-only M12 assets and bounded/on-demand admin work;
- real WordPress admin smoke verifying authorization, mount/enqueue boundaries, non-secret boot data, normalized bootstrap, and credential serialization.

## Merge gate

Exact next work:

1. wait for/verify fresh CI on the final reconciled PR head;
2. confirm PR #17 is mergeable and has 0 blocking review threads;
3. mark PR #17 ready for review;
4. merge only with the expected exact CI-green head;
5. verify fresh post-merge `main` CI;
6. only after that, mark M12 globally complete and make M13 the active milestone.

Do not start M13 before M12 post-merge verification is green.

## Durable recovery

- `docs/milestones/M12-admin-onboarding-bots-providers.md` — authoritative M12 acceptance/merge-gate ledger.
- `docs/progress/M12-TASK9-CLOSEOUT.md` — final Task 9 evidence.
- `docs/progress/M12-TASK10-CLOSEOUT.md` — Task 10 integration/review evidence.
- PR #17 — exact branch/CI/review/merge state.
- `docs/superpowers/specs/2026-09-07-m12-admin-onboarding-bots-providers-design.md` and `docs/superpowers/plans/2026-09-07-m12-admin-onboarding-bots-providers.md` — auto-approved M12 design/plan.
