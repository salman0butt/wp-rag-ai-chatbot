# Global Status

- Completed milestones on `main`: **M00-M12**.
- Latest completed milestone: **M12 — Admin Onboarding, Bot Management & Provider Configuration**.
- M12 PR: **#17 — MERGED** at merge SHA `206dbfcef42cfcee2a998d7f3c386abdb97425a0`.
- Current milestone: **M13 — Knowledge Manager, Indexing UI, Playground & RAG Debugger**.
- Active M13 integration PR: **#18 — OPEN, DRAFT**.

## M13 current progress

### Task 1 — knowledge source inventory: COMPLETE

- Protected bounded `GET /admin/knowledge/sources` over the existing source repository.
- Explicit allow-list excludes persisted source `config` and `sourceHash`.
- Genuine RED `d60f761b52ab29ae1363cf248435984bcfdb3376`, CI `34248330696`.
- Verified implementation `813e14817ec067180b55ac19e09d27995baf348e`, CI `34248981984` GREEN.
- Review `5144187297`: 0 Critical / 0 Important.
- Evidence: `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md`.

### Task 2 — source detail plus bounded document/chunk inspection: COMPLETE

- Protected source detail, paginated document summary, and paginated persisted chunk inspection.
- Child pages capped at 100; chunk text capped at 2,000 bytes with a truncation indicator and UTF-8-safe boundary handling.
- Explicit DTOs exclude source config/hash, raw document content/metadata/hash, and chunk metadata/content hash.
- Source/document ownership is verified before chunk inspection.
- Genuine initial RED `f7c43911db163bc07d52e0584847d0290b0da6fc`, CI `34259395295`.
- Final UTF-8 closeout RED `ccbb70bba6132e04e62781561a1a21e2a97035f7`, CI `34262896019`: PHPStan clean; 677 tests / 2,838 assertions with exactly one expected failure.
- Final implementation `4a74f587648db3bb36c417fc691596278ef80c44`, CI `34263141368` GREEN across `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.
- Final review `5145555022`: 1 Important UTF-8 truncation issue found and resolved; 0 Critical / 0 Important unresolved.
- Evidence: `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md`.

### Task 3 — recoverable job status and safe lifecycle controls: COMPLETE

- Protected bounded job inventory plus enqueue/cancel/retry endpoints over the existing M09 queue/repository/state-transition seams.
- Job pages are capped at 100 and expose only allow-listed operational fields; payload, idempotency and lease internals are excluded.
- Unsupported terminal cancellation and non-failed retry return stable `invalid_transition` before any mutation/enqueue call.
- Persisted error code/message fields reuse the M09 sanitized diagnostic contract rather than raw exception/provider payloads.
- Genuine RED `c7e122f641c7734905711416a9bc318f8cc78fb0`, CI `34267619024`: static analysis clean; PHPUnit 687 tests / 2,897 assertions with exactly two expected missing-route errors; JS/package/WordPress smoke green.
- Final implementation/harness head `645f58fa08a1c3dff3d31128a700071a94c2c97a`, CI `34272705657`: all four permanent jobs GREEN; PHPStan 288/288 clean; PHPUnit 687/687 tests / 2,897 assertions; Composer audit clean.
- Review `5146492264`: 0 Critical / 0 Important.
- Evidence: `docs/progress/M13-TASK3-JOB-LIFECYCLE.md`.

### Task 4 — Knowledge manager admin UI: ACTIVE

Completed bounded slice — server-supplied knowledge-source rendering:

- `KnowledgeManagementScreen` renders the bounded source page, deterministic selected context, labelled pagination, and an explicit empty state.
- Source identifiers are URI-encoded in hash navigation; displayed summary fields are limited to the existing allow-listed source DTO.
- Initial test checkpoint `f33f0f26d4ce0d237de0b55edc106b612c548ca0` / CI `34274206561` is **not RED** because Prettier stopped before Jest.
- Genuine RED `d6e3d6c44bc3a1ea72b65b59ab6482449ccae76b` / CI `34274361994`: lint and typecheck passed; Jest ran 46 tests with 45 passed / exactly 1 expected missing-screen failure.
- GREEN implementation `dc64aaa502d94504b8619bcf55aa2d600d655b53` / CI `34274863066`: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all GREEN.
- Scoped review `5146690487`: 0 Critical / 0 Important.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-SOURCE-RENDERING.md`.

Completed bounded slice — Knowledge router/bootstrap and server-authoritative source loading:

- Added Knowledge to the established admin navigation/hash router and reused the nonce-authenticated same-origin admin client.
- Loads only `GET /admin/knowledge/sources?page=N&per_page=20`; selected source/page state is derived defensively from the hash.
- Leaving Knowledge discards the bounded in-memory source page, so returning to the same page refetches server-authoritatively rather than reusing stale data.
- Initial checkpoint `fe12b8a5c8dbe23d1d7d541607ca5905a7c21ca5` / CI `34277143129` is **not RED** because Prettier stopped before Jest.
- Genuine bootstrap RED `2e2471f9a952221406768caf1b4240c5db40b02b` / CI `34282644712`: lint/typecheck passed; Jest 47 tests with 46 passed / exactly 1 missing-source-request failure.
- Verification caught and reverted an over-broad test-file edit before closeout; no production behavior depended on it.
- Genuine server-authoritative re-entry RED `5edf9034d0bab82ea701245768834cf55dd5498b` / CI `34283561496`: lint/typecheck passed; Jest 48 tests with 47 passed / exactly 1 stale re-entry failure.
- GREEN implementation `64c57e7bdcbb07282cf91838c8b8ddf987ecc4fd` / CI `34284020063`: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all GREEN.
- Scoped review `5147497514`: 0 Critical / 0 Important.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-BOOTSTRAP.md`.

Completed bounded slice — selected-source detail/document loading and persisted-ID compatibility:

- Selected persisted source routes reuse the existing Task 2 allow-listed source-detail and bounded document-page endpoints through the existing nonce-authenticated client.
- Detail/document state is discarded when source selection changes or Knowledge is left, preventing stale cross-source rendering.
- Server DTO source IDs may be numeric while hash-route IDs are strings; UI matching now normalizes only this comparison boundary.
- `6302f3786946b05be27d4fbb15979c694a82b947` / CI `34286857568` is **not RED** because Prettier stopped before Jest.
- Genuine persisted-ID RED `7c01f3fb77b73c4bea47b27e410114fa239b854c` / CI `34292237492`: lint and typecheck passed; Jest ran 50 tests with 49 passed / exactly 1 failure, selecting `Support Articles` instead of numeric source `17` / `Product Catalog`.
- GREEN `65ff1f83f1a232c2bedf510cce8117fceb4e2c0f` / CI `34292560442`: `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke` all GREEN.
- Scoped review `5148247521`: 0 Critical / 0 Important.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-DETAIL-UI.md`.

Completed bounded slice — selected-document persisted chunk inspection and accessible document navigation:

- Selected document routes reuse the existing Task 2 bounded chunk endpoint through the nonce-authenticated admin client with `per_page=20`.
- Chunk state remains correlated to the selected persisted source/document and is discarded when Knowledge, source, page, or document selection changes.
- Document titles are keyboard-focusable hash links that preserve source-page context and mark the selected document with `aria-current="true"`.
- Genuine chunk-loading RED `99c4c892b680c6deefb303080f392f8eb51fd349` / CI `34301081848`: lint/typecheck passed; Jest 51 tests with 50 passed / exactly 1 missing bounded-chunk-request failure.
- Chunk-loading GREEN `1b148d4828841c1d19214d932f49eb9d7697eb54` / CI `34308792575`: all four permanent jobs GREEN.
- Genuine document-navigation RED `2764967d48bcf707771f1d91d82822ce0fe4468a` / CI `34309042300`: lint/typecheck passed; Jest 52 tests with 51 passed / exactly 1 missing-anchor failure.
- `a01d9390b2311895f957a0b9c09be1f0fec5e455` / CI `34309325755` is **not GREEN** because Prettier stopped before Jest.
- Formatting-only corrected GREEN `1c824f50b2dc1a7ddc82611bf55aca8f1bd45ad4` / CI `34309555272`: lint/typecheck passed; Jest 19/19 suites and 52/52 tests GREEN; build and JavaScript live/package gates GREEN.
- Scoped coordinator review: 0 Critical / 0 Important; independent subagent transport remained unavailable, so final Task 4 independent review remains outstanding.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-CHUNKS.md`.

## Current work

**Task 4 — Knowledge manager admin UI** remains the authoritative unfinished unit.

Exact continuation:

- start with a fresh genuine Jest RED for integrating the existing Task 3 bounded job inventory into the Knowledge screen;
- add only the existing enqueue/cancel/retry lifecycle actions through the nonce-authenticated admin client and surface stable `invalid_transition` errors without raw exception/provider payloads;
- do not cache secret-bearing, raw document, raw provider, job payload, lease/idempotency, or unbounded data in browser state;
- add explicit loading/empty/error behavior plus constrained-width, long-content, keyboard/accessibility, and responsive CSS coverage;
- complete final Task 4 correctness/security/accessibility/performance and independent review and exact-final-SHA CI before advancing to Task 5.

## Durable recovery

- `docs/milestones/M13-knowledge-manager-playground-debugger.md` — M13 milestone ledger.
- `docs/superpowers/specs/2026-09-08-m13-knowledge-manager-debugger-design.md` — auto-approved design.
- `docs/superpowers/plans/2026-09-08-m13-knowledge-manager-debugger.md` — auto-approved implementation plan.
- `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md` — Task 1 evidence.
- `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md` — Task 2 evidence.
- `docs/progress/M13-TASK3-JOB-LIFECYCLE.md` — Task 3 evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-SOURCE-RENDERING.md` — Task 4 source-rendering slice evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-BOOTSTRAP.md` — Task 4 router/bootstrap/source-loading evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-DETAIL-UI.md` — Task 4 selected-source detail/document and persisted-ID compatibility evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-CHUNKS.md` — Task 4 selected-document chunk inspection and accessible navigation evidence.
- PR #18 — milestone-wide draft integration record.
