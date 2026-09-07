# Global Status

- Completed milestones on `main`: **M00-M11**.
- M11 feature PR: **#16 — MERGED**.
- M11 merge SHA: `9974dc8193462e0459f8bf21d701c30bb164462f`.
- M11 final feature head: `b8aaee8da3de7f5f2b40929a57ba5a22c12cc34b`.
- M11 final PR CI: `34085575697` — permanent product CI jobs GREEN.
- M11 post-merge `main` CI: `34085794045` — `php-quality`, `js-quality`, `package`, and `wordpress-smoke` all GREEN.
- M11 final acceptance/security/performance review: **0 Critical / 0 Important unresolved**.
- Current milestone: **M12 — Admin Onboarding, Bot Management & Provider Configuration**.
- M12 status: **IN PROGRESS**.
- M12 branch: `feat/m12-admin-onboarding-bots-providers`.
- M12 draft PR: **#17**.
- M12 design: `docs/superpowers/specs/2026-09-07-m12-admin-onboarding-bots-providers-design.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- M12 implementation plan: `docs/superpowers/plans/2026-09-07-m12-admin-onboarding-bots-providers.md` — **AUTO-APPROVED — SCHEDULED MODE**.
- M12 Task 1 — Admin foundation and capability-protected REST bootstrap: **COMPLETE**. Final implementation `0638dc14197054f48efc5fd51e480b74ca394073`; exact-head CI `34088991361` GREEN; scoped review `5128526998` found **0 Critical / 0 Important**.
- M12 Task 2 — Bot aggregate/repository and persistence: **COMPLETE**. Final implementation/integration `1bbfe79994f7fa850429e7f5aa968f44e4c54c31`; exact-head CI `34091206697` GREEN; scoped review `5128744956` found **0 Critical / 0 Important**.
- M12 Task 3 — Bot CRUD REST resources with pagination/isolation: **COMPLETE**. Genuine RED `b7950a33c51171532c1d270076f8b847b70d2d21` / CI `34097504341`; final implementation/integration `047de805f6481ad36a334e63e2e44efb62989553` / CI `34098207281` with `php-quality`, `js-quality`, `package`, and `wordpress-smoke` GREEN. Real WordPress REST smoke proves unauthenticated denial, admin CRUD, bounded pagination, deterministic malformed/stale errors, and multi-bot update/delete isolation. Scoped review `5129481296` found **0 Critical / 0 Important**.
- M12 Task 4 — Provider credential/configuration REST resource with write-only secrets: **COMPLETE**. Genuine core RED `26ce3eefef6dd7d5198b77a669268bc07cbcafbf` / CI `34102834048` reached PHPUnit after static gates and failed exactly five absent-resource behaviors. Genuine route RED `4ff5916c809cf104d7e753a2d5531837ef093a49` / CI `34103192408` reached PHPUnit and failed exactly because the provider credential route was absent. Final implementation checkpoint `759db1cc1c20ccebb906fc03cc51d1434dc6110a` / CI `34103975996` passed `php-quality`, `js-quality`, `package`, and the complete `wordpress-smoke` suite. The REST resource reuses M03 `CredentialResolver`, `CredentialStore`, `WordPressCredentialStore`, `AuthenticatedCredentialCipher`, and runtime source/capability seams; GET returns only configured/source metadata, PUT/DELETE never echo secrets, all methods use `AdminCapability::can_manage`, and unsafe exception text is normalized. Scoped correctness/security/performance review `5130113628` found **0 Critical / 0 Important**.
- Current M12 task: **Task 5 — Model capability and onboarding-readiness resources**.
- Exact next unfinished action: recover the exact Task 4 documentation head/CI and PR threads, then add the smallest Task 5 behavioral tests first proving unavailable provider, missing credential, and unsupported capability are distinguishable; model lists are normalized and purpose/capability-filtered; and onboarding readiness advances only when persisted provider/model/bot requirements are satisfied. Reuse M03 model catalog/provider capability contracts and Task 2 bot state, and establish a genuine expected RED before Task 5 production implementation.

Detailed M12 Task 1-3 evidence remains recorded in `docs/milestones/M12-admin-onboarding-bots-providers.md`. Task 4 exact evidence is additionally recorded in `docs/progress/M12-task4-provider-credentials.md` and PR #17.
