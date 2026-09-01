# M01 — WordPress Plugin Foundation & Tooling

Status: COMPLETE

## Goal
Create the minimal production plugin skeleton and reliable PHP/JS development, test, static-analysis, lint, build, WordPress integration, and CI foundations.

## Dependencies
M00 approved written spec and `docs/superpowers/plans/2026-09-01-m01-wordpress-plugin-foundation-tooling.md`.

## In Scope
Plugin bootstrap/lifecycle boundaries; Composer/PSR-4; selected PHP test/static/WPCS tooling; JS/TS build/test/lint/typecheck; WordPress test environment; initial CI; activation smoke test; coding conventions.

## Out of Scope
Product database schema, providers, RAG, admin product UI.

## Architecture
Thin WordPress entry point delegates to focused bootstrap/application components. No business logic in the global plugin file. Node remains development/build tooling only.

## Acceptance Criteria
- [x] Tooling commands documented and reproducible.
- [x] Plugin installs and activates on WordPress 6.9 / PHP 8.2 baseline environment.
- [x] Production PHP/TS behaviors show genuine RED-before-GREEN evidence.
- [x] PHP unit tests pass.
- [x] WPCS passes.
- [x] PHPStan passes.
- [x] JS lint/typecheck/unit tests/build pass.
- [x] Distribution ZIP includes required runtime files and excludes dev/private files.
- [x] CI passes on the exact code candidate commit.
- [x] Independent review has no unresolved Critical/Important findings.

## Tasks
1. [x] PHP toolchain, entry point, and bootstrap boundary.
2. [x] TypeScript build/test/lint foundation.
3. [x] WordPress activation/deactivation smoke harness and distribution metadata.
4. [x] Package guardrails and CI.
5. [x] Independent review, fresh verification, and durable state.

## TDD Evidence
GitHub Actions was the authoritative dependency-backed runner under ADR-018.

- PHP RED: test-only commit `4da1fa9009e96710181ed7c58e6b014a4a8fbe73` ran 4 tests and failed all four for the intended missing `Lifecycle`, `Bootstrap`, and plugin entry-point behaviors.
- PHP GREEN: minimal bootstrap commit `0f30bec389e9bf4a298936c5c85a2e6652002592` turned the focused suite green; the final candidate runs 4 tests / 12 assertions successfully.
- TypeScript RED: the focused Jest runner failed only with `Cannot find module './index'` while `src-js/index.ts` was intentionally absent.
- TypeScript GREEN: the minimal `pluginIdentity` implementation passed the focused test, then the complete engine/package-lint/JS-lint/typecheck/Jest/build chain.
- Package RED: GitHub Actions run `33546790330` failed exactly with `Missing wp-rag-ai-chatbot.zip` before package production was added.
- Package GREEN: production packaging creates the ZIP, validates required/excluded paths, and uploads the artifact.

## Integration Test Evidence
`wp-env` is pinned to WordPress 6.9 / PHP 8.2. The smoke job successfully starts WordPress, activates the plugin, verifies the Composer-loaded `WpRagAiChatbot\Core\Bootstrap` class, deactivates, reactivates, confirms active state, and stops the environment.

## E2E / Visual Verification
Not applicable. M01 intentionally introduces no user-visible UI.

## Security Review
- PHP dependency audit: no security vulnerability advisories on the verified candidate.
- CI token permissions are read-only (`contents: read`).
- Plugin ZIP excludes tests, docs, GitHub workflow files, environment files, dependency manifests/locks, and Node tooling.
- Node dependencies are development/build-only and are not shipped in the plugin ZIP.
- The current WordPress development-toolchain tree reports 22 moderate and 10 high transitive npm advisories, with zero critical advisories; this is recorded in `docs/progress/SECURITY.md`, `KNOWN-ISSUES.md`, and `TECH-DEBT.md` rather than hidden.

## Accessibility Review where UI exists
Not applicable; M01 introduces no UI.

## Performance Review where relevant
The bootstrap only registers lifecycle hooks and emits a `wp_rag_ai_chatbot_loaded` action. It does not query a database, call external services, or enqueue frontend assets.

## Code Review Findings
Independent review compared the complete M01 branch against `main` and re-read the runtime bootstrap, lifecycle, CI, smoke harness, packaging guard, metadata, and progress requirements. No unresolved Critical or Important implementation finding remains.

A packaging review found that `@wordpress/scripts plugin-zip` delegates its `files` handling to `npm-packlist`, which force-includes npm metadata such as `package.json`. Because the approved release policy forbids development manifests, packaging was wrapped to remove only the forced npm metadata before the strict package assertion. The corrected package job passes.

Non-blocking findings are tracked as technical debt: WordPress tooling transitive npm advisories/deprecations and pre-release/stable metadata normalization before a public WordPress.org release.

## Fixes
- Aligned WPCS configuration and WordPress formatting after the quality gate exposed rule mismatches.
- Added WordPress package metadata required by `wp-scripts lint-pkg-json`.
- Applied WordPress JS formatting after lint exposed Prettier differences.
- Added strict ZIP post-processing for npm-forced metadata while preserving the approved runtime allow-list.
- Removed temporary branch-only RED/GREEN workflows once the permanent four-job CI became authoritative.

## Fresh Verification Commands
Permanent `.github/workflows/ci.yml` executes:

- PHP: `composer validate --strict`, `composer install`, `composer audit`, `composer verify:php`.
- JS: `npm ci`, `npm audit --audit-level=critical`, `npm run verify:js`.
- WordPress: dependency install, build, `npm run env:start`, `npm run test:wp:activation`, `npm run env:stop`.
- Package: production-only Composer install, `npm ci`, build, strict plugin ZIP, `scripts/assert-package.sh`, artifact upload.

## Fresh Verification Results
Code candidate `255e978ba11f65b7300b492a6bfc6a94210e6b98` passed CI run `33547358423` across all four jobs: `php-quality`, `js-quality`, `wordpress-smoke`, and `package`.

Verified details:
- PHP 8.2.33; PHPUnit 10.5.64; 4 tests / 12 assertions; PHPStan no errors; WPCS pass; Composer audit clean.
- Node 22; package lint, JS lint, strict TypeScript check, 1 Jest test, and production Webpack build pass.
- WordPress 6.9/PHP 8.2 activation, bootstrap resolution, deactivation, and reactivation pass.
- Production ZIP passes strict required/excluded path validation and uploads as a CI artifact.

The documentation-complete branch head is re-run through the same permanent CI before M01 is reported complete externally.

## Commits
Representative implementation/evidence commits:
- `2788ec1f6aac1bb1c1f913fcaf79aebe1a84db9c` — self-reviewed M01 implementation plan.
- `4da1fa9009e96710181ed7c58e6b014a4a8fbe73` — lifecycle-specific PHP RED evidence.
- `0f30bec389e9bf4a298936c5c85a2e6652002592` — minimal WordPress plugin bootstrap GREEN.
- `3f3fcb901a5f6b2bdb73c04ff64ad80ea41427c4` — WordPress standards correction after evidence-driven debugging.
- `606cf7483b465d0d0333b0690f0f4f01aca1391f` — minimal TypeScript fixture GREEN.
- `850a531e1745595a90b049c5a8b8d501db37fc50` — permanent four-job CI foundation.
- `d14638cdfa5d4e971115ac4d948aea80649f3a7d` — strict plugin packaging fix.
- `255e978ba11f65b7300b492a6bfc6a94210e6b98` — temporary TDD workflow cleanup / verified code candidate.

## Files Changed
M01 adds the WordPress entry point and `src/Core` lifecycle/bootstrap, Composer and npm lockfiles/tooling, PHP and TypeScript tests, `wp-env` smoke harness, WordPress distribution metadata, strict package scripts, and the permanent four-job GitHub Actions CI workflow.

## Known Limitations
- This chat container still cannot install Composer/npm dependencies due external DNS/package-manager limitations; GitHub Actions remains the authoritative dependency-backed runner under ADR-018.
- Node/WordPress development tooling currently carries non-critical transitive npm advisories and deprecation warnings; these dependencies are not included in the production plugin ZIP.
- Public-release version/stable-tag normalization is deferred to the release audit milestone; M01 follows the approved pre-release metadata exactly.

## Documentation Updated
M01 milestone evidence plus global status, test matrix, known issues, security, and technical-debt ledgers are updated together.

## Completion Checklist
- [x] RED-before-GREEN evidence recorded for PHP, TypeScript, and package guard behavior.
- [x] PHP/JS quality gates pass.
- [x] Real WordPress smoke passes.
- [x] Strict production ZIP passes.
- [x] Security audits executed and results recorded.
- [x] Independent review has no unresolved Critical/Important findings.
- [x] Permanent CI passes the exact code candidate.
- [x] Durable completion state written.

## Next Milestone
M02 — Database Schema, Migrations & Domain Repositories.
