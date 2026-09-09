# M13 Task 4 — Knowledge concurrent navigation race hardening

Status: **COMPLETE SLICE — Task 4 closeout remains active pending independent review**

## Scope

This slice prevents an older asynchronous Knowledge source/document/chunk request from overwriting a newer hash-route selection.

The browser now uses a monotonically increasing Knowledge selection generation. Source detail/document responses are fetched into local variables and are committed to shared UI state only when both the generation and current hash-route source still match. Chunk responses apply the same generation/source/document checks. Leaving Knowledge increments the generation, invalidating any outstanding Knowledge selection request. Stale request failures are ignored only when their generation has already been superseded; current-request failures still follow the existing safe Knowledge error path.

No REST route, authorization policy, source/job/debug DTO, provider call, persistence contract, queue semantics, or unbounded client cache was added.

## Genuine RED evidence

Genuine RED: `e0d6332cf85b64dd07e108c488e79746a8bc00b9`, CI `34361171785`.

The exact-head `js-quality` job passed engine checks, package lint, JavaScript lint and TypeScript before Jest. Jest then executed 26 suites / 63 tests with 25 suites and 62 tests passing and exactly one failing regression in `src-js/knowledge-navigation-race.test.ts`.

The test deliberately delayed source `17`'s document response, navigated to source `18`, and required source `18` to become authoritative before the stale source `17` response resolved. Production failed because `[data-knowledge-selected-detail="18"]` was absent while the older request was still pending. This is the intended missing latest-request-wins behavior, not a formatting/type/harness failure.

## GREEN evidence

Verified production implementation: `fab6a157bdeb3ef8095d0780a7980ea104b8ff88` (`fix(m13): keep newest knowledge navigation authoritative`).

The implementation:

- introduces `knowledgeSelectionGeneration` for request correlation;
- returns source detail/documents from the fetch helper without mutating shared state;
- commits detail/documents only when generation and current source still match;
- commits chunks only when generation, current source and current document still match;
- invalidates outstanding selection work when leaving Knowledge;
- preserves current-request errors while suppressing only already-stale failures.

At later exact branch head `caba788276ce1c05ab042046e0f3032038e57f79`, permanent CI `34361588728` was fully GREEN. `npm run verify:js` passed lint/typecheck and Jest reported 26/26 suites and 63/63 tests GREEN, including `knowledge-navigation-race.test.ts`; build, Pinecone/Chroma/provider/Qdrant live-gating and package assertion also passed. `php-quality`, `package`, and complete `wordpress-smoke` were GREEN.

A redundant re-trigger copy of the already-completed one-shot patch runner was removed in cleanup commit `0b8664c342398b0d5ceeaf4372be7edbb426b97a`; no product behavior changed in that cleanup.

## Scoped coordinator review

Critical: **0**.

Important: **0**.

Correctness: stale asynchronous work can no longer commit source/detail/document/chunk state after a newer route selection or after leaving Knowledge. The guard checks both a monotonic generation and the current parsed route, avoiding cross-source/cross-document state corruption.

Security/privacy: the fix adds only numeric request-correlation state. It does not broaden browser DTOs or serialize source config, credentials, provider payloads, raw documents, job payloads, lease/idempotency data, or backend exception bodies.

Performance: one integer increment and constant route checks are added around already-bounded requests. No polling, retries, duplicate retrieval pipeline, unbounded cache, or collection scan is introduced.

Accessibility: no semantic/keyboard contract changes; the fix only determines which already-accessible Knowledge selection is authoritative.

Independent reviewer transport remains transiently unavailable in this automation runtime, so no independent-review result is claimed. The final Task 4 independent closeout review remains mandatory before Task 4 is marked COMPLETE or Task 5 begins.

## Continuation

After cleanup exact-head CI is GREEN, reconcile `docs/progress/STATUS.md` and the M13 milestone ledger to record that all planned Task 4 behavior is implemented and verified. Then obtain the mandatory independent Task 4 correctness/security/performance/accessibility review. Resolve any Critical/Important finding under fresh RED -> GREEN evidence, require exact-final-SHA permanent CI GREEN, and only then mark Task 4 COMPLETE and begin Task 5 structured debug-trace projection/redaction.
