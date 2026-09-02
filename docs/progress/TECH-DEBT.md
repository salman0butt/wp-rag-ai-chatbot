# Technical Debt

## Active implementation debt

### TD-001 — WordPress JavaScript tooling transitive dependency debt

Owner milestone: M24, or earlier when a compatible WordPress tooling release resolves it.

The development/build tree contains tracked non-critical advisories and deprecation/peer warnings through transitive `@wordpress/scripts` / `@wordpress/env` dependencies. The permanent npm gate blocks critical advisories. Node dependencies are development-only and excluded from the plugin ZIP, so this does not create a shipped Node runtime surface, but the CI/dev supply chain should be refreshed and re-audited without using a breaking `npm audit fix --force` blindly.

### TD-002 — Pre-release vs public-release version metadata

Owner milestone: M24.

The project intentionally follows the approved pre-release metadata: runtime/package version `0.1.0-dev` and WordPress readme stable/changelog `0.1.0`. Normalize these values and verify WordPress.org/readme semantics before any public plugin release.

## M02 debt assessment

M02 did **not** knowingly accept a new Critical/Important implementation compromise. Two Important review findings were fixed before completion:

1. MigrationRunner refreshes schema version after acquiring the advisory lock, so a process cannot rely on a stale pre-lock version and replay migrations completed by another process.
2. DatabaseUninstaller treats failed destructive queries as errors and preserves cleanup options, keeping uninstall retryable rather than silently losing state.

The single-site `$wpdb`-prefix behavior is a documented product limitation/policy decision still to make for multisite, not hidden implementation debt. It remains tracked as KI-005 and as an architectural risk until its owning milestone defines the policy.

## M03 debt assessment

M03 did **not** knowingly accept a new Critical/Important security or runtime compromise. Three Important review findings were fixed before completion:

1. Provider diagnostic bodies are now redacted before truncation so a credential crossing the 2048-byte boundary cannot expose a plaintext prefix.
2. Production package validation now requires every runtime provider PHP file and rejects development scripts.
3. Unexpected WordPress AI Client Throwables fail closed with a constant generic message instead of republishing opaque Core/provider text.

No arbitrary provider base URLs, paid-call retries, model-name capability heuristics, plaintext option storage, public prompt REST surfaces, provider admin UI, embeddings, RAG, streaming, tools/actions, or pricing tables were accepted as shortcuts in M03.

### Expected future work, not current debt

- Live OpenAI/OpenRouter smoke is credential-gated and intentionally not part of normal CI. KI-006 tracks release/staging acceptance when credentials are available.
- WordPress AI Client remains an optional compatibility adapter on the WordPress 6.9 baseline. KI-007 tracks that expected compatibility mode.
- Provider model metadata schemas can drift upstream; later compatibility audits should refresh fixtures/normalizers, but M03 avoids fragile model-name inference.
- Admin credential mutation UI/REST authorization, nonces, capabilities, and UX are owned by M12 and must reuse the M03 server-only storage boundary rather than bypassing it.
- Public rate/cost/abuse controls are owned by later public chat/security milestones and must wrap provider invocation before anonymous production use.

## Architectural risks to watch rather than prematurely solve

- Local vector-search scale and DB portability.
- PDF/DOCX extraction quality and resource limits.
- WordPress cron reliability on low-traffic sites.
- Provider capability/model metadata drift.
- Browser streaming behavior across hosting/proxy stacks.
- Database growth for conversations, traces, analytics, and eval runs.
- Multisite policy, including network activation/migrations/uninstall, which must be explicitly decided before release assumptions harden.

Items become implementation debt only when an actual compromise is accepted and recorded with an owner/milestone.
