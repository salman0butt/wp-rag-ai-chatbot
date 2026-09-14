# M16 Design — Conversations, Leads, Feedback & Forms

Status: AUTO-APPROVED under `docs/AUTONOMOUS-DEVELOPMENT.md` routine design authority.

## Goals

M16 turns the existing production conversation/message persistence into an administrator-facing conversation inbox and adds bounded lead capture, ratings/feedback, bot-scoped custom forms, and CSV export without creating a second chat transcript authority.

## Existing authorities to reuse

- `ConversationRepository` / `WpdbConversationRepository` remain the write authority for owner-scoped conversation identity.
- `MessageRepository` / `WpdbMessageRepository` remain the write authority for owner-scoped user/assistant messages and bounded history.
- Existing public chat orchestration remains responsible for chat generation/persistence; M16 must not fork or replay the RAG path.
- Existing bot repository and protected admin REST capability patterns remain authoritative for bot existence and administrator access.
- Existing database migration/version infrastructure remains authoritative for schema evolution.

## Architectural decisions

### 1. Transcript source of truth

Conversations/messages already persisted by M11 are canonical. M16 adds read/query projections over those tables rather than copying transcript content into a new inbox table.

Administrative list/detail queries may join/aggregate canonical tables for started time, latest message time, message count, bounded transcript snippets, search and pagination.

### 2. Explicit bot association

Bot filtering must use an explicit persisted bot association. M16 must never parse or infer `bot_id` from `owner_scope`.

During Task 1 recovery/TDD, inspect the actual public conversation-creation path and introduce the smallest backward-compatible association needed (for example an explicit nullable `bot_id` conversation field plus creation-path wiring). Historical rows without a known bot remain queryable under an `unknown/unassigned` filter rather than being guessed.

### 3. Read/write separation

Introduce administrator-facing read models/repositories instead of expanding the minimal M11 write contracts into unbounded query APIs. Read projections are immutable/bounded and default to finite page sizes.

Conversation deletion is an explicit protected operation and must remove dependent M16 lead/feedback/form submission rows consistently with repository/database policy. Bulk destructive actions are out of scope unless separately specified.

### 4. Leads/contact capture

Lead/contact data is separate from transcript text. A lead record is bot/conversation scoped and contains an allow-listed bounded set such as name, email, phone and optional note/source metadata.

No arbitrary JSON object from a public request is persisted. Public capture validates bot/conversation ownership and rate/size constraints through existing public REST conventions.

### 5. Ratings/feedback

Feedback is conversation scoped and bounded: rating value from a finite enum/range plus optional bounded text. Re-submission policy is deterministic (one latest feedback record per conversation/visitor scope or another explicitly tested repository invariant) and must not alter transcript history.

### 6. Custom forms

Form definitions are bot-scoped administrator configuration with bounded field count and allow-listed field types (initially text, email, phone, textarea, select/checkbox where existing UI conventions support them).

Definitions store labels/options/required flags only. No executable HTML/JS/CSS or arbitrary schema language is accepted.

Form submissions persist against bot/conversation/form identities with bounded normalized values. Rendering uses native controls and text-only labels.

### 7. CSV export

CSV export reads the same protected admin projection used by list/detail queries. It is capability-gated, bounded by explicit filters/date range, streams/escapes spreadsheet-dangerous leading characters, and never exports provider credentials, model authority, embeddings/retrieval configuration, IP addresses or unrelated WordPress user secrets.

### 8. Security/privacy

- Admin routes require the existing administrative capability/nonce authority.
- Public lead/feedback/form routes accept only bot/conversation identifiers and allow-listed bounded payloads.
- Owner-scope checks remain mandatory for public conversation-scoped writes.
- Search terms, page sizes, date ranges and CSV row counts are bounded.
- Personally identifiable lead/form data is exposed only through protected admin routes and exports.
- No raw SQL fragments, field names, sort clauses or export columns are supplied by requests.

### 9. UI/accessibility

The admin inbox reuses the existing native admin rendering architecture. Filters and table/list controls are labelled, keyboard accessible, paginated, and preserve route/query state. Detail views use semantic transcript grouping. Destructive deletion requires an explicit confirmation interaction.

Public capture/form controls use native labelled controls, visible validation, `aria-live`/alert semantics where applicable, and existing localized widget text/catalog authority where visitor-facing labels intersect M15.

### 10. Performance/lifecycle

Indexes should support the proven query shapes only (bot/date/latest-message/conversation foreign keys). List/detail queries are bounded and avoid N+1 transcript loading. CSV export uses bounded/chunked reads. No polling is introduced by default.

## Data migration principle

Schema changes are additive and backward-compatible. Existing M11 conversations/messages remain valid. Historical rows are never assigned fabricated bot/lead/form metadata.

## Task outline

1. Conversation admin read model + explicit bot association + bounded repository queries.
2. Protected admin conversation list/detail/delete REST.
3. Admin inbox/detail UI with search, bot/date filters and pagination.
4. Lead/contact persistence + protected admin projection + public bounded capture.
5. Conversation feedback/rating persistence and public/admin boundaries.
6. Bot-scoped bounded custom form definitions + submissions.
7. Visitor form/lead/feedback runtime integration using existing widget/chat identities.
8. CSV export over protected filtered read projections with spreadsheet-injection hardening.
9. Permanent PHP/JS/real-WordPress smoke, security/accessibility/performance review, PR/merge/post-merge closeout.

## Out of scope

- CRM synchronization/webhooks.
- Marketing automation or arbitrary workflow builders.
- Arbitrary HTML/JS form builders.
- Full-text search engines outside the existing database.
- Analytics dashboards beyond conversation/lead/form records requested by M16.
- Changes to RAG/retrieval/generation authority.
