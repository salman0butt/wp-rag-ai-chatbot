# Technical Debt

No implementation technical debt exists yet because production implementation has not started.

Potential risks to watch rather than prematurely solve:

- Local vector-search scale and DB portability.
- PDF/DOCX extraction quality and resource limits.
- WordPress cron reliability on low-traffic sites.
- Provider capability/model metadata drift.
- Browser streaming behavior across hosting/proxy stacks.
- Database growth for conversations, traces, analytics, and eval runs.
- Multisite policy, which must be explicitly decided before schema/runtime assumptions harden.

Items become actual debt only when an implementation compromise is accepted and recorded with owner/milestone.
