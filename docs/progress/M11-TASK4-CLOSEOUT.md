# M11 Task 4 — Citation Registry and Validator Closeout

Status: **COMPLETE / GREEN / INDEPENDENT REVIEW CLOSED**

## Design / Plan

M11 design/spec and implementation plan remain **AUTO-APPROVED — SCHEDULED MODE**:

- `docs/superpowers/specs/2026-09-06-m11-rag-chat-orchestration-design.md`
- `docs/superpowers/plans/2026-09-06-m11-rag-chat-orchestration.md`

## Delivered Behavior

Task 4 adds request-local citation contracts that are derived only from the final trusted retrieval candidates:

- `Citation` carries application-owned `C1..Cn` IDs plus canonical chunk/document/source lineage.
- `CitationRegistry` assigns IDs deterministically in final context order and rejects more than 12 candidates.
- `CitationValidator` resolves only registry-backed markers, preserves first-use answer order, rejects unknown, duplicate, leading-zero, and trailing-junk numeric citation markers, and ignores ordinary non-citation bracketed prose.
- Model-authored URLs are never promoted into canonical citation metadata. Current M10 `RetrievalCandidate` does not expose canonical title/URL fields, so registry-created citations intentionally leave those fields null rather than inventing or trusting model-provided metadata.

## Strict TDD Evidence

Initial test-only commits:

- `f749024e8ebe0c725c9529863dedacfbc7350d91` — registry specification.
- `7dd0aa9313d8732d393221d2bae49507c583a0bf` — validator specification.
- CI `34023103334` stopped in PHPCS before the behavior suite and is **not** counted as behavioral RED.

Valid primary behavioral RED:

- test-only standards corrections through `e7cdc6e93d88cef558013fa9eeee0ad3210424d5`.
- CI `34023154402` reached PHPUnit after PHPStan passed with 0 errors.
- PHPUnit ran 582 tests / 2,327 assertions and failed exactly seven new Task 4 assertions because the citation contracts did not yet exist.

Production sequence:

- `5612d80e6a660c187776303838dde9fe7260646c` — citation lineage value.
- `c2b937cb5da609d94c5c46b29c48ce86da23219e` — bounded deterministic registry.
- `14298eefde711ab0a1b2b4b74354d42c68bbdeb1` — validation result.
- `b4f0b857c11619edc1295a5a54e1afaf221a6c33` — validator implementation.
- `aa7f6efa55719d5a8c8b691932478fe916e2d1bb` — safe readonly registry index initialization.
- documentation/static-analysis corrections through `f21cc9ba21219be94a635fc552228229d3a6ef31`.

The intermediate production CI failures were PHPCS/PHPStan-only and are not claimed as behavioral GREEN evidence.

## Independent Review Findings and Regression TDD

First scoped review on `f21cc9ba21219be94a635fc552228229d3a6ef31`, PR review `5124813259`:

- **Important:** ordinary bracketed prose beginning with `C`, e.g. `[Context]`, was falsely treated as a malformed citation.
- Regression RED `26037f6e58b1c41285d66f13e7971144d0b4d93a`, CI `34023530100`: PHPStan 0 errors; PHPUnit 583 tests / 2,363 assertions; exactly one focused failure proving the false positive.
- Fix `ba4781dfddfcba7d6a83f4067480e9682f743b0d` narrowed discovery to numeric citation shapes while retaining fail-closed canonical validation.

Follow-up review on `ba4781dfddfcba7d6a83f4067480e9682f743b0d`, PR review `5124819766`:

- **Important:** malformed numeric-prefix markers with trailing junk, e.g. `[C1x]`, were ignored rather than rejected.
- Regression RED `8d5195cb73861e5540a2cde4cdaf440911ae27d5`, CI `34023684049`: PHPStan 0 errors; PHPUnit 583 tests / 2,367 assertions; exactly one focused failure proving the gap.
- Fix `11e9ed3d71d40e94b054ad881416344359250356` discovers citation-shaped markers beginning with `C` + digit and lets canonical validation reject trailing junk, while `[Context]` remains ordinary prose.

Final scoped review on exact implementation SHA `11e9ed3d71d40e94b054ad881416344359250356`, PR review `5124827141`:

- **0 unresolved Critical / 0 unresolved Important findings**.
- No blocking inline review threads.

## Verification

Exact implementation SHA: `11e9ed3d71d40e94b054ad881416344359250356`.

Push-triggered CI `34023743426` — **SUCCESS** across all four permanent jobs:

- PHPStan: 0 errors.
- PHPUnit: 583/583 tests, 2,368 assertions.
- Composer audit: no security vulnerability advisories.
- `js-quality`: GREEN.
- `package`: GREEN.
- `wordpress-smoke`: GREEN across activation, database, providers, knowledge, file ingestion, and WooCommerce checks.

Package artifact:

- ID `9986372467`.
- Size 845,133 bytes.
- Digest `sha256:6bddafe631d3a609488efacb61c806efb6a7a7d624f4dc1a92c193beaff8f617`.

## Security / Performance

- Citation IDs are request-local and cannot grant access to content that was not selected into the trusted final candidate list.
- Model-authored links cannot become canonical citation URLs.
- Unknown/duplicate/malformed numeric markers fail closed.
- Registry work is bounded to 12 candidates; validator parsing is linear in the already-bounded generated answer and performs no network/database calls.

## Merge State

No merge is permitted yet. M11 Tasks 5–9 remain unfinished.

## Exact Next Unfinished Action

Begin **Task 5 — prompt/context builder with evidence isolation** with a test-only behavioral RED. The RED must prove retrieved text is placed inside explicit untrusted-evidence delimiters, cannot alter system/application policy, memory/evidence ordering is deterministic, only registry-backed citation IDs are exposed to the model, request-local context remains bounded, and no provider secrets/raw diagnostics enter the prompt. Do not add Task 5 production classes until that test-only SHA reaches the behavior suite and fails for the expected missing-contract reason.
