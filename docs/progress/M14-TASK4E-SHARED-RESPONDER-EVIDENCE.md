# M14 Task 4E — Shared Production Responder Evidence

Status: VERIFIED checkpoint on `feat/m14-frontend-chatbot-customizer` / PR #19.

## Purpose

Remove the last duplicate final M11 chat-orchestrator composition from the administrator Playground before wiring the public REST runtime. Both Playground and public execution must reuse the same `ProductionChatResponderFactory`; neither may create a parallel retrieval/generation graph.

## Strict TDD chronology

### Genuine RED

- SHA: `7ad45ce12c4bb21f63cfdebf996d9e36af10f6b7`
- CI: `34715516780`
- Test-only change: `tests/Unit/Admin/PlaygroundChatGraphResolverTest.php`.
- PHPCS: PASS.
- PHPStan: PASS.
- PHPUnit: FAIL exactly at `PlaygroundChatGraphResolverTest::test_resolver_reuses_shared_production_responder_factory` because the resolver still accepted the legacy six graph-construction dependencies instead of `ProductionChatResponderFactory` + `DebugTraceProjector`.
- Result: genuine behavior/architecture RED.

### Implementation checkpoint — NOT GREEN

- Complete implementation head: `b77107eab4360ee703382e81c0f8643e1083eb10`.
- CI: `34715588566`.
- `PlaygroundChatGraphResolver` delegated responder creation to `ProductionChatResponderFactory` and `PlaygroundRuntimeBootstrap` composed that factory from the established M11 authorities.
- PHPCS failed on docblock parameter spacing before PHPUnit executed.
- Result: explicitly **NOT GREEN**. No GREEN claim is made for this SHA.

### Genuine GREEN

- SHA: `8a3a2069d4cca25aa852a20a2d21296b96e26505`.
- CI: `34715666290`.
- The only follow-up change corrected the PHPCS docblock spacing issue.
- `php-quality`: PASS, including Composer validation, PHPCS, PHPStan, PHPUnit, and Composer audit.
- `js-quality`: PASS.
- `package`: PASS.
- `wordpress-smoke`: PASS across the full supported WordPress smoke matrix.
- Result: exact-head genuine GREEN.

## Scoped fallback review

Independent reviewer/subagent transport is not available in this execution environment, so no independent-review claim is made. The repository-approved structured fallback review was performed.

### Correctness

- Playground still receives the same `HybridRetriever`, `GenerationProvider`, trusted `ChatAccessContext`, retrieval observer, persisted model id, and strict grounding mode.
- `ProductionChatResponderFactory::create()` is invoked once and the returned responder is wrapped once by `ProductionPlaygroundExecutor`.
- No request behavior or public runtime contract was broadened.

Unresolved Critical findings: 0.
Unresolved Important findings: 0.

### Security / privacy

- No credentials, provider/model overrides, retrieval parameters, or caller-controlled runtime settings were added.
- Trusted access context remains outside request-level authority.

Unresolved Critical findings: 0.
Unresolved Important findings: 0.

### Performance

- The refactor replaces duplicate object composition; it does not add retrieval, generation, vector-store, or provider calls.
- Retrieval/generation still execute through the existing production responder exactly once.

Unresolved Important findings: 0.

### Accessibility

Not applicable: no UI behavior changed in this checkpoint.

### Architecture / duplication

- Final M11 responder construction is now centralized in `ProductionChatResponderFactory` for the Playground path.
- The Playground no longer constructs `ChatOrchestrator` independently.
- Semantic retrieval, lexical retrieval, fusion, confidence/reranking, grounding, prompts, memory, citations, provider selection, embeddings, and vector-store logic remain delegated to existing authorities.

Unresolved Important findings: 0.

## Exact next unfinished Task 4E work

Continue under strict TDD with the public persisted-runtime composition seam:

1. adapt `PublicChatRuntime` and its persisted knowledge source/collection into the existing retrieval configuration authorities;
2. reuse the existing semantic, hybrid/lexical, generation-provider, and `ProductionChatResponderFactory` authorities exactly once;
3. preserve trusted bot-scoped access and persisted model authority through `PublicChatProductionExecutorResolver`;
4. replace the fail-closed placeholder factory in `PublicChatRestBootstrap` only after the resolver has genuine RED/GREEN evidence;
5. preserve stable non-sensitive `chat_unavailable` behavior and introduce no request-level runtime overrides or parallel RAG graph.

After the production executor composition seam is exact-head GREEN, proceed directly to Task 4F real WordPress integration/smoke coverage and final scoped review, then continue to Task 5 if Task 4 closes cleanly.
