# M13 Task 6 — Playground Access Context

Status: **COMPLETE subunit — Task 6 remains IN PROGRESS**

## Goal

Compose the existing production `ChatAccessContext` from the Playground's already-resolved persisted bot/source/collection selection without accepting request-controlled retrieval policy.

The resolver:

- derives a bounded trusted owner scope from the persisted bot ID;
- constrains semantic retrieval to the persisted source ID;
- constrains lexical retrieval to the persisted collection and source ID;
- resolves semantic vector matches back through the canonical persisted `ChunkLookupStore`;
- rejects a looked-up chunk when its persisted source does not match the selected source;
- keeps single-channel degradation disabled.

It performs no embedding, vector search, lexical search, fusion, reranking, grounding, prompt construction, generation, or duplicate retrieval work.

## Strict TDD evidence

### NOT RED

`a18e799b26c9a8450343457b289647150a9f9621` / CI `34630699244` added the initial access-context regression, but `php-quality` stopped in PHPCS before PHPUnit because of test-only alignment/docblock/spelling conventions. This checkpoint is explicitly **NOT RED**.

### Genuine RED

`3f76197cc259061fdf8624b316160e3f5098050b` / CI `34630874220` corrected test conventions only.

Evidence:

- PHPStan completed cleanly: 311/311 files, 0 errors;
- PHPUnit ran 731 tests / 3,067 assertions;
- exactly one failure remained: `PlaygroundAccessContextResolver is missing.`

This is the genuine RED for the production composition seam.

### GREEN

`ce590c7f6e0a15689bf02a06bee39fe0936a4412` adds `PlaygroundAccessContextResolver` as the minimum production implementation required by the RED.

Exact-head CI `34631079075` is fully GREEN across all four permanent jobs:

- `php-quality`;
- `js-quality`;
- `package`;
- complete `wordpress-smoke`.

## Scoped review

Critical: **0**.
Important: **0**.

Correctness:

- both retrieval channels derive scope from the same persisted source/collection selection;
- canonical semantic chunk hydration uses the existing local lookup authority;
- a canonical chunk from a different source is discarded instead of crossing the selected source boundary;
- the request-local M11 access context remains strict and does not enable single-channel degradation.

Security:

- no credentials, provider IDs, models, embeddings, vector-store options, retrieval limits, filters, visibility values, or arbitrary mandatory filters are taken from the question/request here;
- owner scope is server-derived from the persisted bot identifier;
- this seam adds no serialization surface or raw provider/internal payload exposure.

Performance:

- composition itself is O(1);
- canonical chunk lookup occurs only as the existing semantic retrieval channel resolves actual vector matches;
- there is no extra retrieval pass or provider/network operation.

Accessibility: not applicable to this server-only composition seam.

This is a scoped coordinator review. It does not replace the mandatory fresh independent Task 6 closeout review after full production REST binding/integration is complete.

## Exact next unfinished work

Compose the already-verified Task 6 resolvers into one request-local production execution boundary: resolve persisted configuration and semantic runtime identity, compose the existing semantic and lexical channels into the production `HybridRetriever`, resolve the persisted generation provider and trusted access context, create one request-local `PlaygroundRetrievalCapture`, and use `PlaygroundChatGraphResolver` to return the single `ProductionPlaygroundExecutor` consumed by `PlaygroundRestResource`.

Do not execute retrieval during composition and do not create any parallel Playground RAG implementation.
