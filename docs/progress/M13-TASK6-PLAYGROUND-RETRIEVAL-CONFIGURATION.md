# M13 Task 6 — Persisted Playground Retrieval Configuration

Status: **COMPLETE SUBUNIT — Task 6 remains in progress**

## Scope

This subunit establishes the explicit persisted retrieval selector required before the protected Playground route can safely compose the existing M10/M11 production pipeline.

The repository-owned persisted selection is represented by:

- one positive persisted knowledge-source ID resolved through `KnowledgeSourceRepository`;
- one persisted vector `collection_key` resolved through the existing per-site vector-collections table.

The selector does not accept provider credentials, provider/model overrides, arbitrary retrieval limits, raw vector-store options, or a parallel Playground-specific retrieval stack.

## Architecture evidence

M10 already treats source IDs as a trusted bounded retrieval filter. Semantic retrieval applies the source-ID filter to vector search and rechecks it after search. Lexical retrieval requires a portable collection identifier and supports the same source-ID scope. The database persists vector collections under a unique `collection_key`.

`KnowledgeSourceRecord::$config` remains source-specific metadata and is deliberately not treated as a canonical Playground retrieval configuration object.

## TDD evidence

### Convention-only checkpoint — NOT RED

`b3173ab282bb842a836c7723b8f9ce05891fde90` / CI `34479623199` introduced the test-only resolver contract, but PHP CodeSniffer stopped before PHPUnit because the new test file was missing the required blank line after the file comment.

This checkpoint is **not RED**.

### Genuine RED

`f788180526cb38729278b6c5e93e4ab1e50eb795` / CI `34479748996` fixed only that convention issue.

The exact-head PHP job then:

- passed PHP CodeSniffer;
- passed PHPStan with no errors;
- reached PHPUnit;
- ran 707 tests / 2,998 assertions;
- produced exactly 3 errors, all from `PlaygroundRetrievalConfigurationResolverTest` because `PlaygroundRetrievalConfigurationResolver` did not exist.

This is the genuine RED witness.

### Production implementation and non-GREEN checkpoints

`4a8c301bbe7c17e920eb9f7c18e7d0adf705fb8a` added the typed readonly `PlaygroundRetrievalConfiguration` result.

`c3a3f34088d0ea459b33558cad734c616dbbbed7` added the fail-closed resolver. CI `34479906256` stopped in PHP CodeSniffer over assignment alignment, so this SHA is **not GREEN**.

`3f50b4d7c0e9624129ebe3c5c95e5b334402854b` corrected that formatting. CI `34480151905` then reached PHPStan, which rejected an interpolated table-name query because `Connection::prepare()` requires a `literal-string`. This SHA is **not GREEN**.

The root cause was fixed by adopting the same repository-owned typed SQL pattern used by existing wpdb repositories: a literal query with `%i` for the table identifier and `%s` for the collection key.

`5e771ff26885ac2a0fce4ada904f3d550e634e85` updated the test expectation to that typed table-placeholder pattern.

`070c874dc63f0d679d08a6996edccfd24671ad3b` applied the production SQL correction; its PHP job still stopped at one assignment-alignment warning, so it is also **not GREEN**.

### GREEN

`63406d0d1357eac1f31846fe8912e09d24029528` contains the final style-only alignment correction.

Exact-head CI `34480463756` passed all four permanent jobs:

- `php-quality` — GREEN, including conventions, static analysis, PHPUnit, and Composer audit;
- `js-quality` — GREEN;
- `package` — GREEN;
- `wordpress-smoke` — GREEN, including environment start, activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and environment cleanup.

This is the GREEN witness for the subunit.

## Resulting behavior

`PlaygroundRetrievalConfigurationResolver::resolve()` now:

1. rejects source IDs below 1;
2. rejects collection IDs outside the existing portable `[A-Za-z0-9][A-Za-z0-9._-]{0,127}` grammar;
3. performs one exact `KnowledgeSourceRepository::findById()` lookup and fails closed when the source does not exist;
4. performs one prepared lookup against the per-site vector-collections table using `%i` and `%s` placeholders;
5. requires the exact persisted `collection_key` to exist;
6. returns a typed readonly `PlaygroundRetrievalConfiguration` containing the resolved persisted source and collection key.

No fallback collection, default source, provider secret, provider/model override, retrieval execution, scoring, reranking, or network call is introduced.

## Scoped review

Correctness/security/performance review for this bounded subunit found **0 Critical / 0 Important unresolved findings**.

- Correctness: both identifiers must explicitly resolve; missing state fails closed rather than selecting defaults.
- Security: the collection identifier is grammar-bounded and parameterized; the table identifier uses the repository's `%i` placeholder; no request-supplied credentials or provider options enter this seam.
- Performance: one indexed source lookup plus one unique collection-key lookup; no unbounded scan or retrieval work occurs here.
- Accessibility: not applicable to this internal server-side composition prerequisite.

This scoped review is not the mandatory genuinely independent final Task 6 closeout review. That review remains required after production composition, route registration, and integration coverage are complete.

## Exact continuation

Task 6 remains **IN PROGRESS**.

The next safe unit is the smallest request-local production composition boundary that combines:

- the already-complete `PlaygroundBotConfigurationResolver`;
- this `PlaygroundRetrievalConfigurationResolver`;
- the existing provider/embedding/vector/lexical/grounding/prompt/memory/citation construction seams;
- one request-local `PlaygroundRetrievalCapture`;
- one `ProductionPlaygroundExecutor`.

The composition must construct and execute the existing M10/M11 pipeline exactly once. It must scope both semantic and lexical retrieval to the explicit persisted source selection and must not accept arbitrary credentials, provider/model overrides, collection fallbacks, or request-supplied retrieval limits.

Only after that production composition seam is GREEN should the protected `POST /admin/debug/playground` route regression be restored and implemented.
