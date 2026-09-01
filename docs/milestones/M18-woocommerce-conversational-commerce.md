# M18 — WooCommerce Conversational Commerce & Safe Store Actions

Status: NOT STARTED

## Goal
Deliver grounded product discovery and safe live WooCommerce interactions.

## Dependencies
M06 catalog knowledge, M10-M11 retrieval/chat, M14 widget.

## In Scope
Semantic/exact product search; price/category/attribute/stock filters using live data where required; product cards/comparison/related suggestions; variation display; add/inspect/update cart as approved; authenticated order lookup.

## Out of Scope
LLM-invented prices/stock/orders/discounts; unrestricted checkout/payment changes.

## Architecture
RAG supplies descriptive discovery; authorized WooCommerce services supply live transactional state/actions at execution time.

## Acceptance Criteria
Displayed transactional facts match WooCommerce at action time; customer can access only own authorized order data; variation/cart operations validate WooCommerce rules; stale indexed state cannot override live values.

## Tasks
Pending plan.

## TDD Evidence
Required.

## Integration Test Evidence
WooCommerce product/cart/order fixtures required.

## E2E / Visual Verification
Product cards, variants, cart actions, mobile/error/out-of-stock/auth scenarios.

## Security Review
Order ownership, cart/session integrity, price tampering, unauthorized coupon/discount/order access.

## Accessibility Review where UI exists
Required for commerce cards/actions.

## Performance Review where relevant
Catalog filtering/query efficiency and N+1 avoidance.

## Code Review Findings
Pending.

## Fixes
Pending.

## Fresh Verification Commands
Pending.

## Fresh Verification Results
Pending.

## Commits
Pending.

## Files Changed
Pending.

## Known Limitations
Pending.

## Documentation Updated
Pending.

## Completion Checklist
All mandatory gates.

## Next Milestone
M19 — Actions/Abilities/MCP.
