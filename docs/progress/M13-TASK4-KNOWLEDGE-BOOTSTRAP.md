# M13 Task 4 — Knowledge bootstrap/source-loading slice

Status: **COMPLETE SLICE — TASK 4 REMAINS ACTIVE**

## Scope completed

This slice wires the already-reviewed bounded knowledge-source renderer into the established administration bootstrap/router without introducing a second client, a new REST contract, or a client-side source catalog.

- Added `knowledge` to the existing admin navigation and hash router.
- Resolves a selected source ID defensively from the hash and preserves bounded page state.
- Loads `GET /admin/knowledge/sources?page=N&per_page=20` through the existing same-origin, nonce-authenticated admin API client.
- Renders the existing `KnowledgeManagementScreen` with the server-authoritative bounded source page.
- Clears the in-memory knowledge page when leaving Knowledge so returning to the same page refetches server-authoritatively instead of reusing stale client data.
- Keeps the existing Task 1 allow-listed source DTO boundary; no source config/hash, provider credential, raw document body, chunk metadata, or unbounded collection is introduced into browser state.

## Strict TDD evidence

### Initial checkpoint — not RED

`fe12b8a5c8dbe23d1d7d541607ca5905a7c21ca5` / CI `34277143129` stopped at Prettier before Jest. It is therefore not behavioral RED evidence.

### First genuine RED — bootstrap/router loading

`2e2471f9a952221406768caf1b4240c5db40b02b` / CI `34282644712`:

- JavaScript lint passed.
- TypeScript typecheck passed.
- Jest executed 17 suites / 47 tests.
- 46 tests passed and exactly 1 failed because the nonce-authenticated bounded knowledge-source request was absent.

### Initial implementation checkpoint

`a494d2d0c21b5808beec3fd09ff1aabe91e8f7f0` added the minimum Knowledge router/navigation/source-loading behavior. Its CI exposed a stale pre-Knowledge three-link navigation assertion rather than a missing production behavior.

During verification, an over-broad edit to `src-js/index.test.ts` was detected by commit comparison before closeout. The original coverage was restored and only the expected fourth Knowledge navigation link was retained. This is verification evidence, not a behavioral TDD cycle.

`6a361363e398e4329e0a2b8131ab1c2c8c11bb45` then passed JavaScript verification for the initial implementation.

### Second genuine RED — server-authoritative re-entry

Requirement review found that leaving Knowledge and returning to the same page could reuse the previously loaded in-memory page. A focused regression was added before changing production behavior.

`5edf9034d0bab82ea701245768834cf55dd5498b` / CI `34283561496`:

- JavaScript lint passed.
- TypeScript typecheck passed.
- Jest executed 17 suites / 48 tests.
- 47 tests passed and exactly 1 failed: the re-entry scenario expected a third source-page request but observed only two requests.

### GREEN

`64c57e7bdcbb07282cf91838c8b8ddf987ecc4fd` / CI `34284020063`:

- `php-quality`: GREEN.
- `js-quality`: GREEN, including the 48-test suite and live/package gating.
- `package`: GREEN.
- `wordpress-smoke`: GREEN, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and cleanup.

The GREEN production delta for the re-entry correction is exactly one file with five added lines and zero deletions relative to the second RED.

## Review

Scoped correctness/security/accessibility/performance review `5147497514`, anchored to `64c57e7bdcbb07282cf91838c8b8ddf987ecc4fd`:

- Critical: 0.
- Important: 0.
- Correctness: initial entry, bounded page loading, hash-selected context, page changes, and same-page re-entry are server-authoritative.
- Security: the existing same-origin nonce client and allow-listed source DTO are reused; no credential or secret-bearing path was added.
- Accessibility: Knowledge participates in the existing labelled admin navigation with `aria-current="page"`; source selection/empty/pagination behavior remains from the reviewed rendering slice.
- Performance: source state remains bounded to `per_page=20`; no unbounded source catalog or duplicated client cache is introduced.

A separate Superpowers/Codex subagent transport was unavailable in this runtime, so no independent-subagent execution is claimed. The repository-scoped review above is the completed review for this bounded slice; broader final Task 4/M13 independent review remains due at closeout where available.

## Task 4 remains unfinished

Next Task 4 work must begin with a fresh genuine Jest RED and reuse existing server contracts:

1. Load the selected Task 2 source detail and bounded document page for the selected source.
2. Add bounded persisted chunk inspection for a selected document without introducing raw document/source secret fields.
3. Integrate Task 3 bounded job inventory plus enqueue/cancel/retry actions and stable `invalid_transition` UI handling.
4. Complete safe loading/empty/error states plus constrained-width, long-content, keyboard/accessibility, and responsive CSS coverage.
5. Perform final Task 4 correctness/security/accessibility/performance and independent review, then require exact-final-SHA green CI before advancing to Task 5.
