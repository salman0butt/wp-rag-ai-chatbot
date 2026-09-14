# M16 Implementation Plan — Conversations, Leads, Feedback & Forms

Status: AUTO-APPROVED under repository autonomous-development policy.

Design: `docs/superpowers/specs/2026-09-14-m16-conversations-leads-feedback-forms-design.md`

## Execution rules

For every behavior slice: test first, prove intended RED after prerequisite gates, implement minimum production change, prove exact-head GREEN, review, persist evidence, then continue. Never infer bot identity from `owner_scope`; never duplicate transcript/RAG authorities.

## Task 1 — Conversation admin read model + explicit bot association

### 1A. Recover creation path and specify bot association
- Trace every `ConversationRepository::create_for_owner()` production call and public chat creation path.
- Add tests defining a backward-compatible explicit bot association for newly created conversations.
- Historical conversations without a bot remain valid and read as unassigned; no owner-scope parsing/backfill guessing.
- Add the smallest migration/schema/repository change needed and permanent migration coverage.

### 1B. Bounded admin conversation query contract
- Introduce immutable read DTOs for summary/detail rows.
- Add a dedicated read repository instead of bloating the M11 write contract.
- Test pagination bounds, stable ordering, bot filter, date range, message count/latest timestamp and unassigned historical rows.
- Add bounded transcript search over canonical message content with prepared queries; no request-controlled SQL identifiers.

### 1C. Detail projection and delete semantics
- Read one conversation plus bounded chronological transcript from canonical tables.
- Specify/delete only through an explicit admin repository/service boundary.
- Test dependent-row cleanup and missing conversation behavior before implementation.

## Task 2 — Protected admin conversation REST

- RED tests for capability denial, nonce/admin bootstrap conventions, list filters, detail, delete and invalid inputs.
- Reuse established `AdminRestBootstrap` patterns and bot authority.
- Clamp page/page-size/date/search values and map repository DTOs to public-safe admin JSON.
- No provider credentials/model/retrieval configuration in responses.

## Task 3 — Admin inbox/detail UI

- RED Jest tests for route parsing, pagination, search, bot/date filters, empty/error states and detail transcript rendering.
- Implement native labelled controls and semantic transcript groups.
- Add stale-response generation guards following existing bot/appearance/display-rules patterns.
- Add explicit delete confirmation and focus restoration.

## Task 4 — Lead/contact capture

### 4A. Persistence/domain
- Define bounded lead DTO and repository with explicit bot/conversation relation.
- Add migration/table/indexes only for proven query shapes.
- Validate name/email/phone/note/source bounds and normalize safely.

### 4B. Public/admin REST
- Public route accepts only allow-listed lead fields plus trusted conversation context and enforces existing public rate/ownership policy.
- Admin list/detail projection is capability gated and paginated.
- Tests prove cross-conversation writes fail closed and PII never leaks through public bootstrap/config.

## Task 5 — Conversation ratings/feedback

- Define finite rating contract and optional bounded comment.
- Choose deterministic update semantics in tests (one current feedback record per conversation/visitor scope; no transcript mutation).
- Implement public write + protected admin read projection.
- Verify duplicate/retry, missing conversation and ownership behavior.

## Task 6 — Bot-scoped custom forms

### 6A. Definitions
- Admin-only bounded form definition config: finite field types, bounded field count/options/labels, required flag.
- No executable HTML/JS/CSS/schema fragments.
- Persist bot-scoped definitions with protected CRUD and validation.

### 6B. Submissions
- Store normalized form values against bot/conversation/form identities.
- Public submission validates against persisted definition server-side; request cannot invent fields/types.
- Protected admin read projection exposes submissions with pagination/filtering.

## Task 7 — Visitor runtime integration

- Render lead/form/feedback UI through the existing widget runtime; no second visitor runtime.
- Reuse M15 locale/direction/message catalog boundaries where labels overlap.
- RED tests for keyboard labels, errors, successful submission, retries, stale responses and no chat/RAG side effects.
- Keep capture calls distinct from `/chat` generation.

## Task 8 — CSV export

- Add protected export service over the same filtered conversation/lead/form read projections.
- RED tests for capability denial, filters, bounded row/chunk behavior, deterministic columns and CSV escaping.
- Neutralize spreadsheet formula prefixes (`=`, `+`, `-`, `@`) for visitor-controlled cells.
- Never export secrets/provider/model/retrieval/vector data or unrelated WordPress user data.

## Task 9 — Permanent integration and closeout

- Extend real WordPress smoke for conversation admin reads, capture persistence and protected/export boundaries where feasible.
- Run focused suites and full permanent `php-quality`, `js-quality`, `package`, `wordpress-smoke`.
- Scoped independent review where transport exists; otherwise documented repository-approved fallback review across correctness, security/privacy, performance, accessibility and architecture/duplication.
- Resolve all Critical/Important findings.
- Reconcile M16 milestone/global status, create/recover one PR, exact-final-head CI, merge with expected-head protection, verify fresh `main` CI, then activate M17.

## First executable unit

Task 1A: recover all production conversation-creation call sites and write the smallest failing repository/bootstrap test proving an explicit bot association is persisted for new conversations while historical unassigned rows remain valid.
