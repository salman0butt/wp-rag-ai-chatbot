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

### Task 4 — Knowledge manager admin UI: COMPLETE

Completed bounded slice — server-supplied knowledge-source rendering:

- `KnowledgeManagementScreen` renders the bounded source page, deterministic selected context, labelled pagination, and an explicit empty state.
- Source identifiers are URI-encoded in hash navigation; displayed summary fields are limited to the existing allow-listed source DTO.
- Genuine RED `d6e3d6c44bc3a1ea72b65b59ab6482449ccae76b` / CI `34274361994`: lint/typecheck passed; Jest 46 tests with 45 passed / exactly 1 missing-screen failure.
- GREEN `dc64aaa502d94504b8619bcf55aa2d600d655b53` / CI `34274863066`: all four permanent jobs GREEN.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-SOURCE-RENDERING.md`.

Completed bounded slice — Knowledge router/bootstrap and server-authoritative source loading:

- Knowledge uses the established admin hash router and nonce-authenticated same-origin admin client.
- Loads only bounded `GET /admin/knowledge/sources?page=N&per_page=20`; leaving Knowledge discards in-memory source state so re-entry refetches.
- Genuine bootstrap RED `2e2471f9a952221406768caf1b4240c5db40b02b` / CI `34282644712`; genuine re-entry RED `5edf9034d0bab82ea701245768834cf55dd5498b` / CI `34283561496`.
- GREEN `64c57e7bdcbb07282cf91838c8b8ddf987ecc4fd` / CI `34284020063`: all four permanent jobs GREEN.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-BOOTSTRAP.md`.

Completed bounded slice — selected-source detail/document loading and persisted-ID compatibility:

- Existing Task 2 allow-listed source-detail/document endpoints are reused through the nonce-authenticated client.
- State is discarded on source/Knowledge changes; numeric persisted IDs are normalized only at the string-valued hash comparison boundary.
- Genuine RED `7c01f3fb77b73c4bea47b27e410114fa239b854c` / CI `34292237492`: 50 Jest tests with exactly one persisted-ID selection failure.
- GREEN `65ff1f83f1a232c2bedf510cce8117fceb4e2c0f` / CI `34292560442`: all four permanent jobs GREEN.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-DETAIL-UI.md`.

Completed bounded slice — selected-document persisted chunk inspection and accessible document navigation:

- Selected documents reuse the bounded Task 2 chunk endpoint with `per_page=20`.
- Document titles are native keyboard-focusable hash links preserving source-page context and selected state via `aria-current="true"`.
- Genuine chunk RED `99c4c892b680c6deefb303080f392f8eb51fd349` / CI `34301081848`; chunk GREEN `1b148d4828841c1d19214d932f49eb9d7697eb54` / CI `34308792575`.
- Genuine navigation RED `2764967d48bcf707771f1d91d82822ce0fe4468a` / CI `34309042300`; corrected GREEN `1c824f50b2dc1a7ddc82611bf55aca8f1bd45ad4` / CI `34309555272` with 52/52 Jest tests GREEN.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-CHUNKS.md`.

Completed bounded slice — Task 3 job inventory integration:

- Knowledge loads bounded `GET /admin/knowledge/jobs?page=1&per_page=20`, clears it when leaving, and renders only the existing Task 3 allow-listed DTO including sanitized persisted diagnostic fields.
- Genuine RED `b89241f9c8f9a2735e2b42a1e3cfc18770988b6b` / CI `34312568823`: 53 Jest tests with exactly one missing-job-request failure.
- GREEN `ec9089ddd9cd7e503a848007effa68015af521e2` / CI `34317251523`: all four permanent jobs GREEN, 53/53 Jest tests GREEN.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-JOBS.md`.

Completed bounded slice — cancel/retry lifecycle UI:

- Queued/running jobs expose native Cancel controls; failed jobs expose native Retry controls; unsupported states expose neither.
- Mutations URI-encode job keys, reuse Task 3 endpoints, then refetch bounded authoritative job inventory.
- Genuine RED `8ebfd5379e93de778f16ac78800c05b641e91d8c`: 55 Jest tests with exactly two missing-control failures.
- GREEN `9eb9a5344c68a8b84b2fd43d7a7fddc2aefade63` / runner `34321869920`: lint/typecheck, 55/55 Jest tests, build and live-gating GREEN.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-JOB-ACTIONS.md`.

Completed bounded slice — enqueue lifecycle UI:

- Knowledge exposes a labelled native enqueue form for only the Task 3 identifier payload: `document_key`, numeric `source_id`, `collection_id`, `configuration_id`, and `generation`.
- Enqueue reuses `POST /admin/knowledge/jobs` then refetches the bounded job page; no optimistic queue state is retained.
- Genuine RED `99ff3bea50c89df4ec135852932c0529894b3fe4` / probe `34326208932`; GREEN `a0f185bd3b17f62833d6e799e16bafacbfd32f54`; clean exact-head CI `34326516720` GREEN.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-JOB-ENQUEUE.md`.

Completed bounded slice — safe lifecycle mutation errors:

- Knowledge maps only stable `invalid_transition` specially; every other lifecycle mutation failure uses repository-owned generic safe copy.
- UI feedback is an accessible `role="alert"`; raw backend/provider message bodies are never rendered or retained.
- Genuine RED `a6f94b95fb8e25bc1706d7b51deaa8804eaa1dfa` / CI `34336846896`: lint/typecheck passed; 58 Jest tests with exactly two safe-error failures.
- GREEN `da93b2aeb352e777d46a7aa51e983d64c5891abb`; exact evidence head `34b0b8270b2878a87dd0db82e62452fa3e9277d8` / CI `34343009214` all four permanent jobs GREEN.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-JOB-ERRORS.md`.

Completed bounded slice — explicit Knowledge loading/empty/error states:

- Knowledge loading uses `role="status"` / `aria-live="polite"`; read failures use a safe repository-owned `role="alert"`; existing bounded empty states remain server-authoritative.
- Genuine RED `0728d11b56d02719398c3a9c9f82a6d53c559bde` / CI `34347854533`: 60 Jest tests with exactly two Knowledge-state failures.
- GREEN `96da25d4efbd8509810ac8072e93244e00deb21c` / runner `34348170990`: 60/60 Jest tests and full JavaScript verification GREEN.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-STATES.md`.

Completed bounded slice — responsive/long-content and keyboard hardening:

- Both Knowledge result states share the responsive root hook; nested content is width-constrained; long text/links wrap; enqueue collapses to one column on WordPress mobile widths; lifecycle/pagination targets receive mobile-friendly minimum heights.
- Native selected source/document links and `aria-current` semantics remain intact; regression coverage includes a 240-character source title and bounded empty state.
- Verified implementation `5cf9a7ddfaf3060c5ac8134465899e886b4e14c2`; durable evidence head `9e40ea2cd35ae17ae41e69d4ed9a6ade0649549f` / CI `34354356130` all four permanent jobs GREEN.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-RESPONSIVE.md`.

Completed bounded slice — latest-request-wins concurrent Knowledge navigation:

- A monotonically increasing selection generation plus current hash-route checks prevent stale source/detail/document/chunk responses from overwriting a newer route selection or state after leaving Knowledge.
- Genuine RED `e0d6332cf85b64dd07e108c488e79746a8bc00b9` / CI `34361171785`: lint/typecheck passed; Jest 26 suites / 63 tests with exactly one stale-navigation failure.
- GREEN production implementation `fab6a157bdeb3ef8095d0780a7980ea104b8ff88`; exact branch head `caba788276ce1c05ab042046e0f3032038e57f79` / CI `34361588728` all four permanent jobs GREEN with 63/63 Jest tests GREEN.
- Redundant one-shot runner removed at `0b8664c342398b0d5ceeaf4372be7edbb426b97a`.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-NAVIGATION-RACE.md`.

Completed bounded slice — latest-request-wins top-level Knowledge source pagination:

- A separate page-generation/current-route guard prevents stale bounded source-page successes from overwriting a newer hash-selected page and suppresses stale page failures after a newer page succeeds.
- Genuine stale-success RED `78db64a04c4e960b377a7bb180d69402c8f0431b` / CI `34391739460`: lint/typecheck passed; Jest 27 suites / 64 tests with exactly one intended failure.
- Combined genuine RED `1caa472ed8da7cba4fb76d05bdf38e099800bc13` / CI `34392061167`: lint/typecheck passed; Jest 28 suites / 65 tests with exactly two intended failures.
- Production fix `73875144415582b9b77793498840cfa88595c301`; guarded GREEN runner `34392453519`: full `npm run verify:js`, Jest 28/28 suites / 65/65 tests, build and live-gating GREEN.
- Fresh-session independent closeout review `5158649984`: one Important page race found/resolved; 0 Critical / 0 Important unresolved.
- Evidence: `docs/progress/M13-TASK4-KNOWLEDGE-PAGE-RACE.md`.

Fresh-session independent Task 4 closeout review `5158649984` found one Important top-level source-page navigation race and resolved it under genuine RED → GREEN. Final Task 4 review state is **0 Critical / 0 Important unresolved** and PR #18 has no unresolved inline review threads. The page-level race evidence is `docs/progress/M13-TASK4-KNOWLEDGE-PAGE-RACE.md`.

## Current work

**Task 5 — Structured debug trace projection and redaction** is the authoritative next unfinished unit.

Task 4 is complete after fresh-session independent review `5158649984` found and resolved one Important top-level source-page race with 0 Critical / 0 Important unresolved. Exact continuation:

- begin Task 5 with a fresh genuine RED for structured retrieval `DebugTrace` projection/redaction;
- use explicit allow-lists and hard bounds for trace/candidate/chunk diagnostic fields;
- never serialize credentials, raw provider payloads, arbitrary upstream errors, unrestricted metadata, or unbounded chunk/candidate data;
- reuse existing retrieval/domain seams rather than creating a parallel retrieval implementation.

## Durable recovery

- `docs/milestones/M13-knowledge-manager-playground-debugger.md` — M13 milestone ledger.
- `docs/superpowers/specs/2026-09-08-m13-knowledge-manager-debugger-design.md` — auto-approved design.
- `docs/superpowers/plans/2026-09-08-m13-knowledge-manager-debugger.md` — auto-approved implementation plan.
- `docs/progress/M13-TASK1-KNOWLEDGE-SOURCES.md` — Task 1 evidence.
- `docs/progress/M13-TASK2-KNOWLEDGE-DETAIL.md` — Task 2 evidence.
- `docs/progress/M13-TASK3-JOB-LIFECYCLE.md` — Task 3 evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-SOURCE-RENDERING.md` — Task 4 source-rendering evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-BOOTSTRAP.md` — router/bootstrap/source-loading evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-DETAIL-UI.md` — selected-source detail/document evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-CHUNKS.md` — selected-document chunk/navigation evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-JOBS.md` — bounded job inventory evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-JOB-ACTIONS.md` — cancel/retry evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-JOB-ENQUEUE.md` — enqueue evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-JOB-ERRORS.md` — safe mutation-error evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-STATES.md` — loading/empty/error evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-RESPONSIVE.md` — responsive/long-content/keyboard evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-NAVIGATION-RACE.md` — latest-request-wins async navigation evidence.
- `docs/progress/M13-TASK4-KNOWLEDGE-PAGE-RACE.md` — latest-request-wins top-level source-page evidence and independent closeout review.
- PR #18 — milestone-wide draft integration record.
