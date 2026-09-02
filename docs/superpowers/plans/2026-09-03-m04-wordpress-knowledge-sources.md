# M04 WordPress Knowledge Source Framework Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build deterministic WordPress-native knowledge source contracts and initial WordPress/manual/FAQ normalizers that emit canonical M02 `DocumentRecord` values.

**Architecture:** Reuse `KnowledgeSourceRecord` and `DocumentRecord`; add a small source contract/registry, a canonical document hasher, and isolate WordPress global APIs behind `WordPressContentGateway`. No outbound HTTP, file parsing, WooCommerce specialization, chunking, embeddings, or UI is introduced.

**Tech Stack:** PHP 8.2+, WordPress APIs, PHPUnit 10, Brain Monkey, PHPCS/WPCS, PHPStan, wp-env/GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-03-m04-wordpress-knowledge-sources-design.md`

## Global Constraints

- Source normalization must be deterministic for equivalent upstream content.
- Reuse M02 `KnowledgeSourceRecord` and `DocumentRecord`; do not create a second document persistence DTO.
- Draft/pending/trash/password-protected content is never emitted.
- Private WordPress content is excluded unless the source explicitly enables it.
- No arbitrary post meta ingestion.
- No outbound HTTP requests in M04.
- PHP production behavior follows strict RED -> GREEN -> REFACTOR TDD.
- Permanent CI jobs `php-quality`, `js-quality`, `wordpress-smoke`, and `package` must pass on the final SHA.

---

### Task 1: Source contract, registry, and deterministic hashing

**Files:**
- Create: `src/Knowledge/Sources/KnowledgeSource.php`
- Create: `src/Knowledge/Sources/KnowledgeSourceException.php`
- Create: `src/Knowledge/Sources/KnowledgeSourceRegistry.php`
- Create: `src/Documents/DocumentHasher.php`
- Create: `tests/Unit/Knowledge/Sources/KnowledgeSourceRegistryTest.php`
- Create: `tests/Unit/Documents/DocumentHasherTest.php`

**Interfaces:**
- `KnowledgeSource::type(): string`
- `KnowledgeSource::documents(KnowledgeSourceRecord $source): iterable`
- `KnowledgeSourceRegistry::register(KnowledgeSource $source): void`
- `KnowledgeSourceRegistry::get(string $type): KnowledgeSource`
- `KnowledgeSourceRegistry::has(string $type): bool`
- `KnowledgeSourceRegistry::types(): array`
- `DocumentHasher::hash(array $payload): string`

- [ ] **Step 1: Write failing registry tests** proving registration/lookup, duplicate rejection, empty type rejection, and deterministic type ordering.
- [ ] **Step 2: Run** `vendor/bin/phpunit tests/Unit/Knowledge/Sources/KnowledgeSourceRegistryTest.php` and record expected RED caused by missing classes.
- [ ] **Step 3: Implement minimum contract/exception/registry** using an associative map keyed by source type; reject empty/duplicate IDs with `KnowledgeSourceException`.
- [ ] **Step 4: Run focused registry test** and require GREEN.
- [ ] **Step 5: Write failing hasher tests** showing recursive associative-key order does not change SHA-256 but list order and content changes do.
- [ ] **Step 6: Run** `vendor/bin/phpunit tests/Unit/Documents/DocumentHasherTest.php` and record RED caused by missing hasher.
- [ ] **Step 7: Implement `DocumentHasher`** by recursively sorting associative arrays, preserving list order, JSON encoding with `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR`, and returning `hash('sha256', $json)`.
- [ ] **Step 8: Run both focused test files** and require GREEN.
- [ ] **Step 9: Commit** `feat: add knowledge source contracts`.

### Task 2: Manual text source

**Files:**
- Create: `src/Knowledge/Sources/ManualTextSource.php`
- Create: `tests/Unit/Knowledge/Sources/ManualTextSourceTest.php`

**Interfaces:**
- Consumes `DocumentHasher`, `KnowledgeSourceRecord`, `DocumentRecord`.
- Produces source type `manual_text`.

- [ ] **Step 1: Write failing tests** for one deterministic document, blank-text rejection, invalid visibility rejection, persisted-source requirement, stable key `manual:{sourceKey}`, null canonical URL, and hash stability.
- [ ] **Step 2: Run** `vendor/bin/phpunit tests/Unit/Knowledge/Sources/ManualTextSourceTest.php`; require expected RED.
- [ ] **Step 3: Implement minimum source**. Use `trim()` for title/text validation, preserve normalized text content, allow visibility only `public|private`, default language null, derive source version from `sourceHash` or canonical config hash, and set both timestamps from source `updatedAt` for deterministic normalization.
- [ ] **Step 4: Run focused test** and require GREEN.
- [ ] **Step 5: Run Task 1 + Task 2 tests** and require GREEN.
- [ ] **Step 6: Commit** `feat: add manual text knowledge source`.

### Task 3: FAQ source

**Files:**
- Create: `src/Knowledge/Sources/FaqSource.php`
- Create: `tests/Unit/Knowledge/Sources/FaqSourceTest.php`

**Interfaces:**
- Produces source type `faq`.
- Each item document key: `faq:{sourceKey}:{index}`.
- Content: `Question: {question}\nAnswer: {answer}`.

- [ ] **Step 1: Write failing tests** for multiple FAQ documents, deterministic ordering/keys/hashes, malformed item rejection, invalid visibility rejection, and persisted-source requirement.
- [ ] **Step 2: Run focused test** and require RED.
- [ ] **Step 3: Implement minimum source** validating `items` as a non-empty list of arrays with non-empty string `question` and `answer`; apply source-level language/visibility.
- [ ] **Step 4: Run focused and related tests** and require GREEN.
- [ ] **Step 5: Commit** `feat: add faq knowledge source`.

### Task 4: WordPress content gateway

**Files:**
- Create: `src/Knowledge/WordPress/WordPressContentGateway.php`
- Create: `src/Knowledge/WordPress/WordPressPost.php`
- Create: `src/Knowledge/WordPress/NativeWordPressContentGateway.php`
- Create: `tests/Unit/Knowledge/WordPress/NativeWordPressContentGatewayTest.php`

**Interfaces:**
- `WordPressContentGateway::publicPostTypes(): array`
- `WordPressContentGateway::posts(array $postTypes, bool $includePrivate, int $page, int $perPage): array`
- Gateway returns immutable `WordPressPost` values containing ID, type, status, title, excerpt, content, URL, modified GMT, language when available, password flag, author ID, and taxonomy labels.

- [ ] **Step 1: Write failing gateway tests** with Brain Monkey/function shims for public post-type filtering, bounded pagination arguments, allowed statuses, password flag, canonical permalink, and selected taxonomy labels.
- [ ] **Step 2: Run focused test** and require RED.
- [ ] **Step 3: Implement minimum native gateway** using `get_post_types(['public' => true])`, `WP_Query`/`get_posts` with explicit page size, `get_permalink`, and taxonomy term retrieval. Never request arbitrary post meta.
- [ ] **Step 4: Run focused tests** and require GREEN.
- [ ] **Step 5: Commit** `feat: add WordPress content gateway`.

### Task 5: WordPress post/page/public-CPT source

**Files:**
- Create: `src/Knowledge/Sources/WordPressPostSource.php`
- Create: `tests/Support/Knowledge/FakeWordPressContentGateway.php`
- Create: `tests/Unit/Knowledge/Sources/WordPressPostSourceTest.php`

**Interfaces:**
- Produces source type `wordpress_posts`.
- Document key `wp-post:{post_type}:{post_id}`.
- Source version `{modified_gmt}:{post_id}`.

- [ ] **Step 1: Write failing tests** for default `post/page`, configured public CPTs, unsupported/non-public type rejection, draft/pending/trash exclusion, private opt-in, password exclusion, canonical normalized content, taxonomy metadata, stable version/hash, and paged gateway consumption.
- [ ] **Step 2: Run focused test** and require RED.
- [ ] **Step 3: Implement minimum source**. Intersect configured types with gateway public types; reject an explicitly configured unsupported type. Iterate page-by-page with a bounded batch size (100) until a short page is returned. Convert only allowed statuses to documents.
- [ ] **Step 4: Normalize content deterministically** from title, excerpt/content, and sorted taxonomy labels; strip unsafe markup through WordPress text conversion functions at the adapter boundary.
- [ ] **Step 5: Run focused tests** and require GREEN.
- [ ] **Step 6: Run all PHP tests** and require GREEN.
- [ ] **Step 7: Commit** `feat: normalize WordPress content sources`.

### Task 6: Registry bootstrap and extension hook

**Files:**
- Create: `src/Knowledge/KnowledgeBootstrap.php`
- Modify: `src/Core/Bootstrap.php`
- Create: `tests/Unit/Knowledge/KnowledgeBootstrapTest.php`
- Modify: `tests/Unit/Core/BootstrapTest.php`

**Interfaces:**
- `KnowledgeBootstrap::register(): void`
- Builds registry with native source implementations and applies `wp_rag_ai_chatbot_knowledge_sources` filter to a list of additional `KnowledgeSource` instances before final registration.

- [ ] **Step 1: Write failing bootstrap tests** proving the plugins-loaded hook is registered and default sources exist after knowledge bootstrap.
- [ ] **Step 2: Run focused tests** and require RED.
- [ ] **Step 3: Implement minimum bootstrap** and integrate it after database bootstrap. Reject filter values that are not `KnowledgeSource` instances.
- [ ] **Step 4: Run focused and full PHP tests** and require GREEN.
- [ ] **Step 5: Commit** `feat: bootstrap knowledge sources`.

### Task 7: Real WordPress smoke coverage

**Files:**
- Create: `scripts/test-wp-knowledge.php`
- Create: `scripts/test-wp-knowledge.sh`
- Modify: `package.json`
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- New command `npm run test:wp:knowledge`.

- [ ] **Step 1: Add smoke script first** that creates a published page/post, private post, draft, and password-protected post; asserts public normalization, private default exclusion/opt-in inclusion, draft/password exclusion, stable key/hash, and cleanup.
- [ ] **Step 2: Wire command into CI and run branch CI**. Before production behavior exists this script must fail for the expected missing/incorrect behavior if introduced earlier in the sequence; otherwise use it as integration GREEN evidence after Tasks 1-6.
- [ ] **Step 3: Fix only integration defects discovered by the real WordPress run**, adding unit regressions first for each production defect.
- [ ] **Step 4: Require `wordpress-smoke` plus all permanent jobs GREEN on the exact SHA**.
- [ ] **Step 5: Commit** `test: cover WordPress knowledge normalization`.

### Task 8: Security/performance review and milestone evidence

**Files:**
- Modify: `docs/milestones/M04-wordpress-knowledge-sources.md`
- Modify: `docs/progress/STATUS.md`
- Modify: `docs/progress/TEST-MATRIX.md`
- Modify: `docs/progress/SECURITY.md`
- Modify when needed: `docs/progress/KNOWN-ISSUES.md`, `docs/progress/TECH-DEBT.md`, `docs/DECISIONS.md`

- [ ] **Step 1: Independently review** correctness, WordPress conventions, visibility/capability boundaries, sanitization, URL handling, data integrity, N+1 risks, pagination, package completeness, test gaps, and milestone leakage. Classify Critical/Important/Minor.
- [ ] **Step 2: For every Critical/Important defect, write a failing regression test first**, capture RED, implement minimum fix, capture GREEN, and re-review.
- [ ] **Step 3: Run full verification**: `composer verify:php`, `npm run verify:js`, WordPress activation/database/providers/knowledge smoke, build, package assertion, and package artifact generation.
- [ ] **Step 4: Update durable ledgers with exact RED/GREEN/final SHAs, CI run IDs, job results, assertion counts, artifact ID/digest/size, review findings/fixes, known limitations, and next action. Do not invent unavailable evidence.**
- [ ] **Step 5: Commit** `docs: record M04 verification evidence`.
- [ ] **Step 6: Create/update PR**, verify exact final-head CI, resolve blocking review threads, and merge only if all gates pass.
- [ ] **Step 7: Verify post-merge `main` CI** before marking M04 complete and allowing M05 to begin.

## Plan self-review

- Spec coverage: every selected source, visibility rule, hash invariant, registry extension, pagination requirement, and real WordPress smoke gate maps to a task.
- Placeholder scan: no TODO/TBD/"implement later" placeholders.
- Type consistency: source/registry/hasher/gateway names and signatures are consistent across tasks.
- Milestone boundary: no file parsing, WooCommerce specialization, chunking, embedding, vector-store, queue, UI, or remote crawling implementation.

Status: AUTO-APPROVED — SCHEDULED MODE
