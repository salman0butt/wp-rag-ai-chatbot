# M02 — Database Schema, Migrations & Domain Repositories

Status: COMPLETE — runtime candidate verified; branch integration pending.

## Goal
Introduce versioned database infrastructure and the minimal domain repositories needed by upcoming milestones without pre-creating later product schema.

## Dependencies
M01.

## Authoritative Plan
`docs/superpowers/plans/2026-09-02-m02-database-schema-migrations-repositories-v2.md`

## Delivered Scope
- Dedicated `rag_ai_sources` and `rag_ai_documents` tables only.
- Versioned, incremental migrations with a persisted schema version.
- MySQL advisory migration lock with lock release in `finally`.
- Post-lock schema-version refresh to prevent stale concurrent runners from replaying migrations.
- Fresh-install, V1→V2 upgrade, repeat/idempotency, and failure-aware migration behavior.
- `$wpdb` connection adapter and WordPress schema-version store.
- Source/document records, repository contracts, prepared lookup SQL, `$wpdb` write APIs, and pagination clamped to 100 rows.
- Real WordPress repository integration with SQL-injection-shaped literal data, Unicode, script-like content, JSON metadata hydration, source isolation, and exact delete counts.
- Retain-by-default uninstall policy with explicit opt-in deletion, safe table order, retry-safe failure propagation, option cleanup only after successful drops, and clean reinstall verification.
- Strict package validation requiring the uninstall runtime while continuing to exclude development/private files.

## Out of Scope Preserved
No vector tables/search, job tables, conversation/analytics tables, providers/credentials, RAG orchestration, WooCommerce behavior, admin UI, or frontend widget behavior was added.

## TDD Evidence
- Repository integration RED established missing repositories/value objects before implementation; the focused value-object RED culminated at `c018e586d5089612ad531dce22b04ccc633af039`.
- Repository GREEN exact head `a6fec65d99271ab554e88d984f8b56bce2d350e6`; CI run `33600969514` passed PHP, JS, package, and real WordPress database smoke.
- Uninstall RED `6a00e3646b32ff56fe2bb77dfab6a7a0e7d2f4c8` failed because the uninstall runtime did not exist.
- Uninstall GREEN exact head `72cadc138c707a9f96c802c0bc281267894adc30`; CI run `33602771870` passed all four jobs and verified retain/delete/reinstall behavior.
- Independent review found a stale-version concurrency race. RED `c721349b1e1171ff5366733dcdaee11b3d2cde95` / run `33603026768` failed only because the runner returned `MIGRATED` instead of `UP_TO_DATE`; GREEN `15c88f46a804a776adf945053e0df39b45cc94fa` refreshes the version after lock acquisition inside the protected `try`.
- Independent review found uninstall cleanup could silently lose retry state after a failed DROP. RED `ff6143b5fb71cda3f463d0e78b557d1de9bc8e2f` / run `33603348517` failed only because no `DatabaseException` was thrown; GREEN `4db24d95db0d572f28273734714c74a47ac8bb2e` propagates the failure and preserves options.

## Integration / Security Evidence
Permanent `wordpress-smoke` exercises activation, fresh migration, simulated V1→V2 upgrade, repeat migration, repository CRUD/pagination, hostile literal data, uninstall retention, explicit deletion, and clean schema recreation in a real WordPress 6.9/PHP 8.2 environment.

Prepared boundaries were verified with values including `source-' OR 1=1 --`, `" OR 1=1 --`, apostrophes, `<script>literal test data</script>`, and Unicode `مرحبا`. They round-trip as data and do not alter row counts or source scope. Table identifiers come only from trusted plugin-owned `TableNames` values.

No secrets, provider credentials, external model calls, public REST endpoints, or user-facing action surfaces were introduced in M02.

## Performance Evidence
- Source/document tables have indexes for stable keys and expected source/filter access paths.
- Repository list methods clamp page size to 100.
- The normal current-schema `plugins_loaded` path performs the small schema-version option read and exits before composing the database/lock/migration stack.
- M02 intentionally avoids future high-volume tables until their owning milestones.

## Independent Review
Fresh `main...feat/m02-database-schema-continuation` review covered migration ordering/versioning/locking, prepared-value boundaries, pagination, uninstall safety, package contents, and scope creep.

Findings fixed:
1. **Important:** stale schema version after lock acquisition could replay migrations after another process completed them. Fixed and regression-tested.
2. **Important:** uninstall DROP failure could be ignored while cleanup options were removed. Fixed so failures throw and state remains retryable.

Unresolved Critical/Important findings: **none**.

## Verified Runtime Candidate
- Commit: `4db24d95db0d572f28273734714c74a47ac8bb2e`
- GitHub Actions run: `33603435032`
- `php-quality`: success — Composer validation/audit, WPCS, PHPStan, PHPUnit.
- `js-quality`: success — unchanged M01 quality/security/build baseline.
- `wordpress-smoke`: success — activation plus full M02 database/repository/uninstall integration.
- `package`: success — strict runtime archive validation and artifact upload.
- Artifact: `wp-rag-ai-chatbot`, ID `9836065304`, 30,864 bytes.
- Artifact digest: `sha256:f77b32bf377b4f6fbb65cf1721a87b5e0408041ad5444816076227ab931aeab3`.

The documentation-complete commit containing this ledger must also pass the same permanent CI before branch integration.

## Files Changed
M02 changes are confined to database/core lifecycle infrastructure, source/document domain records and repositories, WordPress database integration scripts/tests, uninstall runtime, package/CI wiring, and milestone evidence. The final branch compare is ahead of `main` with no M03 provider/credential/RAG/UI implementation.

## Known Limitations
- Network-wide multisite activation/migration orchestration is not implemented in M02; current behavior follows the active site `$wpdb` prefix. Multisite policy remains explicitly deferred.
- Native local git worktree/dependency execution is unavailable in this chat runtime because external DNS is restricted. Connected GitHub branch isolation and GitHub Actions are the verified execution path under existing environment ADRs; this is not a plugin runtime defect.
- Existing non-critical WordPress JavaScript development-tooling advisories remain tracked from M01 and are not shipped in the production ZIP.

## Documentation Updated
`M02`, global status, test matrix, security ledger, known issues, and technical-debt ledger.

## Completion Checklist
- [x] Required M02 scope implemented.
- [x] RED observed before production behavior for repository/uninstall/review fixes.
- [x] PHP/static/dependency gates green on runtime candidate.
- [x] Real WordPress migration/repository/uninstall integration green.
- [x] Package guard and artifact upload green.
- [x] Security/performance review complete.
- [x] Independent review complete; no unresolved Critical/Important issues.
- [x] Exact runtime candidate and artifact digest recorded.
- [ ] Documentation-complete SHA permanent CI must be green before integration.

## Next Milestone
M03 — AI Providers, Credentials & Compatibility. Production implementation begins only after the M02 branch integration decision required by the finishing workflow.
