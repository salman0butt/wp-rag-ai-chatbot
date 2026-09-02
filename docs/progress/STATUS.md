# Global Status

- Current milestone: M03 — AI Providers, Credentials & Compatibility.
- Current task: integrate the security-hardened M03 branch, then require post-merge `main` CI green before M04 starts.
- Current phase: SECURITY-HARDENED RUNTIME CANDIDATE VERIFIED; DURABLE EVIDENCE UPDATE / INTEGRATION PENDING.
- Completed milestones on `main`: M00, M01, M02.
- Runtime-complete milestone pending integration: M03.
- Remaining milestones after M03 integration: M04, M05, M06, M07, M08, M09, M10, M11, M12, M13, M14, M15, M16, M17, M18, M19, M20, M21, M22, M23, M24.
- Blocked items: Native local git clone/worktree and package-registry access remain unavailable in this chat runtime because external DNS is restricted. Connected GitHub branch isolation plus GitHub Actions remain the dependency-backed execution path under ADR-016/018.
- M03 branch: `feat/m03-ai-providers-credentials`.
- M03 authoritative plan: `docs/superpowers/plans/2026-09-02-m03-ai-providers-credentials-v2.md`.
- M03 security-hardened runtime candidate: `c8cddc7c8d4905d1436f95eeb8ef77c2f075c8af`; GitHub Actions run `33639805500` passed `php-quality`, `js-quality`, `wordpress-smoke`, and `package`.
- M03 PHP evidence at `c8cddc7c8d4905d1436f95eeb8ef77c2f075c8af`: WPCS/PHPStan clean; PHPUnit `134 tests / 747 assertions` green; Composer audit clean.
- M03 integration evidence: real WordPress activation + M02 database smoke + M03 encrypted-credential/provider-descriptor smoke green; normal CI makes no paid/live provider calls.
- M03 artifact at run `33639805500`: `wp-rag-ai-chatbot` ID `9850332472`, 64,822 bytes, digest `sha256:d0c8683f0f39e0c0587101e433d05086bccf73766d577d7d6874f4bd85125266`.
- M03 review: five Important issues found and fixed with focused regressions (boundary-crossing secret redaction; provider-runtime package completeness; fail-closed unexpected Core-AI Throwables; Secret export/native-serialization leakage; secret-bearing provider request IDs). Unresolved Critical/Important findings: none.
- Additional TDD evidence: Secret export RED `5e721174530e493ce8274eea2567a25446c7361c` / run `33638078588` failed with the plaintext present in `var_export()`; GREEN `e5ab99f54baf734597c78e6a3ff5b85a1d3d4e2f` / run `33638196004` passed. Request-ID RED `4581b26297b3cc98b6adb0bf9f12b989a1dc8d47` / run `33639434957` failed both OpenAI/OpenRouter regressions; partial GREEN `266b7b40de435a7d563ff5e2ffc1bff6744bb9a6` left only OpenRouter failing; final GREEN `c8cddc7c8d4905d1436f95eeb8ef77c2f075c8af` / run `33639805500` passed all four permanent jobs.
- Exact next action: require all four permanent CI jobs green on the durable-evidence documentation SHA, create/update the M03 PR to `main`, merge only while the exact head SHA remains verified, then require post-merge `main` CI green. M04 must not start before that post-merge gate passes.
