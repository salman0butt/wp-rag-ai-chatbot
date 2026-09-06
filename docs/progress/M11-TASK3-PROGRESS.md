# M11 Task 3 — Deterministic Bounded Memory Progress

Status: **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED**

## Design / Plan

M11 design/spec and implementation plan remain **AUTO-APPROVED — SCHEDULED MODE**:

- `docs/superpowers/specs/2026-09-06-m11-rag-chat-orchestration-design.md`
- `docs/superpowers/plans/2026-09-06-m11-rag-chat-orchestration.md`

## Delivered Behavior

Task 3 provides:

- `ConversationHistory`, an owner-scoped history contract requiring both `conversation_id` and trusted `owner_scope` for recent messages and optional summary access.
- `ConversationMemory`, an immutable request-local value containing chronological recent messages plus at most one optional versioned summary.
- `MemoryAssembler`, which requests no more than 12 recent messages, retains the newest contiguous window, drops older messages first when the byte ceiling would be exceeded, preserves chronological output ordering, and enforces a 24 KiB total text ceiling across retained messages and any included summary.
- Versioned summaries are accepted only when the version is positive, the trimmed text is non-empty, and the summary plus retained message content remains within the same 24 KiB ceiling.

## Strict TDD Evidence

Initial test-only commit:

- `68d05fa4bc87c7fdfcc7d311891686e810c71d80` — `test: specify bounded M11 conversation memory`.
- CI `34020220657` stopped in PHPCS on a missing final newline before behavior and is **not** counted as behavioral RED.

Valid behavioral RED:

- `2e063c2fd59334d454d7b17d7399a8f796d52cab` — test-only standards correction.
- CI `34020263699` reached PHPUnit after static analysis and ran 575 tests / 2,301 assertions, failing exactly the five new Task 3 assertions because the planned `ConversationHistory` / memory contracts did not yet exist.

Production implementation:

- `5d640763de1d6d4c48e9d2301bb41f9591e196dc` — `feat: add bounded M11 conversation memory`.
- `153c2e62021950ae06073341cf0bd6f144a9458c` — production type/standards alignment.
- `699370c7349ed5f6ec909d8eb61087ace2d5cbe3` — assembler standards alignment.
- `bddd28a6bf8ddb94d46c16641ba86bc22501746b` — required property type documentation.
- `ac4b99aa6921db0f0fcc6dd5571497a352237fa3` — removes a PHPStan-reported redundant list normalization without changing behavior.

The intermediate production CI failures were PHPCS/static-analysis-only and are not claimed as behavioral GREEN evidence.

## Verification

Exact implementation SHA:

- `ac4b99aa6921db0f0fcc6dd5571497a352237fa3`.
- push-triggered CI `34020490179` — **SUCCESS** across all permanent jobs.

Verified results:

- PHPStan: 0 errors.
- PHPUnit: 575/575 tests, 2,320 assertions.
- Composer audit: no security vulnerability advisories.
- `js-quality`: GREEN.
- `package`: GREEN.
- `wordpress-smoke`: GREEN across activation, database, providers, knowledge, file ingestion, and WooCommerce checks.
- Package artifact `9985321552`, 842,459 bytes, digest `sha256:533e633d00a5a14d553aef7a7add97403ffc8b76766a47ff154401910f84824d`.

The prior documentation head `89a9f42efb685cba2bab17794b5ffba9a1034421` also passed exact-head CI `34020724462`.

## Independent Review

Scoped Task 3 correctness/security/privacy review was recorded on PR #16 against exact implementation SHA `ac4b99aa6921db0f0fcc6dd5571497a352237fa3`.

Review scope:

- trusted owner-scope propagation and prevention of cross-owner transcript access;
- newest-message retention and deterministic chronological output;
- hard 12-message and 24 KiB ceilings;
- total summary + message budget accounting;
- at most one positive-version non-empty summary;
- transcript/privacy leakage boundaries;
- type safety and bounded memory/performance behavior;
- adequacy of tests against the approved M11 design/plan.

Result: **0 Critical / 0 Important findings**.

PR review record: `5124784589`.

No blocking inline review threads exist. No review-fix regression cycle was required.

## Merge State

No merge is permitted yet. M11 Tasks 4–9 remain unfinished.

## Exact Next Unfinished Action

After the Task 3 closeout documentation head receives exact-SHA GREEN CI, begin **Task 4 — citation registry and validator** with a test-only behavioral RED covering deterministic `C1..Cn` IDs in final context order, unknown/duplicate/ill-formed citation handling, model-authored URL non-authority, canonical lineage preservation, and the registry hard limit. Do not add citation production classes until the RED reaches the behavior suite for the expected missing-contract reason.
