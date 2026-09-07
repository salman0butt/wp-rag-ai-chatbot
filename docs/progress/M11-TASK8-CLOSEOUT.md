# M11 Task 8 Closeout — Normalized Streaming and Cancellation

Status: **COMPLETE / GREEN / OWNER-DIRECTED REVIEW CLOSED**

## Scope

Task 8 delivers provider-neutral streaming normalization with deterministic event sequencing, bounded deltas, trusted citation events, cancellation, sanitized failures, and provider-stream cleanup.

## Delivered behavior

- Stable event vocabulary: `message.start`, `message.delta`, `citation`, `message.complete`, `error`.
- Monotonic sequence numbers with `message.start` first and exactly one terminal event.
- Client-visible message deltas are bounded to 4 KiB while preserving valid UTF-8 boundaries.
- Accumulated streamed answer content is hard-bounded to 65,536 bytes, aligned with the persisted-message ceiling.
- Cancellation is checked before provider consumption and between chunks split from one oversized provider delta.
- Cancellation terminates without accepting subsequent content and provider cleanup still runs.
- Citation events are emitted only after request-local `CitationRegistry` validation; unknown/invalid model citation IDs fail closed.
- Provider and cleanup exceptions are normalized/contained without exposing raw provider diagnostics.
- The normalizer performs no assistant-message persistence itself, so cancelled/incomplete streamed output cannot be persisted at this boundary; Task 9 owns final persistence composition acceptance.

## TDD evidence

### Primary streaming contracts

- Primary Task 8 behavioral RED: `e0a473d643af15b88ea7e4b20cecb8968d681d32`, CI `34041369344`.
- PHPStan passed; PHPUnit reached the streaming contract tests and failed exactly because the streaming contracts were not yet implemented.
- Subsequent implementation introduced `StreamEventType`, `StreamEvent`, `GenerationStream`, `Cancellation`, and `StreamingChatOrchestrator`.

### Cleanup exception regression

- Behavioral RED: `777b6b65a7f6083cf181da8df0415fe9d0678179`, CI `34047394987`.
- The regression proved a throwing provider `close()` could escape after a terminal event.
- Fix sequence culminated at `51538cdd2ad654939a3693928fe2c4ce593cfe1b`, containing cleanup diagnostics at the client-safe boundary.

### Split-delta cancellation regression

- Behavioral RED: `9c9995883194ee8ed0f1c5da6e91661c87771add`, CI `34047932711`.
- The regression proved cancellation while the generator was suspended after the first bounded chunk could otherwise allow another chunk from the same oversized provider delta.
- Minimum production fix began at `f50b16473d4055c7bf5747e20d1fdf46555f0c70` and final analyzer-safe implementation was included at `effeedcef9ee545939fb9628d687f77d09044d75`.
- Exact code-head CI `34050249917`: all permanent jobs GREEN, PHPUnit 616/616 tests / 2,565 assertions, PHPStan 0 errors, Composer audit clean.

### Total accumulated-output review regression

Fresh owner-directed review `5127806828` found **0 Critical / 1 Important**: individual deltas were bounded but the accumulated answer was not, allowing unbounded memory/final-validation work from a misbehaving provider.

Two preliminary test-only heads are explicitly not valid behavioral RED evidence:

- `0d7d3a3722af7332759aec65ff67ee78edec057c` used an accidentally infinite fake stream.
- `f4b47b7cc26e4725f0df06b05bb9e11547cc4f49` stopped at PHPCS before PHPUnit.

Genuine behavioral RED:

- `962ba9c6c0fec68cd7c16c802b5705d778276100`, CI `34079861410`.
- PHPStan: 0 errors.
- PHPUnit: 617 tests / 2,566 assertions, exactly one failure.
- Failure: 69,632 bytes were accepted instead of the required 65,536-byte ceiling.

Minimum production fix:

- `d2d985a91c232e856dc452ae4fc1eac51267ea77` adds a running accumulated-answer byte ceiling, stops provider reads before accepting an overflowing chunk, emits one sanitized `generation_failed` terminal event, and preserves cancellation precedence.
- `a357929f6d26f2e40c42e55bcc4196c5aaf3f025` is formatting-only alignment required by PHPCS.

## Final verification

Exact Task 8 final code head before this closeout document: `a357929f6d26f2e40c42e55bcc4196c5aaf3f025`.

CI `34080065230`:

- `php-quality`: **SUCCESS**
  - PHPStan: 0 errors
  - PHPUnit: **617/617 tests, 2,571 assertions**
  - Composer audit: no security vulnerability advisories
- `js-quality`: **SUCCESS**
- `package`: **SUCCESS**
- `wordpress-smoke`: **SUCCESS**
- `autonomous-ci-status`: **SUCCESS**

No inline review threads are open.

## Final review

Owner-directed follow-up review `5127840305`, performed after the repository owner explicitly requested this agent to review and unblock Task 8:

- Critical: **0**
- Important: **0 unresolved**

The follow-up rechecked event ordering/monotonicity, 4 KiB per-delta bounds, 65,536-byte total-output bound, cancellation between split chunks, request-local citation authority, invalid-citation fail-closed behavior, provider/cleanup diagnostic redaction, terminal uniqueness, and cleanup.

This closeout records the review provenance accurately as owner-directed rather than falsely claiming a separate reviewer identity.

## Security/performance notes

- Provider text is bounded both per event and across the whole streamed answer.
- No raw provider or cleanup diagnostics are emitted to the client.
- Citation authority remains application-owned and request-local.
- Provider reads stop on cancellation and on total-output overflow.
- Stream cleanup always runs through `finally` and cleanup failures are contained.
- No retry/fallback provider generation path is introduced.

## Next task

Proceed immediately to **M11 Task 9 — end-to-end acceptance, hooks, security/performance closeout, PR and merge**.
