# Global Status

- Completed milestones on `main`: **M00-M12**.
- Latest completed milestone: **M12 — Admin Onboarding, Bot Management & Provider Configuration**.
- M12 PR: **#17 — MERGED**.
- M12 merge SHA: `206dbfcef42cfcee2a998d7f3c386abdb97425a0`.
- M12 final reconciled PR head: `85c1e18ba64626653c6ecc7733517d930bc8d695`; CI `34240807501` GREEN across `php-quality`, `js-quality`, `package`, and `wordpress-smoke`.
- M12 post-merge `main` CI: `34241199445` — all four permanent jobs GREEN, including the complete WordPress smoke chain.
- M12 final milestone review `5142753820`: **0 Critical / 0 Important**.
- Current milestone: **M13 — Knowledge Manager/Debugger**.

## M12 completed surface

M12 is **COMPLETE**. It delivered:

- capability-protected WordPress admin bootstrap and typed same-origin nonce REST client;
- multi-bot persistence, CRUD, pagination, isolation, validation, optimistic editing, confirmed deletion, selected-record accessibility, and responsive admin layout;
- onboarding driven by server-authoritative readiness;
- browser-safe credential state exposing only `configured` / `source`, with plaintext/ciphertext never serialized to JavaScript;
- safe credential replacement submitting only newly typed credentials;
- server-authoritative capability-compatible model selection through Task 5 `/admin/models`;
- stale provider/model state cleared across provider route changes;
- deterministic accessible guidance only for stable `missing_credential`, `provider_unavailable`, and `unsupported_capability` codes;
- arbitrary provider/upstream messages excluded from the UI;
- plugin-screen-only M12 assets and bounded/on-demand admin work;
- real WordPress admin smoke covering authorization, mount/enqueue boundaries, non-secret boot data, normalized bootstrap, credential serialization, activation, database, providers, knowledge, file ingestion, and WooCommerce knowledge.

Task 9 final implementation `394da863b54f6663743c98bf8dd978e675041535`; CI `34232581709` GREEN; whole-task review `5142448686`: 0 Critical / 0 Important. Evidence: `docs/progress/M12-TASK9-CLOSEOUT.md`.

Task 10 final integration correction `a8518ac2cbc5aeb27968a1bc4ef3291c26e34524`; CI `34235369601` GREEN; final milestone review `5142753820`: 0 Critical / 0 Important. Evidence: `docs/progress/M12-TASK10-CLOSEOUT.md`.

## Current work

**M13 — Knowledge Manager/Debugger** is now the first legitimate unfinished milestone.

A fresh autonomous run must recover M13 scope from its milestone/spec/plan and repository state before writing. Do not infer M13 behavior from M12 implementation details.

## Durable recovery

- `docs/milestones/M12-admin-onboarding-bots-providers.md` — completed M12 ledger.
- `docs/progress/M12-TASK9-CLOSEOUT.md` — final Task 9 evidence.
- `docs/progress/M12-TASK10-CLOSEOUT.md` — final Task 10 integration/review evidence.
- PR #17 — merged M12 integration record.
- M13 milestone/spec/plan docs — authoritative next-scope sources for the next run.
