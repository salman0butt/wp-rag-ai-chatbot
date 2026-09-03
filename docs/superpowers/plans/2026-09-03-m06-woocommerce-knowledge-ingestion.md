# M06 WooCommerce Knowledge Ingestion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

Status: AUTO-APPROVED — SCHEDULED MODE

**Goal:** Add optional-safe WooCommerce catalog ingestion that normalizes stable public product facts into deterministic canonical documents while excluding live transactional/customer state.

**Architecture:** A WooCommerce catalog gateway isolates native WooCommerce APIs and returns immutable allowlisted product snapshots. `WooCommerceProductSource` maps snapshots to existing `DocumentRecord` contracts and is registered through `KnowledgeBootstrap`; real WordPress/WooCommerce smoke proves optional-plugin behavior and product normalization.

**Tech Stack:** PHP 8.2+, WordPress 6.9+, optional WooCommerce, PHPUnit/Brain Monkey, GitHub Actions WordPress smoke.

**Spec:** `docs/superpowers/specs/2026-09-03-m06-woocommerce-knowledge-ingestion-design.md`

## Global Constraints

- WooCommerce remains optional; inactive/missing WooCommerce must not break activation or non-commerce knowledge sources.
- Index stable catalog facts only: name/descriptions/SKU/categories/tags/descriptive attributes/stable variation identity/options/canonical URL.
- Never index or source-version current price, sale state, stock/availability, cart/order state, active discounts, customer-specific data, or arbitrary product/post meta.
- Public eligibility fails closed: publish-only, non-password-protected, and not intentionally hidden from both catalog/search.
- One canonical document per product in M06; variations remain structured product metadata.
- Whole-catalog enumeration is deterministic and paginated; default 100, hard maximum 250 per page.
- M07 owns stale-index reconciliation/incremental indexing; M09 owns long-running background execution; M18 owns live commerce actions.
- Strict TDD: every production behavior begins with a genuine failing test on the exact predecessor SHA.

---

### Task 1: WooCommerce catalog contracts and immutable snapshots

**Files:**
- Create: `src/WooCommerce/Catalog/WooCommerceCatalogGateway.php`
- Create: `src/WooCommerce/Catalog/WooCommerceProduct.php`
- Create: `src/WooCommerce/Catalog/WooCommerceVariation.php`
- Test: `tests/Unit/WooCommerce/Catalog/WooCommerceProductTest.php`
- Test: `tests/Unit/WooCommerce/Catalog/WooCommerceCatalogGatewayContractTest.php`

**Interfaces:**
- Produces: `WooCommerceCatalogGateway::isAvailable(): bool`
- Produces: `WooCommerceCatalogGateway::product(int $productId): ?WooCommerceProduct`
- Produces: `WooCommerceCatalogGateway::productIds(int $page, int $perPage): array`
- Produces immutable `WooCommerceProduct` / `WooCommerceVariation` records containing only approved stable facts.

- [ ] **Step 1: Write failing contract/invariant tests**

Cover invalid/non-positive IDs, blank product names, unsupported status/visibility states, normalized unique/sorted category/tag labels, deterministic attribute/variation ordering, and rejection/absence of live fields such as price/stock/customer data.

Example assertion shape:

```php
$product = new WooCommerceProduct(
    id: 42,
    type: 'variable',
    status: 'publish',
    catalogVisibility: 'visible',
    name: 'Trail Shoe',
    shortDescription: 'Light trail shoe.',
    description: 'Stable descriptive copy.',
    sku: 'TRAIL-42',
    canonicalUrl: 'https://example.test/product/trail-shoe/',
    categories: array( 'Shoes' ),
    tags: array( 'Trail' ),
    attributes: array( 'Color' => array( 'Blue', 'Red' ) ),
    variations: array(),
    modifiedGmt: '2026-09-03T00:00:00+00:00'
);

self::assertSame( 42, $product->id );
self::assertFalse( property_exists( $product, 'price' ) );
self::assertFalse( property_exists( $product, 'stockStatus' ) );
```

- [ ] **Step 2: Verify genuine RED**

Run the focused PHPUnit file through the repository-approved CI path. Expected failure: missing M06 contract/classes, not style/bootstrap errors. Record exact SHA/run in the milestone ledger.

- [ ] **Step 3: Implement minimum immutable contracts**

Use readonly PHP records/classes, explicit constructor invariants, and docblock/static types consistent with existing project conventions. Do not reference `WC_Product` in the contract.

- [ ] **Step 4: Verify focused and full GREEN**

Run PHP quality/unit suite and Composer audit on exact implementation SHA.

- [ ] **Step 5: Independent contract review and commit**

Review for live-data leakage, invalid identity, ambiguous ordering, and future-milestone scope leakage. Fix Important/Critical findings through regression TDD.

---

### Task 2: Optional-safe native WooCommerce catalog gateway

**Files:**
- Create: `src/WooCommerce/Catalog/NativeWooCommerceCatalogGateway.php`
- Test: `tests/Unit/WooCommerce/Catalog/NativeWooCommerceCatalogGatewayTest.php`

**Interfaces:**
- Consumes: Task 1 catalog contracts.
- Produces: optional-safe native implementation using public WooCommerce APIs only.

- [ ] **Step 1: Write failing availability/enumeration tests**

Required cases: WooCommerce functions unavailable -> `isAvailable() === false`, empty IDs, `product()` returns `null`; invalid page/per-page rejected; `perPage > 250` rejected; enumeration requests deterministic ascending IDs; missing/deleted product returns `null`.

- [ ] **Step 2: Verify RED on test-only SHA**

Expected failures must be caused by absent native gateway behavior.

- [ ] **Step 3: Implement optional detection and bounded enumeration**

Use runtime `function_exists()` / public API checks without hard bootstrap dependency. Use `wc_get_products()` with bounded paging, `return => ids`, deterministic ID ordering, and publish/public visibility constraints where supported.

- [ ] **Step 4: Add product normalization TDD**

Tests cover simple and variable products, SKU, permalink, categories/tags, descriptive attributes, variation ID/SKU/options, modified marker, password/non-public exclusion, and explicit omission of price/stock/customer/private metadata.

- [ ] **Step 5: Implement minimum native mapping**

Translate `WC_Product` / variation APIs into immutable snapshots. Do not call private data stores or copy arbitrary meta.

- [ ] **Step 6: Verify/review/commit**

Run permanent PHP gates and security/privacy review. Confirm no native WooCommerce class is required at plugin load time.

---

### Task 3: WooCommerce product source canonical mapping

**Files:**
- Create: `src/Knowledge/Sources/WooCommerceProductSource.php`
- Test: `tests/Unit/Knowledge/Sources/WooCommerceProductSourceTest.php`

**Interfaces:**
- Consumes: `WooCommerceCatalogGateway`, `KnowledgeSourceRecord`, `DocumentHasher`.
- Produces: source type `woocommerce_product`, one `DocumentRecord` per eligible product.

- [ ] **Step 1: Write failing source tests**

Cover disabled gateway -> no documents; configured product IDs; whole-catalog paging; malformed config; stable document key/external ID/type/title/URL/visibility; deterministic readable content and allowlisted metadata.

Expected key:

```php
self::assertSame( 'woocommerce_product:42', $document->documentKey );
self::assertSame( '42', $document->externalId );
self::assertSame( 'woocommerce_product', $document->documentType );
self::assertSame( 'public', $document->visibility );
```

- [ ] **Step 2: Verify genuine RED**

Run focused tests on a test-only SHA and record the expected missing-source failures.

- [ ] **Step 3: Implement source configuration and mapping**

Support explicit positive product IDs or catalog mode with bounded page size. Reject malformed/ambiguous configuration instead of silently broadening selection.

Build deterministic content sections for name, SKU, descriptions, categories, tags, attributes, and variation descriptors. Use existing `DocumentHasher` for final content hash.

- [ ] **Step 4: Verify GREEN**

Run focused tests, full PHP quality, Composer audit, JS/package gates where CI requires them.

- [ ] **Step 5: Review and commit**

Review deterministic ordering, duplicate IDs, empty products, metadata allowlist, and path from source config to canonical documents.

---

### Task 4: Stable source version and live-state exclusion regressions

**Files:**
- Modify: `src/Knowledge/Sources/WooCommerceProductSource.php`
- Test: `tests/Unit/Knowledge/Sources/WooCommerceProductSourceVersionTest.php`

**Interfaces:**
- Produces deterministic stable source version independent of live transactional values.

- [ ] **Step 1: Write failing version tests**

Create snapshots identical in stable fields but differing in synthetic live-only fixture values outside the snapshot contract; prove emitted version/hash stays identical. Then change SKU/description/category/attribute/variation identity and prove source version changes.

- [ ] **Step 2: Verify RED for any missing deterministic version behavior**

Do not add fake live fields to production snapshots merely to test exclusion; test the source-version input builder directly or via snapshot pairs.

- [ ] **Step 3: Implement canonical stable snapshot hashing**

Canonicalize ordered scalar/array inputs and SHA-256 hash them. Source version/hash must derive only from canonical allowlisted stable facts; generic WooCommerce `modifiedGmt` is observational and must not participate because it can advance for excluded live-state changes such as price/stock updates.

- [ ] **Step 4: Verify GREEN/review/commit**

Confirm update semantics meet M06 acceptance criteria and do not implement M07 persistence reconciliation.

---

### Task 5: Knowledge bootstrap registration and disabled-WooCommerce safety

**Files:**
- Modify: `src/Knowledge/KnowledgeBootstrap.php`
- Test: `tests/Unit/Knowledge/KnowledgeBootstrapTest.php`

**Interfaces:**
- Consumes: `NativeWooCommerceCatalogGateway`, `WooCommerceProductSource`.
- Produces stable native registry entry `woocommerce_product` regardless of WooCommerce activation state.

- [ ] **Step 1: Write failing bootstrap tests**

Assert registry contains `woocommerce_product`; constructing/registering the source does not require WooCommerce classes/functions; existing manual/FAQ/WordPress/file sources and extension hook behavior remain unchanged.

- [ ] **Step 2: Verify RED**

Expected failure: missing registry source, while prior bootstrap tests remain green.

- [ ] **Step 3: Add minimal registration**

Register `new WooCommerceProductSource( new NativeWooCommerceCatalogGateway() )` without executing WooCommerce APIs during bootstrap.

- [ ] **Step 4: Verify GREEN and regression suite**

Run exact-head PHP tests and all permanent CI jobs.

- [ ] **Step 5: Independent review/commit**

Confirm source type collision semantics and extension hook ordering remain deterministic.

---

### Task 6: Real WordPress/WooCommerce smoke coverage

**Files:**
- Create: `scripts/test-wp-woocommerce-knowledge.php`
- Create: `scripts/test-wp-woocommerce-knowledge.sh`
- Modify: `package.json`
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Produces permanent `npm run test:wp:woocommerce-knowledge` smoke gate in `wordpress-smoke`.

- [ ] **Step 1: Add CI command/step before script implementation to create wiring RED**

Add the npm script and workflow step referencing the intentionally absent shell script. Push test-only SHA. Expected: existing permanent jobs/steps pass until the new step fails because the script is absent. Record this accurately as integration-wiring RED, not product-behavior RED.

- [ ] **Step 2: Implement isolated WooCommerce smoke environment setup**

Install/activate the repository-selected WooCommerce test version in the WordPress CI environment without making it a production Composer dependency.

- [ ] **Step 3: Create fixtures through public APIs**

Create one published simple product and one published variable product with SKU, categories, tags, attributes, descriptions and variations. Add live price/stock plus private/customer-like metadata specifically to prove they do not enter documents.

- [ ] **Step 4: Assert normalization and change behavior**

Verify simple/variable output, stable metadata, no price/stock/private data, deterministic hashes, descriptive update changes version/hash, price/stock-only update does not, unpublish removes product from source output, and cleanup succeeds.

- [ ] **Step 5: Verify WooCommerce-disabled path**

Deactivate WooCommerce and prove plugin activation/knowledge bootstrap plus non-commerce source smoke still pass.

- [ ] **Step 6: Exact-head GREEN/review/commit**

Require all permanent jobs green and inspect package artifact.

---

### Task 7: M06 integration, compatibility/security/performance review, documentation, and merge

**Files:**
- Modify: `docs/milestones/M06-woocommerce-knowledge-ingestion.md`
- Modify: `docs/progress/STATUS.md`
- Modify as findings require: `docs/progress/TEST-MATRIX.md`, `docs/progress/SECURITY.md`, `docs/progress/KNOWN-ISSUES.md`, `docs/progress/TECH-DEBT.md`, `docs/DECISIONS.md`

**Interfaces:**
- Produces verified M06 milestone completion and durable M07 handoff.

- [ ] **Step 1: Reconcile branch with latest `main`**

Recover active PR/reviews/CI first. Resolve branch divergence without overwriting unrelated work.

- [ ] **Step 2: Whole-milestone review**

Recheck optional WooCommerce activation, public eligibility, arbitrary-meta/customer/order exclusion, stable/live state separation, deterministic version/hash, product deletion/unpublish semantics, bounded paging/N+1 behavior, dependency/version policy, error redaction, and package inclusion.

- [ ] **Step 3: Route every Critical/Important behavior finding through TDD**

Add focused regression RED on exact predecessor SHA, implement minimum fix, verify focused/full GREEN, and re-review until 0 unresolved Critical/Important findings remain.

- [ ] **Step 4: Final verification**

Require `php-quality`, `js-quality`, `package`, and `wordpress-smoke` green on the exact final SHA, including WooCommerce enabled and disabled smoke. Record artifact ID/size/digest.

- [ ] **Step 5: Durable closeout**

Update milestone/status/test/security/known-issues/tech-debt/decision docs only with evidence that actually exists. Mark M06 complete only after all acceptance criteria and review gates pass.

- [ ] **Step 6: Finish branch and PR**

Use `superpowers:requesting-code-review`, `superpowers:verification-before-completion`, and `superpowers:finishing-a-development-branch`. Ensure no unresolved review threads, PR mergeable, and exact tested head unchanged.

- [ ] **Step 7: Merge and post-merge verify**

Merge only the exact tested SHA. Verify a fresh `main` CI run passes all permanent jobs before recording M06 integrated and handing off to M07.

## Plan self-review

- Spec coverage: every design requirement maps to Tasks 1–7; real optional-WooCommerce integration is covered in Task 6.
- Placeholder scan: no TODO/TBD/"implement later" placeholders.
- TDD sequencing: production work begins only after test-only RED; Task 6 explicitly distinguishes wiring RED from behavior RED.
- Type consistency: gateway/product/source names and signatures are consistent across tasks.
- Scope: live commerce actions remain M18; incremental index reconciliation remains M07; background jobs remain M09.
- Security/privacy: allowlisted public catalog data only; customer/order/arbitrary metadata excluded.
- Performance: deterministic bounded ID paging is specified and reviewed.
- Merge gate: exact-head permanent CI and fresh post-merge `main` CI are mandatory.
