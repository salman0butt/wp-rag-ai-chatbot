# M14 Task 4A — Public Chat Request Contract

Status: COMPLETE on `feat/m14-frontend-chatbot-customizer` / PR #19.

## Scope

Task 4A introduces a closed public widget request boundary that is intentionally separate from the internal M11 `ChatRequest` runtime contract.

Public callers may supply only:

- `bot_id`;
- `question`;
- optional `conversation_id`.

Unknown keys are rejected, so callers cannot select provider/model authority, grounding mode, output-token budgets, embeddings, vector stores, retrieval limits, credentials, or other server-owned runtime settings.

## TDD evidence

### Genuine RED

- SHA: `a856437360285b4f1f068395a3d4e40a4a9cec15`
- CI: `34691076494`
- Composer validation, PHP coding standards, and PHP static analysis all passed.
- PHPUnit reached five intended `PublicChatRequest contract is missing` failures in `PublicChatRequestTest`.
- The failure was therefore behavioral rather than lint/static/infrastructure failure.

### NOT GREEN

- SHA: `1926dc1f2889d7308869f5ba51ba781b86f54bea`
- CI: `34691133010`
- Production behavior had been implemented, but PHP coding standards stopped the PHP verification path on three alignment warnings before static analysis/PHPUnit.
- This checkpoint is explicitly **NOT GREEN**.

### Genuine GREEN

- SHA: `e92316492404a40e66edbaa866bc83e1f2936e13`
- CI: `34691211030`
- `php-quality`: GREEN, including coding standards, static analysis, PHPUnit, and Composer audit.
- `js-quality`: GREEN.
- `package`: GREEN.
- `wordpress-smoke`: GREEN, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and Playground REST smoke.

## Implementation

`src/Frontend/PublicChatRequest.php`:

- normalizes the existing canonical `BotId` authority;
- trims and bounds questions to 16,384 bytes;
- requires valid UTF-8 question input;
- accepts an optional trimmed opaque conversation identifier bounded to 255 bytes and valid UTF-8;
- rejects empty required values;
- rejects every unknown request key.

No provider, model, retrieval, embedding, vector-store, credential, grounding, or token-budget authority is accepted from the public payload.

## Review

Independent reviewer transport was attempted but unavailable because the external reviewer execution transport returned HTTP 429. The repository-approved scoped fallback review was therefore used and the limitation is recorded honestly.

- Correctness: valid public input is normalized into one immutable value object; malformed bot identifiers are delegated to the existing `BotId` invariant.
- Security/privacy: the key allow-list is closed and prevents request-level runtime-authority injection; the object performs no credential, provider, persistence, retrieval, or network work.
- Performance: validation is bounded string/key work only.
- Architecture/duplication: this is a public boundary object only; it does not duplicate `ChatRequest`, M11 orchestration, retrieval, grounding, prompt construction, memory, citations, or provider selection.
- Accessibility: not applicable to this backend-only contract; widget accessibility remains a later M14 UI responsibility.

Unresolved Critical findings: **0**.

Unresolved Important findings: **0**.

## Continuation

Continue Task 4B with a deterministic abuse-control boundary that executes before expensive retrieval/generation work. Do not store prompt text or credentials in rate-limit keys/state, and do not expose any new public runtime override surface.
