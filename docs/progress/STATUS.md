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
9. **ACTIVE** — Provider/model configuration screens. First security slice COMPLETE: configured credential state is represented only by browser-safe `configured`/`source` state plus an empty replacement password field; no plaintext/ciphertext is rendered or rehydrated. Genuine RED `ad6f92ff91ad35c6c2da9bd3da7ed20dedb2987f` / CI `34212072818` ran 38 Jest tests with exactly the new case failing (37 passed / 1 failed). Verified implementation `6fd758118683bea331f597676c2d7d5d505e8cc6`; CI `34212835966` fully GREEN with 38/38 Jest tests; scoped review `5140265520`: 0 Critical / 0 Important. Detailed evidence: `docs/progress/M12-TASK9-CREDENTIAL-STATE.md`.
10. Pending — M12 integration/E2E, security, accessibility, performance, independent review, exact-final-SHA CI, merge, and post-merge `main` verification.

## Current work

**Task 9 — Provider/model configuration screens** remains the first legitimate unfinished task.

The configured-credential browser security boundary is now proven and durably checkpointed. Continue under strict TDD with safe credential replacement through the existing Task 4 capability-protected credential REST contract: load only configured/source state, submit only a newly entered credential through the existing nonce-authenticated admin client, never prefill/restore the old credential, refresh from server-authoritative state after success, and expose safe actionable failure state without upstream/secret leakage.

After credential replacement, continue with capability-filtered model selection and actionable provider/capability errors. Do not create a parallel credential path, do not persist completion solely in browser state, and do not advance to Task 10 until Task 9 has focused/full verification, exact-head green CI, and 0 unresolved Critical/Important correctness/security/accessibility/performance findings.

## Durable recovery

- `docs/milestones/M12-admin-onboarding-bots-providers.md` — authoritative M12 task/acceptance ledger.
- `docs/progress/M12-TASK9-CREDENTIAL-STATE.md` — current Task 9 credential-state TDD/review/CI checkpoint.
- `docs/progress/M12-TASK8-CLOSEOUT.md` — Task 8 final reconciliation and review evidence.
- `docs/progress/M12-task4-provider-credentials.md` — credential-resource evidence reused by Task 9.
- PR #17 — active integration point, review history, and exact-SHA CI.
- `docs/superpowers/specs/2026-09-07-m12-admin-onboarding-bots-providers-design.md` and `docs/superpowers/plans/2026-09-07-m12-admin-onboarding-bots-providers.md` — current auto-approved design/plan.
