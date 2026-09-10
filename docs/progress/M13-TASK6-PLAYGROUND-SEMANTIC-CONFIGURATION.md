# M13 Task 6 — Playground Semantic Configuration

## Status

**COMPLETE as a bounded Task 6 prerequisite.** Task 6 remains **IN PROGRESS**.

This subunit establishes the persisted semantic-runtime identity needed before the Playground can compose the existing M10/M11 retrieval path. It does not construct providers or vector stores, execute retrieval, or expose a REST route.

## Auto-approved design decision

Repository recovery established that the selected vector collection does not contain enough persisted information to reconstruct semantic retrieval by itself:

- the vector-collection record persists a collection key, configuration fingerprint, dimensions, and timestamps;
- `EmbeddingProfile` requires provider ID, model ID, dimensions, and normalization;
- semantic retrieval also requires a concrete vector-search store;
- M09 intentionally left source-specific indexing dependency reconstruction injectable rather than inventing a global runtime default.

The existing `KnowledgeSourceRecord::config` is the repository-owned persisted source-specific configuration boundary. This subunit therefore requires an explicit nested `semantic_retrieval` block there and fails closed when it is absent or malformed.

Persisted shape:

```php
array(
    'semantic_retrieval' => array(
        'embedding_provider_id' => 'openai',
        'embedding_model_id'    => 'text-embedding-3-small',
        'dimensions'            => 1536,
        'normalization'         => 'l2',
        'vector_store_id'       => 'local',
    ),
)
```

No provider, model, normalization mode, dimensions, or vector-store provider is inferred from `collection_id` or supplied through the Playground request.

## Implementation

Added:

- `PlaygroundSemanticConfiguration` — readonly typed carrier for an existing `EmbeddingProfile` plus persisted `vector_store_id`;
- `PlaygroundSemanticConfigurationResolver` — reads only the persisted `semantic_retrieval` block, validates its allow-listed fields, parses the existing `NormalizationMode`, and returns the typed configuration.

The resolver performs no network calls, database queries, generation, embedding, retrieval, scoring, or serialization. Runtime provider/store resolution remains a separate composition responsibility.

## TDD evidence

### Test preparation — NOT RED

Commit `5c6d949dc7bf00d20793956a41472c2227ec6f8d`, CI `34529016929`.

The test-only checkpoint stopped at PHPCS before PHPStan/PHPUnit because of test formatting/docblock violations. It is explicitly **NOT RED**.

### Genuine RED

Commit `96df7c526a63b96fcbea4f0be63e87efc1b9aa38`, CI `34529123849`.

After correcting only test conventions:

- PHPCS passed;
- PHPStan passed with no errors;
- PHPUnit ran **716 tests / 3,031 assertions**;
- the new semantic-configuration tests produced exactly **1 error + 2 failures**, all caused by the intended missing `PlaygroundSemanticConfigurationResolver` class.

This is the genuine RED witness.

### Initial production checkpoint — NOT GREEN

Production files were added by commits `cd1e49e48215ea45721887c8f642a72ca87f3966` and `a9dcc88d7a89b83d5e1b25fc7039a794124e6bce`.

CI `34529286769` on `a9dcc88d7a89b83d5e1b25fc7039a794124e6bce` stopped at four PHPCS assignment-alignment warnings in the resolver before PHPStan/PHPUnit. It is explicitly **NOT GREEN**.

### GREEN

Style-only correction commit `b15207de8091bae02a3639b1626d9315eb6f9876`, CI `34529398321`.

Exact-SHA verification passed all four permanent jobs:

- `php-quality` — GREEN;
- `js-quality` — GREEN;
- `package` — GREEN;
- `wordpress-smoke` — GREEN through environment startup, activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and environment shutdown.

PHP evidence on the GREEN SHA:

- PHPStan: **no errors**;
- PHPUnit: **716 tests / 3,038 assertions — OK**;
- Composer audit: **no security vulnerability advisories found**.

## Scoped cold review

### Correctness

**0 Critical / 0 Important unresolved.** The resolver accepts only explicit persisted semantic identity, validates all required fields, delegates embedding-profile invariants to the existing domain object, uses the existing normalization enum, and fails closed instead of choosing defaults.

### Security

**0 Critical / 0 Important unresolved.** The boundary reads a small allow-listed persisted shape only. It handles no provider credentials, does not expose the source config wholesale, and performs no external calls.

### Performance

**0 Critical / 0 Important unresolved.** Resolution is O(1) array validation and value-object construction with no I/O.

### Accessibility

N/A — this is an internal server-side composition prerequisite with no UI surface.

This scoped review is not represented as the mandatory independent final Task 6 closeout review.

## Required continuation

Before constructing `SemanticRetriever`, the next bounded production-composition subunit must:

1. load the selected persisted vector-collection metadata rather than relying on `collection_id` alone;
2. verify its dimensions/configuration fingerprint are compatible with the resolved `EmbeddingProfile`;
3. resolve the embedding provider through the existing provider registry and the vector-search provider through the existing vector-store registry/composition authority, failing closed for unknown persisted identifiers;
4. compose the existing semantic + lexical retrieval pipeline without adding a Playground-specific retrieval stack;
5. continue toward one request-local `ChatOrchestrator` / `ProductionPlaygroundExecutor` execution with exactly one M10/M11 retrieval path.

The protected `POST /admin/debug/playground` route remains intentionally deferred until the production dependency graph is complete and exact-head GREEN.
