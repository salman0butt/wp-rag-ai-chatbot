# M13 Task 1 — Knowledge Source Inventory REST Projection

Status: **COMPLETE**

## Scope

Task 1 establishes the first M13 administrator knowledge resource without introducing a parallel knowledge engine.

Delivered:

- bounded, allow-listed `KnowledgeSourceRecord` projection;
- capability-protected `GET /wp-rag-ai-chatbot/v1/admin/knowledge/sources`;
- default pagination of page 1 / 20 items;
- hard `per_page` cap of 100;
- stable `invalid_request` handling before repository access;
- reuse of `WpdbKnowledgeSourceRepository`, `WpdbConnection`, `TableNames`, and `AdminCapability::can_manage`.

Design authority:

- `docs/superpowers/specs/2026-09-08-m13-knowledge-manager-debugger-design.md`
- `docs/superpowers/plans/2026-09-08-m13-knowledge-manager-debugger.md`

Both are **AUTO-APPROVED — SCHEDULED MODE**.

## Security boundary

The inventory DTO exposes only:

- `id`
- `source_key`
- `source_type`
- `external_id`
- `title`
- `canonical_url`
- `status`
- `last_synced_at`
- `updated_at`

It does not serialize persisted source `config` or `sourceHash`.

The focused regression stores `TOP-SECRET-SENTINEL` in source config and `SOURCE-HASH-SENTINEL` as the content/source hash and proves neither appears in serialized output.

No provider credential, encrypted credential, authorization/session material, arbitrary provider payload, mutation route, public asset, or browser-to-provider request was introduced.

## Strict TDD evidence

### Non-counted checkpoints

`3412074d3020648fdbdfa181baddad5ff3ea2dbb` / CI `34247769599` is **not RED**. PHP verification stopped at PHPCS assignment alignment before PHPUnit.

`ca2179870745a46633e6006030003a0aa900d36c` / CI `34248750777` is **not GREEN**. The new M13 behavior passed, but three older route-registration tests still assumed exactly six M12 routes and failed after the seventh protected route was added.

The production implementation was deliberately removed after discovering that the earlier candidate RED had not reached PHPUnit, restoring the pre-feature state before establishing behavioral RED.

### Genuine RED

Exact head: `d60f761b52ab29ae1363cf248435984bcfdb3376`

CI: `34248330696`

`php-quality` evidence:

- PHPStan completed with 0 errors;
- PHPUnit ran 669 tests / 2,779 assertions;
- exactly three new M13 expectations failed:
  - protected `/admin/knowledge/sources` route was not registered;
  - safe source resource did not exist for projection behavior;
  - safe source resource did not exist for invalid-pagination behavior.

This is the Task 1 behavioral RED.

### GREEN

Exact implementation head: `813e14817ec067180b55ac19e09d27995baf348e`

CI: `34248981984`

Results:

- `php-quality` — GREEN;
  - PHPStan 283/283, 0 errors;
  - PHPUnit 669/669 tests, 2,783 assertions;
  - Composer audit: no security vulnerability advisories;
- `js-quality` — GREEN;
- `package` — GREEN;
- `wordpress-smoke` — GREEN through environment start, activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and cleanup.

## Correctness / security / performance review

Scoped review: `5144187297`, anchored to implementation head `813e14817ec067180b55ac19e09d27995baf348e`.

Findings:

- Critical: **0**
- Important: **0**
- unresolved PR review threads: **0**

Correctness: route/request bounds and projection delegate to existing repository truth; no duplicate persistence layer exists.

Security: explicit allow-list prevents config/hash exposure; centralized admin capability remains the authorization boundary.

Performance: one existing bounded page, maximum 100 records, with linear projection and no provider/network or child-collection scan.

Accessibility: N/A for this server-only task.

A separate Superpowers subagent review transport is not exposed in this runtime. No independent-subagent claim is made; the bounded GitHub review path follows the repository's established fallback precedent.

## Implementation commits

- `67bdc18c37c6bd4bb289b1949b72dc2e23d22795` — re-add bounded knowledge source projection after genuine RED.
- `ca2179870745a46633e6006030003a0aa900d36c` — expose protected knowledge inventory after genuine RED; integration route-count harness still needed reconciliation.
- `7ae9e5e27c68fc354a3262886fb1ad8d6bece3c3` — update stale admin route-count expectations.
- `813e14817ec067180b55ac19e09d27995baf348e` — final Task 1 implementation/harness head, exact implementation CI GREEN.

Earlier test/setup commits remain in branch history and are intentionally not relabeled as RED/GREEN.

## Known limitations / remaining M13 scope

Task 1 intentionally does not expose source detail, documents, chunks, jobs, knowledge UI, debug traces, or playground behavior. Those remain Tasks 2-8.

## Exact continuation point

Begin **Task 2 — source detail plus bounded document/chunk inspection** under a new genuine RED. Reuse the existing source/document/chunk repositories; add allow-listed detail DTOs and bounded child resources; do not embed unbounded child collections or expose source config/hash/provider secrets. Prove persisted fixture correlation before advancing to Task 3.
