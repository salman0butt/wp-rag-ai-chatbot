# M16 Task 1 — Conversation Administration Evidence

Status: COMPLETE

## Scope

Task 1 established explicit bot association for canonical conversations, bounded administrator conversation list/detail projections, and explicit administrator deletion semantics while preserving the M11 conversation/message authorities.

## Architecture

- Canonical conversation/message tables remain authoritative.
- Bot identity is explicit and nullable for historical unassigned conversations; `owner_scope` is never parsed to infer a bot.
- Administration reads use the dedicated `ConversationReadRepository` / `WpdbConversationReadRepository` boundary.
- Administrator deletion uses the separate `ConversationAdminRepository` / `WpdbConversationAdminRepository` boundary rather than widening generation writes.
- Detail projections keep `owner_scope` internal and expose only public-safe administration fields.

## Task 1A — Explicit bot association

- Final GREEN: `7be973f5d97b043af5d962cb1e00f0833ecea403`, CI `34801027067`.
- Existing public execution creates a canonical conversation with the validated route bot ID before using the existing responder path exactly once.
- Historical unassigned conversations remain valid.

## Task 1B — Bounded administrator list/read model

Verified GREEN checkpoints:

- Pagination authority: `1ce2c82609af07f7b29fc685dadfb79304679014`, CI `34802027933`.
- Immutable summary projection: `c2d2d5725b5a67f38c6c307e9e83fc2100d4f932`, CI `34802423148`.
- Canonical read repository: `ae185b5f55c954140f230a72c12e19185392d5a1`, CI `34802886455`.
- Bounded bot/date/transcript query authority: `060f5614e995ddd23b97c717beaba43ddfd16c7b`, CI `34803852942`.
- Prepared repository filtering/search: `2d826482d45ec47c1c2b3edfb1d9b3c034774066`, CI `34804366360`.

## Task 1C — Detail projection and administrator deletion

The recovered implementation contains:

- bounded one-conversation detail projection;
- canonical owner-scoped transcript reads;
- transcript limit capped at 100 messages;
- explicit transactional administrator deletion;
- dependent message cleanup before conversation deletion;
- deterministic `false` for a missing conversation;
- rollback plus bounded `DatabaseException` semantics for persistence failures.

A final acceptance review found one Important correctness gap against the checked-in M16 plan: the detail transcript was ordered only by message ID instead of persisted chronology with an ID tie-breaker.

### Chronology RED

- RED SHA: `c7922d21d8dd27f1a8bc1dc3f16d135763b56da2`
- CI: `34810920588`
- Composer validation, PHPCS, and PHPStan passed.
- PHPUnit executed 868 tests and failed exactly one new chronology assertion because production SQL used `ORDER BY m.id ASC` instead of `ORDER BY m.created_at ASC, m.id ASC`.
- This is genuine behavioral RED.

### Chronology implementation checkpoint

- SHA: `4d7a694074603ffaec9ce0320074847b7f2b84f2`
- CI: `34811014002`
- Production SQL was corrected to `ORDER BY m.created_at ASC, m.id ASC`.
- **NOT GREEN:** PHPCS and PHPStan passed, but PHPUnit failed because an older Task 1C test still asserted the superseded `ORDER BY m.id ASC` contract.
- History was preserved; the stale test contract was repaired rather than weakening production behavior.

### Final chronology GREEN

- GREEN SHA: `b45225eda3b01931a907821a42fda77330c53b76`
- CI: `34811065345`
- `php-quality`: GREEN — Composer validation, PHPCS, PHPStan, PHPUnit, Composer audit.
- `js-quality`: GREEN — JavaScript verification, dependency audit, provider gating, Qdrant gating, package assertion.
- `package`: GREEN.
- `wordpress-smoke`: GREEN — activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, Playground REST, and widget surfaces.

## Review

Fallback scoped review after final GREEN:

- Correctness: 0 unresolved Critical / 0 unresolved Important. Transcript ordering now matches the plan (`created_at ASC, id ASC`) and remains bounded.
- Security/privacy: 0 unresolved Critical / 0 unresolved Important. `owner_scope` remains internal; identifiers/values are prepared; no provider/model/retrieval authority or public administration write was introduced.
- Performance: 0 unresolved Critical / 0 unresolved Important. Detail reads remain constrained to one canonical conversation and at most 100 messages; chronology adds only the required stable ordering.
- Architecture/duplication: 0 unresolved Critical / 0 unresolved Important. The change stays within the existing canonical read repository and explicit admin mutation boundary.
- Accessibility: not applicable; Task 1 has no UI.

Independent reviewer transport was unavailable in this runtime; no independent review is claimed. The repository-approved fallback review was used and this limitation is recorded explicitly.

## Next unfinished work

Task 2 — protected administrator conversation REST over the existing `ConversationReadRepository` and `ConversationAdminRepository` authorities. It must preserve administrator capability/nonce protection, bounded query/detail inputs, deterministic not-found/delete behavior, and must not expose administrator writes on public runtime surfaces.
