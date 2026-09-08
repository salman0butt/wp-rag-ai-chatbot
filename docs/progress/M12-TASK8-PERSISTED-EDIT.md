# M12 Task 8 — Persisted Bot Edit Checkpoint

Status: **COMPLETE SLICE; TASK 8 REMAINS ACTIVE**

## Scope

This checkpoint covers persisted editing of an existing bot through the Task 3 REST update contract. It does not close Task 8.

Implemented behavior:

- populated bot management state exposes edit forms prefilled from server-derived bot records;
- edit submission remains inside the SPA and reuses existing required-field validation;
- `PUT /admin/bots/{id}` is issued through the existing same-origin nonce-authenticated typed client;
- the mutation payload includes the current server-provided optimistic `version` plus `name`, `provider_id`, `model_id`, and `enabled`;
- the bot id is URL-encoded;
- the mutation response is not treated as display authority;
- successful update is followed by `GET /admin/bots?page=1&per_page=20`, and the refreshed server page is rendered;
- mutation failures fail closed into the existing admin error state;
- existing `data-bot-id` list-row semantics and create-editor field IDs were preserved after the first implementation exposed compatibility regressions;
- edit fields use per-bot unique label/validation IDs.

## TDD evidence

- `b46a310c1c31ff659b60bde0186edc769458e210` / CI `34184737667`: initial edit regression test checkpoint. Prettier stopped verification before Jest, so this is **not** behavioral RED evidence.
- `d7223bd7792f44a84e0e0d865fbe2161bacab7c4` / CI `34184890050`: **genuine behavioral RED**. JS lint and TypeScript passed; Jest ran 33 tests with exactly the new persisted-edit case failing because no edit form existed: **32 passed / 1 failed**.
- `e1cb36cbd6e17453061f2609f8a7184405b00a29` / CI `34185005665`: minimum production edit path, but Prettier stopped verification before Jest; not counted as GREEN.
- `6146df034060ecc9110214f4b3c0077d9b03f258`: formatting-only production follow-up. Existing tests then exposed compatibility regressions in list-row selectors/text and create-editor IDs; this checkpoint was not accepted as GREEN.
- `c8f033e2d81acb4c07156b0cc115c5ffa35e4796`: tightened the new edit regression without redefining the established list-row contract.
- `acf7a574fdba01b7e7a911add768073ef955ca85` / CI `34185359469`: compatibility-preserving implementation GREEN. `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all passed. JS verification ran **33/33 Jest tests passing**, build, provider/Qdrant/Pinecone/Chroma live-gating, and package assertions.

## Review

Scoped correctness/security/accessibility/performance review `5137169195` on `acf7a574fdba01b7e7a911add768073ef955ca85` found **0 Critical / 0 Important** for this slice.

Review notes:

- correctness: update is bound to the server-derived bot record and optimistic version, then refreshed from server truth;
- security: no new trust boundary or credential exposure; same-origin credentials and `X-WP-Nonce` remain centralized in the typed client;
- accessibility: required labels/validation/focus behavior remain intact and edit controls have unique IDs;
- performance: one PUT plus one bounded authoritative list refresh per save;
- minor/deferred: final Task 8 interaction work should provide clearer selected-record context instead of exposing every edit form simultaneously, and verify narrow-screen usability.

## Exact next unfinished Task 8 work

Under strict TDD, prove that switching/selecting bot records cannot leak unsaved editor state. Implement the minimum deterministic selected-record/editor lifecycle needed for that behavior, while preserving server authority. Then complete delete/archive confirmation, real pagination/hash navigation, narrow/mobile WordPress-admin usability, final Task 8 independent review, exact-final-SHA full CI, and durable Task 8 closeout before advancing to Task 9.
