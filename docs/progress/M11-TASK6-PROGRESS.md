# M11 Task 6 — Deterministic Grounding Progress

Status: **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED**

## Scope

Task 6 implements the application-owned grounding decision that sits between bounded retrieval and provider generation. The policy is deterministic: it does not call an LLM/provider to decide whether evidence is sufficient.

## Delivered Behavior

- `GroundingDecision` is an immutable application-owned allow/deny result.
- Allowed decisions cannot carry denial data.
- Denied decisions require the stable `ChatFailureReason::INSUFFICIENT_EVIDENCE` reason plus non-empty canonical no-answer content.
- `GroundingPolicy` receives only the application-selected `GroundingMode` and the final bounded retrieval candidates.
- `DeterministicGroundingPolicy` rechecks the hard maximum of 12 selected candidates.
- `STRICT` mode permits generation only when at least one selected candidate already carries M10 deterministic confidence level `medium` or `high`.
- `STRICT` mode with zero candidates, missing confidence, or only `low` confidence fails closed and returns: `I don't have enough reliable information in the selected sources to answer that.`
- `ASSISTED` mode remains generation-eligible even with no/low evidence; the existing retrieval/context hard bounds still apply upstream.
- No provider credentials, authorization state, raw diagnostics, or model-authored confidence enter the grounding decision.

## TDD Evidence

- Initial test-only commit `fb7478035a8f54e6432c6ec30845a7e16eb51d7a`, CI `34028670503`, stopped in PHPCS on test docblock/type formatting and is **not** counted as behavioral RED.
- Test-only standards correction `0298402132d329123b7048e5b7f4919cd396fa6d`, CI `34028799644`, reached the behavior suite and produced the genuine Task 6 RED: PHPStan 0 errors; PHPUnit ran 595 tests / 2,429 assertions with exactly six failures because `GroundingDecision`, `GroundingPolicy`, and `DeterministicGroundingPolicy` were missing.
- Production commits: `272e82670217f354c940827f0456c001ab5756b3` (`GroundingDecision`), `590cdf2dc1f7de89fba6e623a3fe5a608a0b1eaf` (`GroundingPolicy`), and `67ffc8740d5a4fedf01b636a49e91200417e95e8` (`DeterministicGroundingPolicy`).
- CI on `67ffc874...` exposed only missing implementation-method documentation; `971212d3ff9f80c722d6228dd00cb7b62bab241f` corrected that. Its next CI reached PHPStan, which correctly reported one redundant `instanceof` check because the interface already guarantees `list<RetrievalCandidate>`.
- Static-analysis correction `f3e32ffda462efd99eec11f7ae23bdd4e89df2be` removed only that unreachable defensive branch without changing grounding semantics.

## Verification

Exact implementation SHA `f3e32ffda462efd99eec11f7ae23bdd4e89df2be` passed CI `34029027755` across all four permanent jobs:

- `php-quality` — SUCCESS: PHPStan 0 errors; PHPUnit 595/595 tests / 2,458 assertions; Composer audit reports no security vulnerability advisories.
- `js-quality` — SUCCESS.
- `package` — SUCCESS.
- `wordpress-smoke` — SUCCESS.

Package artifact `9987989620`, 849,521 bytes, digest `sha256:009770030e6401ebab646b6eb4ef37aa806fdded2fafd35d89320b5c4188ef20`, is tied to exact implementation SHA `f3e32ffda...`.

## Independent Review

Scoped Task 6 review `5125124020` is anchored to exact implementation SHA `f3e32ffda462efd99eec11f7ae23bdd4e89df2be`.

Review scope covered fail-closed STRICT behavior, deterministic no-answer without provider/LLM involvement, ASSISTED eligibility, stable application-owned denial reason/content, decision invariants, reuse of existing M10 confidence semantics rather than model-authored confidence, the 12-candidate ceiling, authorization/secrets/diagnostic exclusion, and bounded deterministic work.

Final result: **0 unresolved Critical / 0 unresolved Important findings**.

## Merge State

PR #16 remains open/draft because M11 Tasks 7–9 are unfinished. Task 6 itself is closed.

## Exact Next Unfinished Action

Begin **Task 7 — provider-neutral orchestration and post-generation citation validation** with a test-only behavioral RED. The first RED should prove the orchestrator keeps authorization/owner scope outside model input, executes retrieval before grounding, skips provider generation entirely on a strict grounding denial, builds generation only from the bounded prompt/context contract, validates generated citation markers against the request-local registry before returning success, maps provider/retrieval/citation failures to stable `ChatFailureReason` values, and never exposes raw provider diagnostics. Do not add the Task 7 production orchestrator until the exact test-only SHA reaches PHPUnit and fails for the expected missing orchestration contract.