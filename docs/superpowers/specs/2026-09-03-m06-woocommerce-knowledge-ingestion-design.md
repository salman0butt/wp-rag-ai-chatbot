# M06 WooCommerce Knowledge Ingestion Design

Status: AUTO-APPROVED — SCHEDULED MODE

## Goal

Normalize stable, descriptive WooCommerce catalog knowledge into deterministic canonical `DocumentRecord` instances while keeping WooCommerce optional and explicitly excluding live transactional state from indexed knowledge.

## Context and constraints

M04 established `KnowledgeSource`, `KnowledgeSourceRegistry`, WordPress source gateways, `DocumentRecord`, deterministic hashing, and fail-closed source composition. M05 extended source composition without making optional parser/runtime services leak into domain contracts. M06 follows the same pattern for WooCommerce.

Repository architecture requires descriptive/stable product facts to be indexable while current price, sale state, stock, variation availability, cart, authenticated order state, discounts, and similar transactional values are fetched from WooCommerce at execution time instead of trusted from embeddings. WooCommerce must remain optional and its absence must never break plugin activation or non-commerce knowledge sources.

## Approaches considered

### 1. WooCommerce gateway + immutable product snapshot + knowledge source — selected

Introduce a small WooCommerce gateway boundary that detects availability and returns normalized immutable product snapshots. A `WooCommerceProductSource` maps those snapshots into canonical documents. Native WooCommerce API/classes remain behind the gateway.

Pros: optional-plugin safety is centralized; domain tests do not require WooCommerce classes; stable-vs-live policy is reviewable in one place; mapping follows the existing WordPress gateway/source architecture; later live commerce tools can use separate services without contaminating indexed documents.

Cons: adds a gateway/snapshot layer, but each object has one concrete responsibility and a clear test seam.

### 2. Call `wc_get_products()` directly from the knowledge source

Rejected because optional-plugin checks, batching, metadata allowlisting, visibility policy, and WooCommerce object APIs would become coupled to canonical document mapping and harder to test without WooCommerce loaded.

### 3. Reuse generic WordPress post ingestion for `product` posts

Rejected because generic post ingestion cannot safely express WooCommerce SKU, product taxonomy, attributes, variation structure, product visibility rules, or the mandatory separation between stable catalog facts and live commerce values.

## Selected architecture

### `WooCommerceCatalogGateway`

Create `src/WooCommerce/Catalog/WooCommerceCatalogGateway.php` as the application-facing contract.

Responsibilities:

- `isAvailable(): bool` reports whether supported WooCommerce public APIs are available.
- `product(int $productId): ?WooCommerceProduct` returns one eligible normalized product snapshot or `null` when no eligible catalog product exists.
- `productIds(int $page, int $perPage): array` returns deterministic ascending published candidate product IDs in bounded pages; final public eligibility is enforced by `product()` so filtering cannot falsify page-exhaustion semantics.

The gateway never returns raw `WC_Product` objects to the Knowledge domain.

### `NativeWooCommerceCatalogGateway`

Create `src/WooCommerce/Catalog/NativeWooCommerceCatalogGateway.php` as the only native WooCommerce adapter in M06.

Availability is detected without hard class references during plugin bootstrap. When WooCommerce public functions/classes are unavailable, `isAvailable()` is false, enumeration returns an empty array, and product lookup returns `null`. Non-commerce plugin behavior remains healthy.

When available, the adapter uses supported public WooCommerce APIs to load products and translates them into immutable snapshots. Enumeration is paginated/bounded and ordered by product ID to keep ingestion deterministic. The adapter must avoid per-product SQL and must not call private/internal WooCommerce data-store APIs.

### `WooCommerceProduct`

Create an immutable `src/WooCommerce/Catalog/WooCommerceProduct.php` snapshot containing only stable, index-approved catalog facts plus a generic WooCommerce modification marker retained for adapter/reconciliation observability:

- product ID;
- product type (`simple`, `variable`, or another explicitly supported descriptive type);
- published catalog status/visibility state;
- name;
- short description;
- full description;
- SKU;
- canonical product URL;
- category names/slugs;
- tag names/slugs;
- global/custom descriptive attributes;
- variation descriptors for variable products;
- generic WooCommerce modified timestamp.

The generic modified timestamp is not by itself a stable-knowledge version input because WooCommerce can update that marker for changes whose cause is outside the indexed allowlist. M06 source versioning therefore derives from the canonical stable facts themselves, not from timestamp churn.

The snapshot excludes price, regular/sale price, stock quantity/status, backorder state, purchasability, sale dates, coupons/discounts, cart state, customer IDs, order data, downloads/customer entitlements, billing/shipping data, sessions, and arbitrary product/post meta.

### Stable versus live data policy

Indexed content may include product name, descriptions, SKU, canonical URL, categories, tags, descriptive attributes, and stable variation descriptors such as variation ID/SKU and attribute choices.

Indexed content must not include current price, sale state, stock/availability, cart/order state, active discounts, customer-specific values, or other values whose correctness depends on transaction time or identity. Those belong to later authorized WooCommerce services/actions.

Variation descriptors therefore describe what variation identities/options exist, but do not claim whether a variation is currently in stock, purchasable, discounted, or priced at any particular amount.

### Visibility and status policy

M06 indexes only products that are publicly queryable catalog knowledge:

- product status must be `publish`;
- password-protected/non-public products are excluded;
- catalog visibility that intentionally hides a product from both catalog/search is excluded by default;
- private/draft/pending/trash products are excluded;
- customer/user-specific access rules are not bypassed;
- arbitrary metadata is never copied as a fallback.

The gateway owns WooCommerce eligibility because it has access to product visibility semantics. The source still fails closed if a snapshot violates required identity/content invariants.

### Canonical document mapping

`WooCommerceProductSource` implements `KnowledgeSource` with stable type `woocommerce_product`.

Persisted source configuration supports bounded selection by product IDs and/or whole-catalog enumeration. Exact configuration shape is validated by the source; invalid IDs, unsupported values, and malformed configuration raise `KnowledgeSourceException` rather than silently broadening scope.

Each eligible product emits one `DocumentRecord` in M06.

- `documentKey`: `woocommerce_product:{productId}`.
- `externalId`: decimal product ID string.
- `documentType`: `woocommerce_product`.
- `title`: product name.
- `canonicalUrl`: product permalink.
- `visibility`: `public`.
- `content`: deterministic readable catalog text assembled from name, descriptions, SKU, taxonomies, attributes, and variation descriptors.
- `metadata`: allowlisted structured catalog facts only, including product ID/type/SKU/category/tag/attribute/variation identity data.
- `sourceVersion`: deterministic SHA-256 over canonical stable allowlisted catalog facts. The generic WooCommerce modified marker is deliberately excluded so price/stock-only or other non-allowlisted updates cannot create false knowledge-version changes.
- `contentHash`: existing `DocumentHasher` over canonical document fields.

A descriptive catalog update changes source version/content hash as appropriate. A price-only or stock-only change, including any accompanying generic modified-marker churn, must not change the M06 source version or document hash.

Product deletion/unpublishing causes subsequent enumeration/lookup to omit the product, allowing later incremental-index reconciliation to delete stale documents in M07. M06 does not implement the index deletion engine itself.

### Source registration

`KnowledgeBootstrap` registers `WooCommerceProductSource` unconditionally using the optional-safe gateway. Registration itself must not require WooCommerce to be active. With WooCommerce disabled, the source remains resolvable but yields no documents rather than throwing fatal errors.

This keeps source type stability across environments and preserves the existing third-party `wp_rag_ai_chatbot_knowledge_sources` extension hook.

## Security and privacy

- Treat WooCommerce catalog/user metadata as untrusted application data, not authorization policy or system instructions.
- Use a strict allowlist; never ingest arbitrary post meta, order meta, session data, customer data, billing/shipping fields, private notes, download permissions, API credentials, webhook secrets, or extension-defined metadata by default.
- Public visibility checks fail closed.
- Product descriptions may contain HTML/shortcodes; normalize through existing safe WordPress text/content patterns and do not execute arbitrary code while building documents.
- Error messages must not expose secrets, SQL, filesystem paths, customer identifiers, or raw private metadata.

## Performance

Whole-catalog enumeration is bounded and paginated. Default source page size is 100 products with an upper bound of 250. The native gateway requests IDs deterministically and hydrates only the current page. Category/tag/attribute/variation normalization should use WooCommerce object APIs without issuing avoidable repeated queries for the same product.

M06 provides synchronous bounded source enumeration primitives only. Long-running/background indexing and resilient job orchestration remain M09; M07 owns incremental indexing/reconciliation.

## Compatibility

WooCommerce remains optional. M06 should use public APIs available across the repository's selected supported WooCommerce range and avoid internal data-store classes. The concrete supported version matrix is verified by real integration CI in this milestone and finalized again in M24.

No new mandatory runtime service or paid API is introduced.

## Testing

Unit tests use a fake `WooCommerceCatalogGateway` and immutable snapshots to verify source mapping without WooCommerce loaded. Gateway-facing tests use Brain Monkey or small seams around native functions where appropriate.

Required behavior coverage includes:

- disabled WooCommerce is non-fatal and yields no product documents;
- deterministic product enumeration and configured ID selection;
- simple-product normalization;
- variable-product normalization with stable variation identity/options;
- exact SKU/category/tag/attribute metadata preservation;
- exclusion of price, stock, discounts, cart/order/customer/private metadata;
- publish/visibility filtering;
- deterministic source version/content hash;
- descriptive changes alter version/hash;
- price/stock-only changes and generic modified-marker-only churn do not alter version/hash;
- removed/unpublished products disappear from source output;
- malformed source configuration fails closed.

Real WordPress/WooCommerce smoke coverage installs/activates the supported WooCommerce test dependency, creates simple and variable fixtures, verifies normalized documents, verifies exclusion of live/private values, verifies update/unpublish behavior, and proves plugin activation/knowledge bootstrap still works with WooCommerce disabled.

## Milestone boundaries

Out of scope: embeddings/vector runtime (M08), chunking/dedup/incremental index reconciliation implementation (M07), background queue execution (M09), knowledge-manager UI (M13), live price/stock/cart/order/tool actions (M18), authenticated order workflows, checkout UI, coupons, and final compatibility matrix audit (M24).

## Self-review

- Placeholder scan: no TODO/TBD placeholders.
- Architecture: WooCommerce APIs are isolated behind a gateway; canonical mapping reuses M04 contracts.
- Security/privacy: arbitrary/customer/private metadata is excluded by allowlist and visibility fails closed.
- Stable/live separation: price, stock, sale, cart, order, discount, identity-dependent state, and generic modified-marker-only churn are explicitly outside indexed documents and source versioning.
- Compatibility: inactive WooCommerce is a supported state and cannot break bootstrap.
- Performance: bounded deterministic paging avoids unbounded catalog loads; background orchestration remains later scope.
- Testability: source behavior is testable without WooCommerce; native behavior receives real WooCommerce smoke coverage.
- YAGNI: one document per product; variations remain structured product metadata until retrieval evidence proves separate documents are needed.

## Task 4 clarification — auto-approved scheduled mode

Task 4 exposed an inconsistency in the original wording: a generic WooCommerce modified marker cannot both participate in `sourceVersion` and guarantee that price/stock-only updates never change that version, because the adapter receives no cause-specific modification signal. The stronger product requirement is the stable/live separation. Therefore this clarification supersedes the earlier modified-marker-as-version-input wording: the marker may remain in the immutable gateway snapshot for observability, but M06 `sourceVersion` and `contentHash` are functions only of canonical allowlisted stable knowledge facts. This clarification was routed through regression TDD rather than treated as a documentation-only assumption.
