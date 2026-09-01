# M20 — Image/File Input, Vision, STT, TTS & Realtime Voice

Status: NOT STARTED

## Goal
Add capability-gated multimodal interaction without assuming provider/model support.

## Dependencies
M03 capability model, M11 chat, M14 widget.

## In Scope
Image/file chat input; vision; speech-to-text; text-to-speech; realtime voice where practical/current APIs support it; upload limits; capability-aware admin/widget messaging.

## Out of Scope
Mandatory realtime infrastructure or pretending unsupported models support modalities.

## Architecture
Separate provider capability interfaces/adapters; secure media pipeline; feature enabled only when configured model/provider declares and passes capability checks.

## Acceptance Criteria
Unsupported modes fail clearly; uploads validated; audio/image data retention disclosed; cancellation/timeouts work; fallback UX defined.

## Tasks
Pending plan; may split realtime voice from basic STT/TTS/vision.

## TDD Evidence
Required for capability gating/validation.

## Integration Test Evidence
Mock provider contracts; opt-in live smoke tests.

## E2E / Visual Verification
Mobile microphone/upload, permission denied, loading/cancel/error, keyboard alternatives.

## Security Review
Malicious files, oversized media, privacy, provider retention, denial-of-wallet.

## Accessibility Review where UI exists
Required; non-voice alternatives mandatory.

## Performance Review where relevant
Upload sizes, streaming audio resource use, timeouts.

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
M21 — Analytics/Observability/Evals.
