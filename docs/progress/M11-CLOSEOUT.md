# M11 Closeout — RAG Chat Orchestration, Grounding, Citations, Memory & Streaming

Status: **RELEASE CANDIDATE — final documentation-head exact-SHA CI, merge, and post-merge main CI still required**

## Delivered scope

M11 now provides the backend application contracts for bounded provider-neutral RAG chat orchestration:

- normalized and bounded chat requests/results/failure reasons;
- trusted server-side owner/access context separated from model-visible request data;
- owner-scoped conversation/message persistence and bounded memory assembly;
- request-local citation registry and fail-closed citation validation;
- bounded prompt construction with untrusted evidence/memory/question isolation;
- deterministic strict no-answer grounding policy;
- non-streaming orchestration over the existing M10 hybrid retriever and M03 generation provider boundary;
- normalized streaming events with bounded deltas/accumulated output and terminal/cancellation cleanup;
- owner-scoped assistant persistence only after completed generation and valid citations;
- sanitized text-free analytics hooks that are explicitly non-critical;
- end-to-end deterministic acceptance coverage using the real M10 retrieval + M11 application composition.

Frontend/widget/REST presentation work, durable analytics aggregation, debugger/evaluation UX, and live paid-provider performance are intentionally owned by later milestones.

## Task 9 TDD evidence

### Analytics wiring primary RED

Test-only standards cleanup preceded behavioral execution and is not counted as RED. The genuine primary Task 9 analytics RED is:

- head `99a966753041cb998622b398acbb22e138367a04`
- CI `34084745950`
- PHPStan: 0 errors
- PHPUnit: 625 tests executed with exactly three intended analytics-wiring failures: successful generation lacked analytics after persistence, strict no-answer emitted no analytics, and analytics failure containment never invoked the hook.

Initial production wiring landed at `47c6504a43c5c22921b55f1c5796e256bad19b4b`.

### Review regression RED

Task 9 review `5128177983` found one Important issue: `ChatAnalyticsEvent` construction occurred before the non-critical analytics exception boundary, so malformed normalized provider metadata could break a valid already-persisted chat result.

The genuine regression RED is:

- test-only head `c649185ac6a33d9fb33c2a692c5105c1c05a9606`
- CI `34085098367`
- PHPStan: 0 errors
- PHPUnit: 626 tests executed; exactly one error in `test_invalid_analytics_metadata_cannot_break_valid_chat_result`
- failure: `InvalidArgumentException: Provider ID must not be empty when present.` from `ChatAnalyticsEvent` construction after valid persistence.

Fix `b249759d1282a87170230fcef34f2da648f6a200` constructs and records analytics inside the same `Throwable` containment boundary.

## Verification evidence

Exact implementation-head CI on `b249759d1282a87170230fcef34f2da648f6a200`:

- workflow `34085227550` — **SUCCESS**
- `php-quality` — SUCCESS
  - PHPStan: 0 errors
  - PHPUnit: **626/626 tests**, **2,631 assertions**
  - Composer audit: no vulnerability advisories
- `js-quality` — SUCCESS
- `package` — SUCCESS
- `wordpress-smoke` — SUCCESS
- `autonomous-ci-status` — SUCCESS
- package artifact: `wp-rag-ai-chatbot`, id `10004978082`, 858,736 bytes
- artifact digest: `sha256:63791339fd0915be35387922d0ce9b14810bd435fade61c11f55d8a0ccde7d47`

This implementation-head CI is strong evidence for the code. The branch must still pass a fresh full CI run on the final documentation head before merge.

## Acceptance coverage

`tests/Integration/RAG/RagChatAcceptanceTest.php` composes the real M10 hybrid retrieval implementation with the M11 non-streaming orchestration and deterministic external fakes. It verifies:

- selected permitted evidence produces a grounded answer with registry-backed citations and owner-scoped assistant persistence;
- restricted evidence produces deterministic strict no-answer and zero generation calls;
- invented/unselected citation IDs fail closed and are not persisted;
- raw provider diagnostics do not escape as client-facing application errors.

Focused Task 9 analytics tests verify success/no-answer metrics, persistence-before-analytics ordering, transport-failure containment, metadata-construction containment, and an exact text-free event field shape.

## Security review

Full Task 9 review `5128215473`: **0 Critical / 0 Important unresolved**.

Verified properties include:

- owner scope remains trusted server-side context and is used only by memory, retrieval, and owner-scoped persistence;
- M10 trusted access checks remain authoritative after retrieval/fusion;
- conversation repositories preserve prepared owner predicates and fail-closed cross-owner behavior;
- retrieved evidence, memory, and the question remain untrusted model data and cannot become authorization/policy;
- prompt section-delimiter spoofing and total context growth are bounded by the earlier reviewed M11 prompt contracts;
- citations resolve only against the request-local selected registry; unknown/unselected citation IDs fail closed;
- provider/database/analytics raw exceptions and credentials do not become client-visible diagnostics;
- successful assistant persistence occurs at most once and only after completed generation plus citation validation;
- generation occurs at most once per non-streaming request and zero times on deterministic strict no-answer;
- analytics contains no question, transcript, evidence, owner scope, credentials, or raw provider diagnostics;
- analytics transport and event-construction failures are non-critical;
- normalized streaming retains the reviewed per-delta/accumulated-output ceilings and terminal/cancellation cleanup.

Earlier M11 task reviews for request bounds, persistence/IDOR, memory bounds, citation parsing/spoofing, prompt injection/framing, grounding, persistence ordering/redaction, and streaming bounds have no unresolved Critical/Important findings.

## Performance review

The M11 application path remains bounded:

- current question: hard byte ceiling from Task 1;
- requested generation output: bounded token range;
- conversation memory: at most 12 recent messages and a shared 24 KiB text budget with at most one versioned summary;
- selected citation/evidence candidates: at most 12;
- complete evidence section: hard 49,152-byte ceiling after framing/escaping;
- retrieval fan-out remains bounded by M10 configuration;
- non-streaming generation: at most one provider call;
- assistant persistence: at most one append after validation;
- analytics: no additional provider/database call in the application contract;
- streaming: bounded individual deltas and 65,536-byte accumulated output ceiling with cleanup on cancellation/terminal paths.

No unbounded transcript replay, retrieval fan-out, prompt growth, duplicate provider invocation, or duplicate assistant write was found in the reviewed M11 path.

## Known non-blocking limitations

- Normal CI uses deterministic fake provider/vector boundaries and intentionally does not spend paid live-provider credits or claim live network latency performance.
- Public REST/widget/admin integration belongs to later milestones.
- RAG debugger/evaluation UI belongs to later milestones.
- Durable analytics event storage/aggregation/cost dashboards belong to M21; M11 exposes only the sanitized non-critical hook contract.

## Remaining release gate

M11 is implementation-complete but is not yet complete on `main`. Required final sequence:

1. run full CI on the final closeout/documentation head and require all permanent jobs GREEN;
2. re-fetch PR #16, exact head, canonical lease, reviews, and inline threads;
3. mark PR ready and merge only with expected-head SHA protection;
4. verify a fresh post-merge `main` CI on the resulting default-branch SHA;
5. only then treat M11 as completed on `main` and advance immediately to M12.
