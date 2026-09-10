# M13 Task 5 — Structured Retrieval Debug Trace

Status: **COMPLETE**

## Scope completed

Task 5 adds an administrator-safe structured projection over the existing M10 retrieval evidence without introducing a second retrieval pipeline.

The implementation adds:

- immutable `DebugTrace` serialization contract;
- `DebugTraceProjector` over production `RetrievalResult` / `RetrievalTrace` / `RetrievalCandidate` evidence;
- raw query omission in favor of the existing SHA-256 query hash and byte count;
- hard cap of 20 candidate projections;
- hard cap of 4 approved channel-evidence entries per candidate;
- UTF-8-safe candidate content truncation at 2000 bytes plus `content_truncated`;
- UTF-8-safe 256-byte cap for candidate identifier/classification scalar strings before serialization;
- explicit candidate field allow-list;
- explicit `semantic` / `lexical` channel-count allow-list;
- explicit `semantic` / `lexical` candidate channel-evidence allow-list;
- stable M10 rerank status and failure-code projection;
- no recursive/raw provider-object serialization.

Task 6 remains responsible for the protected playground REST execution path that combines this retrieval projection with existing M11 answer/citation/model/usage outputs.

## Genuine RED / GREEN evidence

### Initial projection contract

- RED `16e93f023be9f75fee94b0a5bb67e4494ac97cdc` / CI `34393777911`:
  - PHPStan completed cleanly;
  - PHPUnit executed 688 tests / 2897 assertions;
  - exactly one error occurred because `WpRagAiChatbot\Debug\DebugTraceProjector` did not exist.
- Initial implementation landed through `927b9f4de7be711265d7e2588f0c8e2501d55e70` and `353e5cc9420ea77f6da8f37648d1e4c8129696dc`.
- CI `34393985186` then exposed PHP convention failures only; it was not classified as GREEN.
- Convention corrections landed at:
  - `fb6b64469e9b1406b2a6ff682177bee6f39c5a24`
  - `a93fcdd1c65b51c9e8ac36142dfebc6813496c30`
  - `d6be959d4cf90f6608f591ddeffac31cdab47701`

### Channel-count redaction review

Fresh security review found that `RetrievalTrace::channel_counts` accepts arbitrary non-empty keys while the first projector implementation copied that array wholesale.

- `a3eeaa9656402a9d9ad725d881c665b3aa26f3ac` was a test checkpoint but **not RED** because PHP formatting stopped before PHPUnit.
- Genuine RED `a4fe6acb71801950ca62c21fd73d39374711456a` / CI `34435048753`:
  - PHP static analysis completed cleanly;
  - PHPUnit executed 689 tests / 2911 assertions;
  - exactly one failure proved `SECRET-CHANNEL-SENTINEL` crossed the debug DTO boundary.
- GREEN production fix `31fd52d0184e3471330ae8fd80173bc87aef70ad` filters counts to repository-owned `semantic` / `lexical` keys before serialization.

### Candidate channel-evidence redaction review

Fresh security review also found that `ChannelEvidence::$channel` accepts arbitrary non-empty strings while the initial projector serialized the value verbatim.

- Genuine RED `be2221c0cc534ffdb2f51b35822eb0917679be99` / CI `34435288746`:
  - PHP static analysis completed cleanly;
  - PHPUnit executed 690 tests / 2913 assertions;
  - exactly one failure proved unapproved candidate channel evidence remained in the DTO.
- Production fix `7d5a9dafa4fad2946e508ebc8a273b6a6cdeeb5f` filters evidence to `semantic` / `lexical` before applying the four-entry cap.
- `7d5a9dafa4fad2946e508ebc8a273b6a6cdeeb5f` was not claimed GREEN because PHPCS stopped before PHPUnit on assignment alignment only.
- Formatting-only correction `5f2a05f70a199df697a8cc0474d9987fa62f80ea` preserved production behavior.

### Candidate scalar boundedness closeout review

A separate fresh-session correctness/security/performance review found one Important boundedness defect: `chunk_id`, `document_id`, `language`, and `visibility` were copied verbatim even though `RetrievalCandidate` accepts arbitrary-length strings. Candidate count and content bounds therefore did not guarantee a bounded serialized trace.

- Genuine RED `98bea8bb675d2eafc77f6117565ccd9d396d2049` / CI `34438769550`:
  - PHPStan completed cleanly;
  - PHPUnit executed 691 tests / 2917 assertions;
  - exactly one failure proved an oversized candidate scalar remained 385 bytes instead of the required maximum 256 bytes.
- GREEN production fix `fb774b832594a19b702d4c6caabacc5094b5e30b` adds a projection-owned 256-byte UTF-8-safe cap for candidate identifier/classification scalar strings without changing M10 retrieval/ranking objects or behavior.
- Exact production CI `34438873422` is GREEN across `php-quality`, `js-quality`, `package`, and complete `wordpress-smoke`.

## Exact-head verification

Exact production head `fb774b832594a19b702d4c6caabacc5094b5e30b` / CI `34438873422` is fully GREEN:

- `php-quality`: SUCCESS;
- `js-quality`: SUCCESS;
- `package`: SUCCESS;
- `wordpress-smoke`: SUCCESS, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and environment cleanup.

## Security / privacy review

Final boundaries ensure:

- raw query content is not serialized;
- candidates are hard bounded and candidate content is UTF-8-safe truncated;
- candidate identifier/classification strings are capped at 256 UTF-8-safe bytes;
- candidates use an explicit field allow-list rather than object recursion;
- arbitrary channel-count names cannot cross the DTO boundary;
- arbitrary candidate evidence channel names cannot cross the DTO boundary or consume bounded evidence slots;
- stable M10 failure/rerank codes are reused rather than upstream error bodies;
- no provider credentials, authorization material, provider payloads, environment/config dumps, or unrestricted metadata are added by Task 5.

Fresh-session closeout review after `fb774b832594a19b702d4c6caabacc5094b5e30b` found **0 Critical / 0 Important unresolved**. Query diagnostics are fixed-shape (`sha256` hash plus byte count), failure/rerank strings are constrained by `RetrievalTrace`, candidate/channel collections are hard-capped, and all candidate free-form serialized strings are now bounded.

## Performance review

Projection work is bounded and linear over at most 20 candidates. Each projected candidate emits at most four approved channel-evidence records, at most 2000 bytes of content, and at most 256 bytes for each candidate identifier/classification string. No repository scan, network request, provider call, unbounded cache, or polling loop is introduced.

## Accessibility

Task 5 is a server-side DTO/projection unit and introduces no interactive UI. Accessibility acceptance applies when Task 7 renders the trace.

## Closeout state

Task 5 is **COMPLETE**. Genuine RED/GREEN evidence exists for the initial projection and every Important closeout finding; the final fresh-session review has 0 Critical / 0 Important unresolved; exact production SHA `fb774b832594a19b702d4c6caabacc5094b5e30b` is green across all four permanent CI jobs. The next unfinished unit is Task 6 — protected `POST /admin/debug/playground` execution over existing M10/M11 production seams and this projector.
