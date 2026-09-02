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

1. MigrationRunner now refreshes schema version after acquiring the advisory lock, so a process cannot rely on a stale pre-lock version and replay migrations completed by another process.
2. DatabaseUninstaller now treats failed destructive queries as errors and preserves cleanup options, keeping uninstall retryable rather than silently losing state.

The single-site `$wpdb`-prefix behavior is a documented product limitation/policy decision still to make for multisite, not hidden implementation debt. It remains tracked as KI-005 and as an architectural risk until its owning milestone defines the policy.

## Architectural risks to watch rather than prematurely solve

- Local vector-search scale and DB portability.
- PDF/DOCX extraction quality and resource limits.
- WordPress cron reliability on low-traffic sites.
- Provider capability/model metadata drift.
- Browser streaming behavior across hosting/proxy stacks.
- Database growth for conversations, traces, analytics, and eval runs.
- Multisite policy, including network activation/migrations/uninstall, which must be explicitly decided before release assumptions harden.

Items become implementation debt only when an actual compromise is accepted and recorded with an owner/milestone.
