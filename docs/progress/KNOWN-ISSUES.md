# Known Issues

## KI-001 — Local GitHub/package DNS unavailable in current chat runtime

Status: OPEN / ENVIRONMENT

The execution container cannot resolve external GitHub/package hosts and has no Composer binary, so native clone/worktree plus local Composer/npm dependency execution are unavailable. Connected GitHub branch isolation and GitHub Actions provide the dependency-backed workflow under ADR-016/018. This is not a repository/runtime-product fault.

## KI-002 — Baseline tests did not exist at repository bootstrap

Status: CLOSED IN M01

M01 established reproducible PHP unit, JS/TS unit/quality, real WordPress lifecycle smoke, dependency audit, and production ZIP validation through permanent CI.

## KI-003 — WordPress JS development toolchain has non-critical transitive advisories

Status: OPEN / NON-BLOCKING DEVELOPMENT TOOLCHAIN

The verified development tree reports non-critical transitive advisories through WordPress scripts/env tooling and zero critical advisories under the blocking npm gate. Node dependencies are not included in the production plugin ZIP. Keep refreshing/re-evaluating compatible upstream releases rather than using a blind breaking force-upgrade.

## KI-004 — Public-release metadata normalization remains outstanding

Status: EXPECTED / RELEASE PREPARATION

The approved pre-release metadata uses plugin/package version `0.1.0-dev` while the WordPress readme stable tag/changelog is staged as `0.1.0`. This is not a published WordPress.org release. M24 must normalize and re-verify release metadata before public distribution.

## KI-005 — Network-wide multisite migration/uninstall orchestration is deferred

Status: OPEN / DEFERRED ARCHITECTURAL POLICY

M02 deliberately operates against the active site's `$wpdb` prefix and verifies single-site lifecycle behavior. It does not implement network-activation iteration, network-wide schema upgrades, or network-wide uninstall deletion across every site in a multisite installation. The master architecture requires multisite policy to be decided explicitly before those assumptions harden; this remains deferred rather than silently inferred.

This limitation does not affect the verified single-site M02 migration/repository/uninstall behavior. It must be resolved in the milestone that owns multisite/agency/upgrade compatibility before M24 release qualification.

## KI-006 — Live provider acceptance is intentionally opt-in

Status: OPEN / EXPECTED EXTERNAL-INTEGRATION LIMITATION

M03 permanent CI uses mocked provider contracts plus a real WordPress runtime with deterministic fake credentials; it deliberately makes no paid OpenAI/OpenRouter request. The live smoke path requires `WP_RAG_AI_LIVE_PROVIDER_TESTS=1`, an explicit `openai|openrouter` selection, and the matching environment credential. Discovery may then run; generation occurs at most once only when an explicit live model variable is supplied.

No production provider credentials were supplied during M03 completion, so live upstream acceptance is not claimed as part of normal CI. Re-run the opt-in live smoke when release/staging credentials are available and again during M24 provider compatibility qualification. This does not weaken the verified HTTP/error/cache/credential contracts or real-WordPress storage/bootstrap behavior.

## KI-007 — WordPress AI Client availability depends on WordPress 7 public AI runtime

Status: OPEN / EXPECTED COMPATIBILITY MODE

The plugin baseline remains WordPress 6.9+, where the WordPress AI Client public API may be absent. M03 treats that adapter as optional and reports it unavailable without fatal errors or fallback guesses. OpenAI Direct and OpenRouter Direct remain independent of the optional Core AI runtime.
