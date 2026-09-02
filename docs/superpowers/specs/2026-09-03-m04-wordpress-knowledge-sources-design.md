# M04 WordPress Knowledge Source Framework Design

Status: AUTO-APPROVED — SCHEDULED MODE

## Goal

Normalize supported WordPress-native knowledge sources into deterministic, traceable `DocumentRecord` instances without introducing chunking, embeddings, file parsing, WooCommerce specialization, or remote crawling.

## Context

M02 already provides persisted `KnowledgeSourceRecord` and `DocumentRecord` domain records plus repositories. M04 should build the source-normalization layer on top of those records instead of introducing duplicate persistence models.

## Approaches considered

### 1. Source classes returning `DocumentRecord` directly — selected

Each source implementation receives a persisted `KnowledgeSourceRecord`, validates its configuration, and yields canonical `DocumentRecord` values. WordPress API access is isolated behind a small gateway interface so unit tests remain deterministic.

Pros: smallest change, reuses M02 contracts, no duplicate document DTO, straightforward persistence handoff in later indexing orchestration.

Cons: source implementations require a persisted source ID and must create deterministic timestamps/version/hash values carefully.

### 2. Introduce a second `NormalizedDocument` DTO

Sources would emit a persistence-agnostic DTO later converted to `DocumentRecord`.

Rejected for M04 because it duplicates nearly every M02 field and adds mapping code before a concrete alternate persistence consumer exists.

### 3. Source callbacks/functions instead of explicit contracts

Rejected because extension discovery, testing, and later source orchestration need a stable contract and registry.

## Selected architecture

### `KnowledgeSource`

A small source contract under `src/Knowledge/Sources/KnowledgeSource.php`:

- `type(): string` returns the stable source-type ID.
- `documents(KnowledgeSourceRecord $source): iterable` yields zero or more `DocumentRecord` instances.

Implementations must reject a source record whose `sourceType` does not match `type()` and must reject unpersisted source records because `DocumentRecord` requires `sourceId >= 1`.

### `KnowledgeSourceRegistry`

A deterministic in-memory registry keyed by source type. It rejects empty IDs, duplicate registrations, and type mismatches. It exposes lookup and all registered source types. A WordPress filter hook may extend the final registry at bootstrap time, but arbitrary callbacks do not replace the source contract.

### WordPress access boundary

`WordPressContentGateway` isolates calls to WordPress post/taxonomy APIs. The production implementation uses WordPress APIs; unit tests use a fake gateway. The gateway returns normalized primitive arrays rather than `WP_Post` objects so domain source logic does not depend on global WordPress classes.

### Initial source implementations

1. `WordPressPostSource`
   - supports posts, pages, and configured public custom post types;
   - configuration accepts an explicit `post_types` list; empty configuration defaults to `post` and `page`;
   - production enumeration requests only public post types and paginates in bounded batches;
   - published content yields `visibility=public`;
   - private content may only be included when `include_private=true`, and then yields `visibility=private`;
   - drafts, pending, auto-drafts, trash, password-protected posts, and unsupported post types are excluded in M04;
   - canonical content is stable plain text derived from title, excerpt/content, and selected taxonomy labels;
   - source version is the WordPress modified GMT value plus post ID;
   - `contentHash` is lowercase SHA-256 over a canonical JSON payload containing identity, title, URL, normalized content, metadata, version, language, and visibility.

2. `ManualTextSource`
   - one source record yields exactly one document;
   - config requires non-empty `text`; optional `title`, `language`, and `visibility` (`public` or `private` only);
   - canonical URL is null;
   - external ID uses the source external ID when present;
   - source version is the source hash when present, otherwise a deterministic hash of canonical config.

3. `FaqSource`
   - config contains an `items` array of `{question, answer}` objects;
   - blank or malformed items are rejected instead of silently indexed;
   - each item yields a separate document with deterministic document key based on source key and item ordinal;
   - content format is deterministic: `Question: ...\nAnswer: ...`;
   - optional source-level language/visibility applies to all FAQ documents.

### Selected URL / sitemap boundary

M04 only defines validated configuration/source-type foundations for selected URLs and sitemap URLs. Remote HTTP retrieval, robots policy, SSRF controls, MIME handling, extraction, retries, and crawling are not implemented in M04 because those concerns belong to later ingestion/crawling work. No source implementation in M04 performs outbound HTTP requests.

## Canonical identity and hashing

Document keys must be stable for the same upstream entity:

- WordPress post: `wp-post:{post_type}:{post_id}`
- Manual text: `manual:{sourceKey}`
- FAQ item: `faq:{sourceKey}:{zero_based_index}`

Hashing uses a single `DocumentHasher` helper that canonicalizes associative keys recursively and hashes JSON encoded with unescaped Unicode/slashes. Timestamps are excluded from the content hash. Metadata ordering must not affect the hash.

## Metadata

WordPress documents include only selected, non-secret metadata required for traceability: post ID, post type, author ID when available, taxonomy term names/slugs, and modified GMT. Arbitrary post meta is not indexed in M04.

Manual/FAQ metadata contains only source type and item identity needed for traceability.

## Security and permissions

- Draft/unpublished content is excluded.
- Private content is excluded by default and requires explicit source configuration.
- Password-protected posts are excluded.
- Arbitrary post meta is not ingested.
- Remote URLs are validated as configuration only; no network fetch occurs.
- Source configuration errors fail closed with domain exceptions and do not produce partial documents for the invalid record.
- No provider credentials or other secrets are accepted by source configs.

## Performance

- WordPress enumeration is paginated; no unbounded `posts_per_page=-1` queries.
- Taxonomy labels are requested in bulk per page when practical to avoid N+1 behavior.
- Source normalization itself performs no embeddings or network calls outside local WordPress APIs.

## Extension contract

Third-party code may register additional `KnowledgeSource` implementations through the registry bootstrap hook. A source type ID must be unique and deterministic. Extensions must emit valid `DocumentRecord` objects and honor M04 visibility/hash invariants.

## Testing

Unit tests cover:

- registry registration/duplicate rejection/lookup;
- hash determinism independent of metadata key order;
- manual text normalization and invalid config rejection;
- FAQ multi-document normalization and malformed-item rejection;
- WordPress post normalization, visibility rules, password/draft exclusion, stable key/version/hash, pagination contract, and public CPT filtering using a fake gateway.

WordPress smoke coverage creates real posts/pages/private posts and verifies the production gateway/source behavior inside wp-env without external API calls.

## Milestone boundaries

Out of scope remain: file parsing (M05), WooCommerce product specialization (M06), chunking/dedup/indexing (M07), embeddings/vector stores (M08), queue/recovery (M09), admin source manager UI (M13), and remote crawling implementation.

## Self-review

- Placeholder scan: no TODO/TBD placeholders.
- Scope: bounded to M04 normalization and extension contracts.
- Security: explicit fail-closed visibility and no remote fetch.
- Testability: WordPress globals isolated behind a gateway; all acceptance criteria have deterministic tests.
- Compatibility: reuses M02 records/repositories and existing PHP-first modular-monolith structure.
