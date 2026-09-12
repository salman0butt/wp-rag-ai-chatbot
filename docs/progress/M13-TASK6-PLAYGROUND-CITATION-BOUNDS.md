# M13 Task 6 — Playground Citation Lineage Bounds

Status: **COMPLETE prerequisite — exact-head GREEN**

## Scope

Fresh-session review of the completed Playground REST success projection found one remaining Important boundedness defect: citation `chunk_id` and `document_id` values crossed the administrator REST boundary verbatim even though `Citation` permits arbitrary non-empty lineage strings. Task 5 established a 256-byte UTF-8-safe scalar boundary for diagnostic identifiers, so the Task 6 REST projection must preserve the same hard bound.

This correction is projection-only. It does not alter retrieval, scoring, fusion, reranking, grounding, prompt construction, memory, citations, provider selection, embedding selection, or vector-store selection.

## TDD evidence

### Genuine RED

Test-only SHA `dee49f4b6bfd12a2d40ba724f9f42b838d2fab8d` / CI `34669687968` added `PlaygroundCitationBoundsTest`.

The exact-head workflow failed only in `php-quality` at `composer verify:php`; `js-quality`, `package`, and complete `wordpress-smoke` passed. The regression supplied overlong multibyte `chunk_id` and overlong `document_id` values through the typed `Citation`/`ChatResult` success path and required each projected lineage scalar to be at most 256 bytes and valid UTF-8. The then-current `PlaygroundRestResource` copied both values verbatim, so the behavioral contract was unmet.

### GREEN

Production SHA `bbf0a03b95cf359a9a5c4ea643bf323ca24008de` / CI `34671321083` is exact-head GREEN across all permanent jobs:

- `php-quality` — GREEN (`composer verify:php` and Composer audit pass);
- `js-quality` — GREEN;
- `package` — GREEN;
- `wordpress-smoke` — GREEN, including activation, database, providers, knowledge, file-ingestion, WooCommerce knowledge, and environment cleanup.

The minimal production change adds a 256-byte citation-lineage ceiling and reuses the existing UTF-8-safe byte-bound helper for `chunk_id` and `document_id` before REST projection.

## Review

Fallback correctness/security/performance/architecture review found:

- **Critical:** 0 unresolved.
- **Important:** 0 unresolved for this subunit.
- The bound lives at the administrator DTO projection boundary, leaving canonical retrieval/citation lineage untouched internally.
- No raw provider payloads, credentials, arbitrary request-level overrides, or exception messages are newly exposed.
- Work remains O(1) per citation and reuses the existing projection helper.
- No parallel Playground retrieval or generation path is introduced.

A genuinely independent final Task 6 review remains required before Task 6 can close.

## Continuation

Task 6 remains in progress. Production runtime composition, the protected REST callback, and exact-retrieval integration are now present. Continue with the missing WordPress-level Playground route/smoke evidence, then perform final Task 6 correctness/security/performance/architecture review, repair every Critical/Important finding under strict RED → GREEN, require exact-final-head four-job GREEN, and only then mark Task 6 complete and begin Task 7.
