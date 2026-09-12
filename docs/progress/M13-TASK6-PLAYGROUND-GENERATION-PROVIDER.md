# M13 Task 6 — Playground Generation Provider Resolution

Status: **COMPLETE SUBUNIT**

This record captures the bounded Task 6 production-composition subunit that resolves the generation provider selected by the already-resolved persisted `Bot` configuration.

## Scope

The subunit adds `PlaygroundGenerationProviderResolver`, which accepts the closed `PlaygroundConfiguration` and delegates the persisted `Bot::provider_id` to the existing `ProviderRegistry::generation()` lookup.

It deliberately does not:

- execute generation;
- accept request-supplied credentials, provider IDs, model overrides, or provider options;
- select a fallback provider;
- create a second provider registry or Playground-specific generation stack;
- perform retrieval, scoring, reranking, grounding, prompt assembly, memory work, persistence, or serialization.

## Design decision

AUTO-APPROVED — SCHEDULED MODE.

Use the existing `ProviderRegistry` as the only generation-provider authority. The alternative of resolving providers directly in the future REST route was rejected because it would duplicate composition logic and make fallback/override behavior easier to introduce accidentally. Creating a Playground-specific provider factory was rejected as unnecessary parallel architecture.

## TDD evidence

### Preparation checkpoint — NOT RED

Commit `ed760a7918cda9ad035c23ff075eece46f30662c` / CI `34505228459` stopped during PHPCS before PHPUnit because the new test fixture was missing required parameter and `@throws` documentation. This is not counted as RED evidence.

### Genuine RED

Commit `00273462aefbc9bf053279d830c64019fe3eb614` / CI `34510501349` passed conventions and PHPStan, then PHPUnit ran **713 tests / 3,026 assertions** and produced exactly two errors because `WpRagAiChatbot\Admin\Rest\PlaygroundGenerationProviderResolver` did not exist.

This is the intended behavioral RED for the new resolver contract.

### First production attempt — NOT GREEN

Commit `70a14b6236354aeb3a14d58cf86a7b150b0a77f0` added the minimal production resolver. CI `34510634553` reached PHPUnit but failed because the test fixture used the invalid bot ID `support-bot`; the existing `BotId` contract requires 32 lowercase hexadecimal characters. The production implementation itself was left unchanged.

### GREEN

Commit `5d50bdf1fcca1c418311bbfb072687a3b659098d` changes only the fixture to a valid canonical bot ID. CI `34510755522` completed successfully with all four permanent jobs GREEN:

- `php-quality` — success;
- `js-quality` — success;
- `package` — success;
- `wordpress-smoke` — success, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and environment shutdown.

## Correctness / security / performance review

- Correctness: the resolver returns the exact registered `GenerationProvider` object for the persisted `Bot::provider_id`; unknown IDs retain the existing fail-closed `OutOfBoundsException` behavior.
- Security: no credentials, provider options, or user-controlled provider/model overrides are accepted by this boundary. No fallback provider is selected.
- Performance: one existing in-memory registry lookup; no network, database, retrieval, or generation work is added.
- Accessibility: N/A; this is an internal server-side composition seam.

Scoped review result: **0 Critical / 0 Important unresolved** for this subunit. This is not the mandatory independent final Task 6 closeout review.

## Next unfinished Task 6 work

Recover and implement the authoritative persisted embedding/vector-search composition required by the existing M10 retrieval path. Do not infer an embedding profile or vector-store implementation from `collection_id`. Under a fresh genuine RED, add the smallest typed request-local seam that can resolve those existing production dependencies from repository-owned persisted state. Continue toward one `PlaygroundRetrievalCapture`, one `ChatOrchestrator`, and one `ProductionPlaygroundExecutor`, executing the existing M10/M11 path exactly once. Only after complete production composition is exact-head GREEN should protected `POST /admin/debug/playground` be restored.
