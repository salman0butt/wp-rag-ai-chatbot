# M14 Task 4B — Public Chat Abuse Controls

Status: COMPLETE on `feat/m14-frontend-chatbot-customizer` / PR #19.

## Scope

Task 4B establishes the deterministic abuse-control boundary that runs before expensive public chat retrieval/generation work.

The implementation intentionally does not compose providers, retrieval, embeddings, vector stores, memory, grounding, or generation. Those remain production authorities owned by later Task 4 subunits.

## Architecture

- `PublicChatRateLimitStore` is the persistence seam for consuming one opaque rate-limit bucket with a bounded request limit and window.
- `PublicChatAbuseGuard` owns the public-chat budget policy.
- The guard accepts only a server-derived lowercase SHA-256 client scope.
- Bot IDs are canonicalized through `BotId`.
- The storage bucket is a SHA-256 digest of the canonical bot ID plus the opaque client scope, prefixed with `wp_rag_public_chat_`.
- Raw IP addresses, user agents, prompt text, credentials, provider/model configuration, retrieval scope, and secrets do not enter the rate-limit store.
- Current budget: 20 requests per 60 seconds for one bot/client scope.

## Strict TDD evidence

### Genuine RED

Commit: `9680187250b49b870228349c40989cb6e62d1a5a`

CI: `34691414614`

The test-only checkpoint specified the missing `PublicChatRateLimitStore` / `PublicChatAbuseGuard` boundary and expected opaque bucket behavior. PHP verification reached PHPUnit and failed on the intended missing behavior; this was a genuine behavioral RED rather than style, static-analysis, or infrastructure failure.

### Additional incomplete RED

Commit: `2469f579b4fe915316db4ea2790ead8aa2a8f6f2`

CI: `34691487653`

This checkpoint remained RED after prerequisite PHP quality stages reached PHPUnit. It is preserved as historical evidence rather than relabeled GREEN.

### NOT GREEN

Commit: `666ea3111ed6d12d7b9ecc56077876ec2309c2a2`

CI: `34691498235`

This implementation checkpoint is explicitly **NOT GREEN** because PHPCS failed before the full required PHP verification completed.

### Genuine GREEN

Commit: `65089bbaf0e734b83e137cca868cbfc584fa6780`

CI: `34691576327`

All permanent gates completed successfully on the exact implementation SHA:

- `php-quality`
- `js-quality`
- `package`
- `wordpress-smoke`

## Review

Scoped fallback review found no unresolved Critical or Important issues.

### Correctness

- malformed/non-opaque client scope is denied without touching persistence;
- accepted requests consume exactly one bounded bot/client bucket;
- store denial stops the request before expensive runtime work.

### Security / privacy

- no raw IP address is accepted by the guard;
- no prompt text or credentials enter the rate-limit key;
- the canonical bot ID and opaque client scope are hashed again before storage;
- no caller-controlled provider/model/embedding/vector-store/retrieval options are introduced.

### Performance

The guard performs deterministic validation, canonicalization, hashing, and one persistence call only. It introduces no provider, embedding, retrieval, or generation work.

### Architecture / duplication

This is an abuse-control boundary only. It does not duplicate the M11 chat/retrieval graph and does not create a frontend-specific RAG implementation.

Independent reviewer transport was not available for this subunit; no independent-review claim is made.

## Exact continuation

Task 4 remains IN PROGRESS.

Next unfinished subunit is Task 4C: persisted runtime resolution. Resolve enabled bot and model/provider/retrieval authority from server-side persisted configuration and existing production composition. Do not accept source, collection, provider, model, embedding, vector-store, grounding, output-token, or retrieval-limit values from public input. If the product model lacks a persisted bot-to-knowledge binding, add only the smallest server-owned binding authority required before composing the existing M11 graph.
