# M14 Task 4 — Public Chat Runtime / REST Closeout

Status: COMPLETE

## Scope

Task 4 established the bounded public chat request/runtime path, explicit abuse controls, production responder composition, owner-scoped conversation history, REST wiring, and real WordPress integration coverage. Public requests do not select credentials, provider/model overrides, embedding/vector-store configuration, or retrieval limits.

The final architecture reuses the existing M10/M11 retrieval/chat authorities exactly once. `PublicChatResponderResolver` is a thin composition adapter over persisted runtime configuration, the existing semantic/lexical/hybrid retrieval authorities, and `ProductionChatResponderFactory`; `PublicChatRuntimeBootstrap` and `PublicChatRestBootstrap` wire that same authority into the public endpoint. No parallel Playground/public RAG graph was introduced.

## Final production-composition chronology

- Public responder contract checkpoint `3506ec2222d4d557ebc05eb8c7341ad32c1ac769` / CI `34716145121`: **NOT RED** because PHPCS failed before PHPUnit executed.
- Repaired responder contract `363e114a9242323dd7680108b6664f8d6662091f` / CI `34716197058`: **genuine RED**. PHPCS and PHPStan passed; PHPUnit reached the intended missing `PublicChatResponderResolver` behavior and failed.
- Production responder composition was implemented in `857f9e144b5dd446d4ecb33245a9017438aa0373`; follow-up formatting/style repair culminated in the later green branch state.
- Public runtime bootstrap test `4508315806169e11280cad42966b12a4c954224f` / CI `34716767779`: **NOT RED** because PHPCS failed before PHPUnit.
- Repaired runtime bootstrap contract `e6fc695f2399dff9b80ae3fe41c0b498367af420` / CI `34716820074`: **genuine RED**. PHPCS/PHPStan passed and PHPUnit reached the intended missing runtime-composition behavior.
- Runtime composition was implemented in `c4e94ee88ca92a76251e4f64519b58bd591b77a2`. Checkpoint `22e66189b0fd49e1363b6aab051bb1623cb60ed8` / CI `34717002003` is **NOT GREEN** because PHPStan still failed; later repairs preserved chronology rather than rewriting it.
- Owner-scoped production conversation history was specified in `9d9a170d7218f9df59016c22e44e7053b34edb96` / repaired test `2e151d8793fe405bf2577fd3d93945b6eabf3b42`, then implemented in `8fc88fee8b9f7ee5bb04e1d38d5ac68ab3dee573`.
- REST production executor composition was specified in `d4657d6ca78d245b3c4d21c7ca7a8adb7e02d499` and implemented in `373f6c4e0d38c9a2db993a6c00846da81f3ff398`.
- Real WordPress public REST integration coverage was expanded at `e850863d5487ee7603547413bdb9c667c8d04b8e`.

Earlier request parsing, runtime authority, public resource, callback, and abuse-order RED/GREEN evidence remains in `docs/progress/M14-TASK4E-PUBLIC-CHAT-REST.md` and the preceding Task 4 progress records.

## Final verification

Exact Task 4 head `e850863d5487ee7603547413bdb9c667c8d04b8e` / CI `34717786952` is **GREEN** across all permanent jobs:

- `php-quality`: composer validation, install, PHPCS, PHPStan, PHPUnit, composer audit;
- `js-quality`: JavaScript verification, dependency audit, provider/Qdrant gating, package assertion;
- `package`: production install, build, plugin zip, package assertion;
- `wordpress-smoke`: activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and Playground/public REST smoke.

The WordPress smoke exercises malformed public requests, rejection of arbitrary request-level runtime overrides, disabled-bot fail-closed behavior, and the existing public abuse-control ordering in a real WordPress REST environment.

## Review

Independent reviewer transport was not available in this execution environment, so the repository-approved scoped fallback review was performed against the final production-composition seams.

- Correctness: **0 Critical / 0 Important unresolved**. REST resolves the persisted runtime, production executor, and responder through one composition root.
- Security/privacy: **0 Critical / 0 Important unresolved**. Request parsing remains closed; provider/runtime selectors are not request-authoritative; disabled bots fail closed; conversation lookup is owner-scoped and memory retrieval is bounded.
- Architecture/duplication: **0 Critical / 0 Important unresolved**. Semantic, lexical, hybrid retrieval, scoring/fusion/reranking, provider/model authority, prompt construction, citations, and generation stay in existing production authorities.
- Performance: **0 Critical / 0 Important unresolved**. Retrieval and conversation-memory bounds remain explicit and no duplicate production execution path was added.
- Accessibility: no new visual UI was introduced by Task 4; Task 5+ UI work retains the milestone accessibility gates.

## Durable next work

Task 4 is complete. Continue M14 Task 5: conditional public asset/bootstrap/shortcode mounting. Assets must load only when applicable, the mount contract must expose only public-safe bot identity/configuration, and no shortcode/block attribute may become a provider/model/credential/vector/retrieval override channel.
