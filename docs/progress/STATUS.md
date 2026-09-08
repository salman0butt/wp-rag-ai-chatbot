# Global Status

- Completed milestones on `main`: **M00-M11**.
- M11 feature PR: **#16 — MERGED**.
- M11 merge SHA: `9974dc8193462e0459f8bf21d701c30bb164462f`.
- M11 post-merge `main` CI: `34085794045` — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` GREEN.
- Current milestone: **M12 — Admin Onboarding, Bot Management & Provider Configuration**.
- M12 status: **IN PROGRESS**.
- M12 branch: `feat/m12-admin-onboarding-bots-providers`.
- M12 draft PR: **#17**.
- M12 design: `docs/superpowers/specs/2026-09-07-m12-admin-onboarding-bots-providers-design.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- M12 implementation plan: `docs/superpowers/plans/2026-09-07-m12-admin-onboarding-bots-providers.md` — **AUTO-APPROVED — SCHEDULED MODE**.

## M12 task state

1. **COMPLETE** — Admin foundation and capability-protected REST bootstrap. Final implementation `0638dc14197054f48efc5fd51e480b74ca394073`; CI `34088991361`; review `5128526998`: 0 Critical / 0 Important.
2. **COMPLETE** — Bot aggregate/repository and persistence. Final implementation `1bbfe79994f7fa850429e7f5aa968f44e4c54c31`; CI `34091206697`; review `5128744956`: 0 Critical / 0 Important.
3. **COMPLETE** — Bot CRUD REST resources. Final implementation `047de805f6481ad36a334e63e2e44efb62989553`; CI `34098207281`; review `5129481296`: 0 Critical / 0 Important.
4. **COMPLETE** — Provider credential/configuration REST resource. Final implementation `759db1cc1c20ccebb906fc03cc51d1434dc6110a`; CI `34103975996`; review `5130113628`: 0 Critical / 0 Important. Credential reads remain write-only from the browser perspective: plaintext/ciphertext is never serialized.
5. **COMPLETE** — Model capability and onboarding readiness. Review-found page-1-only readiness defect was repaired under genuine regression RED `c19cb4395680a9c2c13b8a70f391763aebda9b17` / CI `34119267966`. Final implementation `080c973dbd011192c88c3f56941f15d3495d504a`; CI `34119414309`; post-fix review `5131750259`: 0 Critical / 0 Important unresolved.
6. **COMPLETE** — React admin shell and typed API. Final transport regression RED `95d813045c3b530aa4208e155a64588c069b20c7` / CI `34149025827`; verified implementation `4ee313e957f0d7ef8c67fa776df07ff647c8212e`; CI `34149262866`; closeout review `5134434515`: 0 Critical / 0 Important unresolved.
7. **COMPLETE** — Onboarding flow. Final server-derived issue integration `144f674c113fd355d74f1b60ff4dbbc85dd9acab`; CI `34168358072`; closeout review `5135644263`: 0 Critical / 0 Important unresolved.
8. **COMPLETE** — Bot management screens. The completed surface includes bounded paginated list/empty state; create/edit persistence and validation; optimistic edit versioning; selected-record unsaved-state isolation; explicit destructive confirmation; hash/page navigation; selected-record `aria-current`; and plugin-scoped responsive WordPress-admin styling. Representative genuine REDs: deletion `158c9f17c4c1020d06919b58782d8fe727589835` / CI `34192114972`; pagination/accessibility `85e6181ab2dad4df0a85b3aa3549952c6231a411` / CI `34196934058`; responsive admin `819d0703efcfd7dc0013e63bd34959ef0077022a` / CI `34201262961`. Final implementation/durable head before closeout docs `7b6242230c5e88a34e728b0b830feee881622b08`; exact-head CI `34201866816` GREEN; final review `5139437799`: **0 Critical / 0 Important unresolved**. Detailed evidence: `docs/progress/M12-TASK8-CLOSEOUT.md`.
9. **ACTIVE** — Provider/model configuration screens. Credential-state security slice COMPLETE: configured credential state is represented only by browser-safe `configured`/`source` metadata plus an empty replacement password field; genuine RED `ad6f92ff91ad35c6c2da9bd3da7ed20dedb2987f` / CI `34212072818`; verified implementation `6fd758118683bea331f597676c2d7d5d505e8cc6`; CI `34212835966`; review `5140265520`: 0 Critical / 0 Important. Credential-replacement slice COMPLETE: component RED `c1217349c3ba0548445e0624591350e8df46d4a1` / CI `34217761100`; REST integration RED `a3a695f452d41ffdb6b3c362faa1fde6cae56bb8` / CI `34218314402`; verified implementation `998f4a4401649abca27e1dff9c4f635bc9a28c22`; CI `34219317420` fully GREEN; scoped review `5141026447`: 0 Critical / 0 Important. Model-selector rendering slice COMPLETE: initial formatting checkpoint `f2c0023174f1d573435ffeff11090cbc83bf22d3` / CI `34222914798` was not counted as RED; genuine RED `715abf2110dc1d95e726bccd2c75d1a507ea5839` / CI `34223027930` ran 41 Jest tests with exactly 1 expected failure (40 passed) because the selector was absent; verified rendering implementation `4002b983699e0dadc8becc0802b3ab54ff175a9d` / CI `34223161036` fully GREEN; scoped review `5141380619`: 0 Critical / 0 Important. Production model-resource wiring slice COMPLETE: formatting-only checkpoint `cbe62d6dea14197527fd1f41c40630d9abdab13e` / CI `34228386987` was not RED; genuine RED `d12a293902a6acb752a0aac08fbf5d9578c1f540` / CI `34228553287` ran 42 Jest tests with exactly 1 failure (41 passed) because `/admin/models` was never requested; verified implementation `342e88967723f9612e5cf3eb982347177088ded5`; CI `34229981092` fully GREEN; scoped review `5142118163`: 0 Critical / 0 Important. Detailed evidence: `docs/progress/M12-TASK9-MODEL-WIRING.md`.
10. Pending — M12 integration/E2E, security, accessibility, performance, independent review, exact-final-SHA CI, merge, and post-merge `main` verification.

## Current work

**Task 9 — Provider/model configuration screens** remains the first legitimate unfinished task.

Credential browser-state security, safe replacement, server-supplied selector rendering, and production Task 5 `/admin/models` wiring are complete. Continue under strict TDD with **safe actionable provider/capability error states** using the existing stable server error codes `missing_credential`, `provider_unavailable`, and `unsupported_capability`.

The provider screen must translate only those stable codes into deterministic accessible guidance. Do not expose provider/upstream exception text, arbitrary server messages, credentials, ciphertext, or introduce a client-side compatibility/catalog path. Preserve server authority for provider/model compatibility and credential state.

Do not advance to Task 10 until Task 9 has focused/full verification, final correctness/security/accessibility/performance review plus an independent reviewer where available, exact-head green CI, and 0 unresolved Critical/Important findings.

## Durable recovery

- `docs/milestones/M12-admin-onboarding-bots-providers.md` — authoritative M12 task/acceptance ledger.
- `docs/progress/M12-TASK9-MODEL-WIRING.md` — current Task 9 production model-resource TDD/review/CI checkpoint and exact continuation point.
- `docs/progress/M12-TASK9-MODEL-SELECTOR.md` — Task 9 server-supplied selector-rendering checkpoint.
- `docs/progress/M12-TASK9-CREDENTIAL-REPLACEMENT.md` — Task 9 credential-replacement TDD/review/CI checkpoint.
- `docs/progress/M12-TASK9-CREDENTIAL-STATE.md` — Task 9 credential browser-state checkpoint.
- `docs/progress/M12-TASK8-CLOSEOUT.md` — Task 8 final reconciliation and review evidence.
- `docs/progress/M12-task4-provider-credentials.md` — credential-resource evidence reused by Task 9.
- PR #17 — active integration point, review history, and exact-SHA CI.
- `docs/superpowers/specs/2026-09-07-m12-admin-onboarding-bots-providers-design.md` and `docs/superpowers/plans/2026-09-07-m12-admin-onboarding-bots-providers.md` — current auto-approved design/plan.
