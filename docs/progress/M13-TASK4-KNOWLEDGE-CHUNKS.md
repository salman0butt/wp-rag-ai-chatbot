# M13 Task 4 — Selected-document chunk inspection UI

Status: **COMPLETE bounded Task 4 slice**. Task 4 itself remains **ACTIVE**.

## Scope

This slice connects the Knowledge manager UI to the existing Task 2 bounded persisted-chunk inspection contract and makes document selection keyboard-navigable.

Implemented behavior:

- Parses the selected document key from `#/knowledge/{source}/documents/{document}`.
- Reuses the existing nonce-authenticated same-origin admin client.
- Loads only `GET /admin/knowledge/sources/{source}/documents/{document}/chunks?page=1&per_page=20`.
- URI-encodes source IDs and document keys before constructing REST or hash-route paths.
- Keeps chunk state correlated to the selected persisted source and document, and clears it when Knowledge, source, page, or document selection changes.
- Renders only the existing bounded Task 2 chunk DTO fields used by the UI; no raw document body, source config/hash, provider payload, or unbounded browser cache was introduced.
- Renders document titles as keyboard-focusable links preserving the current source page and marks the selected document link with `aria-current="true"`.

## Strict TDD evidence

### Chunk loading RED

Clean behavioral RED:

- Commit: `99c4c892b680c6deefb303080f392f8eb51fd349`
- CI: `34301081848`
- JavaScript lint and TypeScript passed.
- Jest executed 51 tests: 50 passed / exactly 1 failed.
- The single failure proved the selected document route made only the existing four requests and never requested `/admin/knowledge/sources/17/documents/doc-support/chunks?page=1&per_page=20`.

### Chunk loading GREEN

- Commit: `1b148d4828841c1d19214d932f49eb9d7697eb54`
- CI: `34308792575`
- `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all GREEN.

### Accessible document-navigation RED

- Commit: `2764967d48bcf707771f1d91d82822ce0fe4468a`
- CI: `34309042300`
- JavaScript lint and TypeScript passed.
- Jest executed 52 tests: 51 passed / exactly 1 failed.
- The single failure expected `#/knowledge/17/documents/doc-support?page=2` but found no document anchor.

### Accessible document-navigation GREEN

`a01d9390b2311895f957a0b9c09be1f0fec5e455` / CI `34309325755` is **not GREEN** because Prettier stopped verification before Jest.

The formatting-only correction is:

- Commit: `1c824f50b2dc1a7ddc82611bf55aca8f1bd45ad4`
- CI: `34309555272`
- JavaScript lint and TypeScript passed.
- Jest: 19/19 suites and 52/52 tests GREEN.
- Build plus Pinecone, Chroma, provider, Qdrant, and package-assertion gates GREEN.

## Scoped review

Correctness:

- Source and document selection are derived from the hash rather than duplicated client-side catalogs.
- Chunk rendering is suppressed unless loaded source/document correlation matches the current route.
- Selecting another source/document clears stale chunk state before refetching.

Security:

- No new endpoint or authorization boundary was added.
- Existing nonce-authenticated same-origin requests are reused.
- Source/document identifiers are encoded before use in request/hash paths.
- No source config/hash, raw document content/metadata/hash, chunk metadata/content hash, provider payload, or secret-bearing state is exposed.

Performance:

- The UI loads one server-bounded chunk page of 20 rows for the selected document.
- Rendering remains linear in bounded source/document/chunk page sizes.
- No unbounded browser cache was added.

Accessibility:

- Document titles are real anchors and therefore keyboard-focusable.
- The selected document exposes `aria-current="true"`.
- The source-page query parameter is preserved in document links so navigation returns to the same bounded source context.

Scoped coordinator review found **0 Critical / 0 Important** issues. Independent subagent execution transport was unavailable in this runtime, so no unavailable independent-review result is claimed; Task 4 still requires its final independent closeout review before completion.

## Exact continuation

Task 4 remains ACTIVE. Next, under a fresh genuine Jest RED:

1. Integrate the existing Task 3 bounded job inventory into the Knowledge screen.
2. Add only the existing enqueue/cancel/retry lifecycle actions through the nonce-authenticated admin client.
3. Surface stable `invalid_transition` lifecycle errors without raw exception/provider payloads.
4. Add explicit loading/empty/error states and constrained-width/long-content responsive coverage.
5. Complete final Task 4 correctness/security/accessibility/performance plus independent review and exact-final-SHA CI before advancing to Task 5.
