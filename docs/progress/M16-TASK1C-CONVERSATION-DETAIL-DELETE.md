# M16 Task 1C — Conversation Detail + Delete Semantics

Status: IN PROGRESS

## Scope

Task 1C extends the dedicated administrator read model with one canonical conversation detail projection and then adds an explicit administrator deletion boundary. Conversation/message tables remain authoritative; `owner_scope` is internal isolation metadata and is never exposed or parsed to infer bot identity.

## Detail-read TDD chronology

- RED: `185ee337be0dbc94e58d16ee89c6cf190cb7897b`, CI `34807593942`.
  - Composer validation, PHPCS, and PHPStan passed.
  - PHPUnit reached the new repository test and failed on the missing `find` detail-read boundary.
  - JavaScript quality and package jobs passed; this is genuine behavioral RED, not a prerequisite failure.
- Implementation: `77f59221c204b3b1484d14a0fd9b84a63f80cfd6` added the canonical detail query and bounded transcript projection.
- Contract checkpoint: `46bf1ff08dc0daef811a6710957f8cbf8ad3830b` exposed the bounded detail method through `ConversationReadRepository`.
- NOT GREEN: `46bf1ff08dc0daef811a6710957f8cbf8ad3830b`, CI `34807790563`. PHPCS stopped on assignment alignment before PHPStan/PHPUnit; behavior was not claimed green.
- GREEN: `6c72fb589a2fa05d3d07cd8a39266e072ae1138c`, CI `34807865339`.
  - `php-quality`, `js-quality`, `package`, and full `wordpress-smoke` all passed on the exact SHA.

## Detail-read implementation

- Reads the conversation from the canonical conversations table by stable `conversation_id`.
- Keeps persisted `owner_scope` internal and uses it only to constrain canonical message reads.
- Reads canonical messages in deterministic chronological `id ASC` order.
- Clamps transcript size to 100 messages, matching the established bounded message-history authority.
- Returns immutable `ConversationDetail` / `ConversationDetailMessage` projections with explicit nullable `bot_id`; no owner scope is projected.
- Blank/oversized identifiers and malformed/missing canonical conversation rows fail closed as `null`.

## Review

Repository-approved fallback scoped review for the detail-read slice: **0 Critical / 0 Important**.

- Correctness: canonical conversation/message tables are reused; chronological ordering and message limit are explicit.
- Security/privacy: owner scope stays internal; no credentials, provider/model/retrieval configuration, WordPress user data, or request-controlled SQL identifiers are projected.
- Performance: two bounded prepared reads; transcript capped at 100; no N+1 behavior.
- Architecture/duplication: extends the dedicated M16 read repository instead of widening or replaying the M11 chat/RAG pipeline.
- Accessibility: not applicable to this repository-only slice.

Independent reviewer transport was unavailable in this runtime; no independent review is claimed.

## Remaining Task 1C work

1. Specify missing-conversation detail behavior explicitly.
2. Specify an explicit administrator deletion boundary before implementation.
3. Prove dependent canonical message cleanup and deterministic missing-conversation deletion behavior.
4. Obtain exact-head GREEN and scoped review, then mark Task 1 complete and continue to Task 2 protected admin conversation REST.
