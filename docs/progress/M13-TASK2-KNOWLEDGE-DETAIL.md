# M13 Task 2 — Source Detail and Bounded Document/Chunk Inspection

Status: COMPLETE

## Scope

Task 2 adds administrator-only source detail plus bounded document and persisted chunk inspection over the existing M04-M07 repositories/projections. It does not introduce a parallel knowledge store, provider call, client-side indexing engine, or unbounded child embedding.

## Delivered behavior

- `GET /admin/knowledge/sources/{id}` returns an explicit safe source DTO.
- `GET /admin/knowledge/sources/{id}/documents` returns a bounded paginated document summary.
- `GET /admin/knowledge/sources/{id}/documents/{document_key}/chunks` returns bounded persisted chunk inspection.
- All routes reuse `AdminCapability::can_manage`.
- Child `per_page` is capped at 100.
- Chunk content is capped at 2,000 bytes and reports `content_truncated`.
- Chunk reads verify that the requested document belongs to the requested source.
- Source config/source hash, raw document content/metadata/hash, and chunk metadata/content hash are excluded from the allow-listed DTOs.
- Persisted chunk pages reuse the lexical store and deterministic sequence/chunk-key ordering.

## Strict TDD evidence

### Initial Task 2 RED

- `bf24c1bc8aac96b491ded8e6e7b5c430ab12c6d9` stopped in PHPCS before PHPUnit and is **not** behavioral RED.
- Genuine RED: `f7c43911db163bc07d52e0584847d0290b0da6fc`, CI `34259395295`.
- PHPStan completed with no errors.
- PHPUnit ran 676 tests / 2,821 assertions and failed only on the intended missing Task 2 behavior: two route-registration expectations and one missing `ChunkInspectionStore` implementation contract.

### Minimum implementation

Relevant implementation commits include:

- `0431bac0462b36386ad7aedc77fe079b32799604` — bounded persisted chunk inspection.
- `6960f08ce9db32f6df024416c490ef9e60ffb948` — protected knowledge detail routes/resources.
- `61118f8ce15b7ae46b9914663bc18b45e64585ef` — route wiring refinement.
- `d5ef3e2787746c813c2fe502f143a738ea0801c2` — stable detail error-contract correction.

### Verification-harness correction

A later branch head `753fa1d2888c280b33bc20ddeb0904c78ad3b90c`, CI `34261530092`, failed only because three older unit tests still asserted pre-M13 global REST route counts. The uploaded PHP diagnostic proved PHPStan and production route registration were correct; the stale tests expected 5/6/5 generic routes where the three new Task 2 routes made the correct counts 8/9/8. This was **not** behavioral RED.

The harness was corrected without changing production behavior:

- `135e3a05655a9097980c7e11f0a2fcd184f45674` — update admin route counts.
- `e46a7520854a6876b231e1b98b4aed80a259150a` — update readiness route count.
- CI `34262233548` verified the corrected PHP suite.
- `cb522cacc2c3ef23573b3764bdee6a42203e43dc` removed the temporary PHP diagnostic workflow after root-cause confirmation.

## Closeout review correction — UTF-8 boundary

Final review found one Important correctness issue: raw byte `substr()` truncation could split a multi-byte UTF-8 code point and make chunk text invalid for REST JSON serialization.

- `29edbb37c6e4614163afacf74d62819dbf06904e`, CI `34262731359`, stopped at PHPCS and is **not** RED.
- Genuine UTF-8 RED: `ccbb70bba6132e04e62781561a1a21e2a97035f7`, CI `34262896019`.
- PHPStan was clean; PHPUnit ran 677 tests / 2,838 assertions with exactly one failure: `test_chunk_truncation_preserves_valid_utf8`.
- GREEN fix: `4a74f587648db3bb36c417fc691596278ef80c44` trims only incomplete trailing UTF-8 bytes after the existing 2,000-byte cap.
- Exact implementation CI `34263141368`: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all GREEN.

## Security review

- Every detail route remains capability protected.
- Explicit DTO allow-lists prevent source config/hash, raw document content/metadata/hash, and chunk metadata/content hash from crossing the admin REST boundary.
- Source/document ownership is verified before chunk access.
- Stable error responses do not serialize provider/internal exception payloads.

## Performance review

- Child resources are paginated and capped at 100 records per page.
- Chunk text remains byte-bounded at 2,000 bytes while preserving valid UTF-8.
- Persisted chunk inspection uses bounded count/page queries and deterministic ordering; no provider/network work is added.

## Review

Final Task 2 scoped correctness/security/performance review: `5145555022`.

- Critical unresolved: 0.
- Important found: 1 UTF-8 truncation issue.
- Important unresolved after fix: 0.
- Accessibility: N/A for this server-only slice.
- Separate Superpowers subagent execution is unavailable in this runtime; no independent-subagent execution is claimed.

## Exact continuation

Task 3 — recoverable job status and safe lifecycle controls. Start with strict RED tests proving unsupported M09 job transitions do not mutate persisted state, then expose only M09-supported enqueue/cancel/retry transitions with stable `invalid_transition` responses and safe bounded error fields.