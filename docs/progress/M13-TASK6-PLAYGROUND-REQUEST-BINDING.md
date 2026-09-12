# M13 Task 6 — Playground Request Binding

Status: **COMPLETE subunit — exact-head GREEN; Task 6 remains IN PROGRESS**

## Goal

Define the smallest fail-closed request contract for the protected Playground REST boundary without allowing request-level runtime overrides or creating a parallel RAG implementation.

The request may select only persisted authorities needed to identify the production execution context:

- `bot_id`;
- positive integer `source_id`;
- `collection_id`;
- bounded `question`.

It must reject arbitrary request-level credentials, provider/model overrides, embedding overrides, vector-store options, retrieval limits, and any unknown key.

## Genuine RED

Test-only checkpoint `50acefe4b4303977afdfd94c52ee41e5cd75758a` / CI `34632552362` established the missing request contract.

The PHP verification path reached the intended PHPUnit behavior failure because `PlaygroundRequest` did not exist. The unrelated permanent jobs (`js-quality`, `package`, and complete `wordpress-smoke`) passed. This is counted as genuine RED rather than a style/infrastructure failure.

The regression requires:

- exact-key allow-listing;
- normalized non-empty strings;
- positive integer `source_id`;
- 256-byte selector ceilings;
- the production 16,384-byte question ceiling;
- valid UTF-8;
- rejection of arbitrary extra keys such as `provider_id`.

## Intermediate implementation — NOT GREEN

`113421eaeac8111127fffee8bd5c4a2077682d12` / CI `34635175994` introduced the production request value object but `php-quality` stopped on WordPress coding-standard spacing before the PHP verification suite could establish GREEN.

This checkpoint is explicitly **NOT GREEN**. `js-quality` and `package` passed, but partial CI is not GREEN evidence.

## Genuine GREEN

`ded02d4ccfe33c134ff03444632c7ca21a98f870` / CI `34635364070` corrected only the coding-standard violations while preserving the request behavior.

Exact-head CI is GREEN across all four permanent jobs:

- `php-quality`;
- `js-quality`;
- `package`;
- complete `wordpress-smoke`.

Production behavior now lives in `src/Admin/Rest/PlaygroundRequest.php` and is deliberately limited to normalized persisted selectors plus the bounded question.

## Correctness / security / architecture review

Scoped review found **0 Critical / 0 Important unresolved** for this request-contract subunit.

The contract:

- does not accept credentials;
- does not accept generation-provider/model overrides;
- does not accept embedding-provider/model overrides;
- does not accept vector-store configuration overrides;
- does not accept retrieval limits;
- does not execute retrieval, embedding, generation, or persistence;
- does not expose secrets or provider payloads;
- leaves persisted configuration authority with the existing production resolvers/registries.

A separate independent reviewer/subagent transport was not available in this run, so this scoped review is not represented as the mandatory final fresh independent Task 6 closeout review.

## Recovered continuation point

`AdminRestBootstrap::run_playground()` is still intentionally fail-closed and returns `invalid_request`; it is the next HTTP boundary to bind.

The production-side Task 6 composition already contains the typed request-local pieces needed below that boundary, including the persisted configuration resolvers, production semantic/hybrid/chat graph resolvers, `ProductionPlaygroundExecutor`, and `PlaygroundRestResource`. The remaining work must compose those existing authorities rather than duplicate any RAG stage.

During recovery, `ProviderBootstrap::registry()` was confirmed as the existing generation/embedding provider registry composition authority. `PlaygroundVectorStoreResolver` correctly consumes the existing `VectorStoreRegistry`; there is no standalone `VectorStoreBootstrap` class to call from `AdminRestBootstrap`, so the live vector-store registry/composition ownership must be recovered from the production indexing/retrieval path before the callback is implemented.

## Exact next unfinished work

1. Recover the production vector-store registry/composition ownership used by indexing/retrieval; do not invent a Playground-only registry.
2. Under a new test-only genuine RED, specify valid protected REST request binding from `WP_REST_Request` through `PlaygroundRequest` into the existing persisted configuration and production executor/resource path.
3. Implement the smallest request-local callback composition that executes the existing M10/M11 path exactly once and returns only `PlaygroundRestResource`'s bounded allow-listed projection/stable safe errors.
4. Require exact-head GREEN across all four permanent jobs.
5. Add the remaining REST/integration/WordPress smoke coverage and perform the final fresh Task 6 correctness/security/performance review before marking Task 6 complete.

PR #18 must remain draft and unmerged while Task 6 and later M13 work remain incomplete.
