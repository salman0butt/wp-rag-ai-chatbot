# Technical Debt

## Active implementation debt

### TD-001 — WordPress JavaScript tooling transitive dependency debt

Owner milestone: M24, or earlier when a compatible WordPress tooling release resolves it.

The current development/build tree reports 22 moderate and 10 high npm advisories plus deprecation/peer warnings through transitive `@wordpress/scripts` / `@wordpress/env` dependencies. There are zero critical advisories on the verified M01 tree. Node dependencies are development-only and excluded from the plugin ZIP, so this does not create a shipped Node runtime surface, but the CI/dev supply chain should be refreshed and re-audited without using a breaking `npm audit fix --force` blindly.

### TD-002 — Pre-release vs public-release version metadata

Owner milestone: M24.

M01 intentionally follows the approved pre-release metadata: runtime/package version `0.1.0-dev` and WordPress readme stable/changelog `0.1.0`. Normalize these values and verify WordPress.org/readme semantics before any public plugin release.

## Architectural risks to watch rather than prematurely solve

- Local vector-search scale and DB portability.
- PDF/DOCX extraction quality and resource limits.
- WordPress cron reliability on low-traffic sites.
- Provider capability/model metadata drift.
- Browser streaming behavior across hosting/proxy stacks.
- Database growth for conversations, traces, analytics, and eval runs.
- Multisite policy, which must be explicitly decided before schema/runtime assumptions harden.

Items become implementation debt only when an actual compromise is accepted and recorded with an owner/milestone.
