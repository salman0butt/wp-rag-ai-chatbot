# Known Issues

## KI-001 — Local GitHub/package DNS unavailable in current chat runtime

Status: OPEN / ENVIRONMENT

The execution container cannot resolve external GitHub/package hosts and has no Composer binary, so native clone/worktree plus local Composer/npm dependency execution are unavailable. Connected GitHub branch isolation and GitHub Actions provide the dependency-backed workflow under ADR-016/018. This is not a repository/runtime-product fault.

## KI-002 — Baseline tests did not exist at repository bootstrap

Status: CLOSED IN M01

M01 established reproducible PHP unit, JS/TS unit/quality, real WordPress lifecycle smoke, dependency audit, and production ZIP validation through permanent CI.

## KI-003 — WordPress JS development toolchain has non-critical transitive advisories

Status: OPEN / NON-BLOCKING DEVELOPMENT TOOLCHAIN

The verified development tree reports non-critical transitive advisories through WordPress scripts/env tooling and zero critical advisories under the blocking M01/M02 npm gate. Node dependencies are not included in the production plugin ZIP. Keep refreshing/re-evaluating compatible upstream releases rather than using a blind breaking force-upgrade.

## KI-004 — Public-release metadata normalization remains outstanding

Status: EXPECTED / RELEASE PREPARATION

The approved pre-release metadata uses plugin/package version `0.1.0-dev` while the WordPress readme stable tag/changelog is staged as `0.1.0`. This is not a published WordPress.org release. M24 must normalize and re-verify release metadata before public distribution.

## KI-005 — Network-wide multisite migration/uninstall orchestration is deferred

Status: OPEN / DEFERRED ARCHITECTURAL POLICY

M02 deliberately operates against the active site's `$wpdb` prefix and verifies single-site lifecycle behavior. It does not implement network-activation iteration, network-wide schema upgrades, or network-wide uninstall deletion across every site in a multisite installation. The master architecture requires multisite policy to be decided explicitly before those assumptions harden; this remains deferred rather than silently inferred.

This limitation does not affect the verified single-site M02 migration/repository/uninstall behavior. It must be resolved in the milestone that owns multisite/agency/upgrade compatibility before M24 release qualification.
