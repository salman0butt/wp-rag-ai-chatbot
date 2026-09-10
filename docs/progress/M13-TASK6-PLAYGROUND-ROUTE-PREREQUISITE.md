# M13 Task 6 — Playground Route Composition Prerequisite

Status: **RECOVERED — persisted bot selection complete; route registration deferred until persisted retrieval composition is resolved**

## Why this checkpoint exists

Task 6 already has the bounded/sanitized Playground resource, typed execution contracts, request-local exact-retrieval capture, and `ProductionPlaygroundExecutor`. The next apparent step was to register `POST /admin/debug/playground` in `AdminRestBootstrap`.

A test-first route probe showed that registration itself is not the next safe production change. The M13 design requires the Playground request to select existing persisted bot/retrieval configuration and forbids arbitrary provider keys/secrets or a parallel retrieval/provider path. `AdminRestBootstrap` does not yet own a production M10/M11 composition root that resolves those identifiers into the existing provider/retrieval/grounding/prompt/citation dependencies.

Registering a question-only callback now would therefore either:

- expose a route that cannot execute the production pipeline;
- silently choose arbitrary provider/model/retrieval state; or
- tempt a second, Playground-specific retrieval/provider stack.

All three violate the M13 design and autonomous-development correctness gates.

## Test-first route probe

`094ae2fb61ab4b0a948445c28b450eb027e0e128` added a route-registration regression requiring:

- namespace `wp-rag-ai-chatbot/v1`;
- `POST /admin/debug/playground`;
- callback `AdminRestBootstrap::run_playground`;
- centralized `AdminCapability::can_manage` permission enforcement.

CI `34465532247` passed PHP lint/static analysis and reached PHPUnit:

- 702 tests;
- 2,991 assertions;
- exactly one error because the Playground route was not registered.

This was a **genuine RED for the route-registration behavior**, but architecture recovery showed the test was ordered before its production-composition prerequisite. It was intentionally not greened with a placeholder or unsafe callback.

`3c2808119362ee9a24be6f2b2e48831d4663dc5e` removes only that premature route test, restoring the branch to the previously verified production behavior while retaining this durable finding. This removal is not represented as GREEN implementation evidence for route registration.

## Persisted bot selection — complete subunit

The first production-composition prerequisite is now implemented by `PlaygroundBotConfigurationResolver`.

- Convention-only test checkpoints `3062408a061d3a62ce71a11acb1a13d1f405e528` / CI `34468544863` and `27a48d0edcd73d9813d3988218f15a3f0fa92ff9` / CI `34468635844` stopped at PHPCS and are **not RED**.
- Genuine RED `c9f810dcd6dd06faa6ec926a095dc18eb1a621c3` / CI `34468732722` passed static analysis and reached PHPUnit: 704 tests / 2,991 assertions / exactly 3 errors because `PlaygroundBotConfigurationResolver` did not exist.
- GREEN implementation `c68957b3cc25efde62f996b881f10ab7253565c6` / CI `34468976306` passed all four permanent jobs, including complete WordPress smoke.

The resolver accepts one canonical persisted `BotId`, performs one exact `BotRepository` lookup, rejects missing or disabled bots, and returns the persisted aggregate unchanged. This preserves the repository-owned `provider_id` and `model_id` instead of accepting request-supplied provider/model overrides or silently selecting a fallback bot.

Detailed evidence is recorded in `docs/progress/M13-TASK6-PLAYGROUND-BOT-CONFIGURATION.md`.

This completes only persisted bot/provider/model selection. It does **not** claim the remaining persisted retrieval configuration is resolved.

## Production composition requirement

Before the route test is reintroduced, Task 6 must establish the smallest request-local production composition seam that:

1. accepts explicit existing persisted bot/retrieval configuration identifiers from a bounded administrator request;
2. resolves them through repository-owned persistence/registries rather than accepting provider credentials or arbitrary provider options;
3. constructs the existing M10/M11 runtime dependencies rather than a Playground-specific pipeline;
4. instantiates one request-local `PlaygroundRetrievalCapture`;
5. injects that capture into the same `ChatOrchestrator` that executes the request;
6. executes M11 exactly once;
7. passes the exact captured M10 `RetrievalResult` through Task 5 `DebugTraceProjector`;
8. returns the existing typed `PlaygroundExecutionResult` to `PlaygroundRestResource`;
9. fails closed with repository-owned sanitized errors when persisted configuration cannot be resolved.

The composition must not run retrieval separately before invoking M11 and must not silently select a default provider/model when the design requires an explicit persisted selection.

## Retrieval-state recovery

`src/Retrieval/RetrievalConfig.php` is an immutable execution-bounds value object, not itself a persisted configuration repository. Knowledge sources persist source-specific `config` data through `KnowledgeSourceRecord`, but that record is not evidence of a complete persisted Playground retrieval selector by itself.

Therefore the next run must continue recovery rather than treating either structure as the required persisted retrieval configuration without proof.

## Exact next unit

Recover the concrete persisted retrieval/collection/configuration selector and existing provider/vector/lexical/grounding/prompt/citation construction seams. Under a new test-first cycle, introduce the smallest typed resolver/composition boundary necessary to combine the already-resolved persisted `Bot` with the explicit repository-owned retrieval selection and create one `ProductionPlaygroundExecutor` request. Only after that unit is GREEN should the protected Playground route regression be restored and implemented.

## Completion boundary

Task 6 remains **IN PROGRESS**. This checkpoint does not claim route completion, Task 6 completion, independent final review, or merge readiness.
