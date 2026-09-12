# M13 Task 6 — Typed Playground Configuration Boundary

Status: **COMPLETE SUBUNIT — production composition remains next**

## Scope

This subunit introduces a closed request-local configuration aggregate for the production Playground composition root. It carries only:

- the enabled persisted `Bot` already resolved by `PlaygroundBotConfigurationResolver`;
- the explicit persisted source/collection selection already resolved by `PlaygroundRetrievalConfigurationResolver`.

It does not accept request-level provider credentials, provider/model overrides, retrieval limits, vector-store options, or other arbitrary configuration.

## TDD evidence

- Test-only preparation `7edaf4beb280775d5391cfb03db8e645b41ba34b` / CI `34482421862` stopped at PHPCS because the test class/method doc comments were missing. This is **not RED**.
- Genuine RED `e566159d71ff0b2e91babcc2ec033e9664a931a9` / CI `34482637733` passed conventions and PHPStan, then PHPUnit ran 708 tests / 3,009 assertions with exactly one error: `PlaygroundConfiguration` did not exist.
- GREEN `7ce7452b17a3a77f887fa315fd1c7257cce5213e` / CI `34482774473` passed all four permanent jobs: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.

## Result

`PlaygroundConfiguration` is a final readonly aggregate with exactly two public values:

- `Bot $bot`;
- `PlaygroundRetrievalConfiguration $retrieval`.

The object copies no secrets and performs no provider calls, retrieval, scoring, reranking, persistence, or serialization.

## Review

Correctness: the aggregate preserves the exact already-resolved persisted objects by identity and introduces no defaults.

Security/privacy: the contract is closed to the two repository-owned resolved configuration values and therefore does not create an input surface for arbitrary credentials, provider options, model overrides, collection fallbacks, or request-supplied retrieval limits.

Performance: construction is O(1) and stores two object references.

Accessibility: not applicable to this server-side composition contract.

No Critical or Important finding was identified in this bounded subunit review. The mandatory independent final Task 6 review remains required after the complete production composition and protected REST route exist.

## Exact next unit

Under a fresh strict-TDD cycle, introduce the smallest request-local production composition/resolver seam that receives explicit bot/source/collection identifiers, resolves them through the existing `PlaygroundBotConfigurationResolver` and `PlaygroundRetrievalConfigurationResolver`, and returns/uses this closed `PlaygroundConfiguration` while constructing the existing M10/M11 provider, embedding/vector, lexical, grounding, prompt, memory and citation dependencies.

The production composition must use one `PlaygroundRetrievalCapture`, one `ChatOrchestrator`, and one `ProductionPlaygroundExecutor`; it must execute M11 exactly once and must not create a second Playground-specific retrieval path or accept arbitrary request-level runtime options.

Only after that composition is GREEN should the protected `POST /admin/debug/playground` regression be restored and implemented.
