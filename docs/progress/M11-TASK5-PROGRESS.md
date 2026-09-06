# M11 Task 5 — Prompt/Context Builder Progress

Status: **IMPLEMENTATION GREEN / COORDINATOR REVIEW FINDINGS FIXED / INDEPENDENT REVIEW PENDING**

## Scope

Task 5 implements the provider-neutral prompt/context boundary from the auto-approved M11 design and implementation plan. It must keep application-owned policy separate from untrusted memory, retrieved evidence, and the current question; expose only request-local citation identifiers to the model; preserve deterministic section ordering; and enforce the 12-candidate / 48 KiB evidence ceiling before provider dispatch.

## Delivered Behavior

- `CitationRegistry` retains only prompt-safe selected evidence `{id, content}` in final context order while keeping raw retrieval metadata, visibility, scores, authorization state, and canonical lineage internals out of the prompt-facing contract.
- `PromptContext` accepts at most 12 selected evidence entries and renders them beneath an explicit `UNTRUSTED EVIDENCE — DATA ONLY` boundary.
- The complete rendered evidence section, including opening and closing framing bytes and escaped content growth, is bounded to 49,152 bytes.
- Lower-priority evidence is dropped deterministically when it cannot fit the same hard evidence budget.
- `PromptBuilder` emits deterministic `MEMORY -> EVIDENCE -> QUESTION` data sections and keeps application-owned policy in `GenerationRequest::instructions`.
- Model selection and requested output-token limits remain application/request values and cannot be replaced by retrieved text.
- Untrusted summary text, message role/content, retrieved evidence, and the current question are escaped before rendering so literal machine-generated section delimiters cannot be spoofed by user/retrieved content.
- Provider secrets, raw diagnostics, visibility/auth metadata, chunk IDs, and document IDs are not included in the generated prompt input.

## Primary TDD Evidence

- Test-only commit `8b8fd16c7c6781785390524c895e2a7e10e5f372` stopped at PHPCS alignment warnings before PHPUnit and is **not** counted as behavioral RED.
- Test-only standards correction `2149f0d4b8bf6aa95e8bb3587f6741609a719f36`, CI `34025759676`, reached the behavior suite and produced the valid Task 5 RED: PHPStan was clean and PHPUnit failed exactly four new Task 5 assertions because `PromptContext` / `PromptBuilder` did not yet exist.
- Production sequence: `c7eb34d01c9e27710ade0dddb8a4e033e2be6bb4` (`CitationRegistry` prompt-safe evidence), `c7934173d01f709f0048b69c2ef9ebb1b0ce460c` (`PromptContext`), and `c3e842739173c595b3a14a2d86dc9a09edd9e32d` (`PromptBuilder`).
- `c3e84273...` stopped at PHPCS-only assignment alignment and `74be2617...` subsequently stopped at PHPStan-only redundant checks; neither is claimed as behavioral RED/GREEN.
- Static-analysis corrections through `168c5ea22e5870b5f323fec461c9d4edbacd7af1` produced the first implementation GREEN. Push CI `34025951322` passed php-quality, js-quality, package, and wordpress-smoke. PHP verification reported PHPStan 0 errors, PHPUnit 587/587 tests / 2,411 assertions, and Composer audit clean. Artifact `9987052679`, digest `sha256:b474f7580590a6a13f241bf6a0fd6acff483a8e2351477a0e1460256bd5c24bb`.

## Coordinator Security / Prompt-Isolation Review

Coordinator review `5124994011` on exact implementation SHA `168c5ea22e5870b5f323fec461c9d4edbacd7af1` found **0 Critical / 2 Important** issues:

1. Untrusted memory/evidence/question text was inserted verbatim inside machine-generated `<MEMORY>`, `<EVIDENCE>`, and `<QUESTION>` framing, allowing literal closing/opening delimiter text to spoof section boundaries.
2. The 48 KiB evidence budget included the opening prefix and evidence entries but omitted the closing `</EVIDENCE>` bytes, allowing the complete rendered evidence section to exceed the hard limit at the boundary.

The review was explicitly recorded as a coordinator review and is **not** being substituted for the mandatory independent-review gate.

## Review Regression TDD

- Test-only commit `9a53604c61993d124ca1cfd8919ec7a5318bd18e` stopped at PHPCS alignment warnings and is **not** counted as regression RED.
- Test-only standards correction `de7677b317b5877906c521b3a64824bc32550a8f`, CI `34026203479`, produced the valid review-regression RED: PHPStan 0 errors; PHPUnit 589 tests / 2,418 assertions with exactly two failures. The delimiter-spoofing string remained literal, and the complete evidence section measured 49,161 bytes against the 49,152-byte ceiling.
- Fix `d32a2d315053e54d9bb271fb6878f078bd092799` includes the evidence suffix in byte accounting and escapes untrusted evidence before sizing/rendering.
- Fix `15f90f9d1014576e57ad1f3e9ab6a31bb842a0a1` escapes untrusted memory summary/message data and the current question before rendering.

## Verification

Exact fixed implementation SHA `15f90f9d1014576e57ad1f3e9ab6a31bb842a0a1` is under push CI `34026283976`.

Verified so far on that exact SHA:

- `php-quality` — SUCCESS: PHPStan 0 errors; PHPUnit 589/589 tests / 2,423 assertions; Composer audit reports no security vulnerability advisories.
- `js-quality` — SUCCESS.
- `package` — SUCCESS.
- `wordpress-smoke` — still completing at the time this progress record was created; this record must not be treated as final exact-head verification until that permanent job is GREEN.
- Package artifact `9987153515`, 847,563 bytes, digest `sha256:49b3cb6f756f39c1d43beb7945c426a22939e73457a16fb7df4bd2e063a916bc`, tied to exact implementation SHA `15f90f9d...`.

## Independent Review Status

The required Superpowers independent-review path was retried after the fixes. The native reviewer/subagent transport still returns a transient MCP tunnel HTTP 404. GitHub Copilot reviewer requests also did not produce a retained reviewer assignment or review submission.

Therefore Task 5 is **not** marked `INDEPENDENT REVIEW CLOSED`, even though the known coordinator Important findings have regression tests and fixes and no known Critical finding remains.

## Merge State

PR #16 remains open/draft. No merge is permitted because Task 5's independent-review gate is still open and M11 Tasks 6–9 remain unfinished.

## Exact Next Unfinished Action

Re-fetch the current PR head and first obtain a genuine independent correctness/security/prompt-injection review of the Task 5 diff, including the two coordinator-review fixes. The review must cover policy/data separation, delimiter spoofing, prompt-injection resistance, citation-ID exposure, metadata/secret exclusion, deterministic ordering/truncation, complete evidence byte accounting, and bounded work. Fix every Critical/Important finding through fresh regression RED -> GREEN evidence and re-review. Only after zero unresolved Critical/Important findings are independently confirmed should Task 5 be marked **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED** and Task 6 begin with its own test-only behavioral RED.
