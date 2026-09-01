# Known Issues

## KI-001 — Local GitHub/package DNS unavailable in current chat runtime

Status: OPEN / ENVIRONMENT

The execution container cannot resolve external GitHub/package hosts and has no Composer binary, so native clone/worktree plus local Composer/npm dependency execution are unavailable. Connected GitHub branch isolation and GitHub Actions provide the dependency-backed workflow under ADR-016/018. This is not a repository/runtime-product fault.

## KI-002 — Baseline tests did not exist at repository bootstrap

Status: CLOSED IN M01

M01 established reproducible PHP unit, JS/TS unit/quality, real WordPress lifecycle smoke, dependency audit, and production ZIP validation through the permanent CI workflow.

## KI-003 — WordPress JS development toolchain has non-critical transitive advisories

Status: OPEN / NON-BLOCKING DEVELOPMENT TOOLCHAIN

The M01 verified npm tree reports 32 transitive advisories: 22 moderate and 10 high, with zero critical advisories. The affected paths are in development/build tooling pulled through the WordPress scripts/env ecosystem; Node dependencies are not included in the production plugin ZIP. `npm audit --audit-level=critical` is a blocking M01 CI gate, while the non-critical transitive set is tracked for dependency refresh/re-evaluation rather than hidden or force-upgraded across breaking versions.

## KI-004 — Public-release metadata normalization remains outstanding

Status: EXPECTED / RELEASE PREPARATION

The approved M01 pre-release metadata uses plugin/package version `0.1.0-dev` while the WordPress readme stable tag/changelog is staged as `0.1.0`. This is not a published WordPress.org release. M24 must normalize and re-verify release metadata before public distribution.
