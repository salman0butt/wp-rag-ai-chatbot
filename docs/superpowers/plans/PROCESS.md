# Execution Workflow

Approved milestone plans are executed under Superpowers. In environments with real subagent dispatch, use subagent-driven development. In this chat runtime, ADR-017 selects executing-plans as the fallback while retaining TDD, review, verification, and durable progress ledgers.

## Autonomous scheduled-development runs

When an invocation is explicitly scheduled, recurring, hourly, autonomous, unattended, or a fresh-session continuation run, follow the repository-wide policy in `AGENTS.md` and `docs/AUTONOMOUS-DEVELOPMENT.md`.

For those runs, the repository owner has pre-authorized internal design, specification, implementation-plan, branch-finishing, PR, merge, and milestone-transition approvals. Required Superpowers stages must still be performed and self-reviewed, but the agent must not pause for a separate human confirmation between those stages.

This pre-authorization never waives evidence gates. Failed tests/CI, unresolved Critical or Important findings, security defects, merge conflicts, missing required credentials, unavailable required services without an approved fallback, or GitHub permission/protection failures remain real blockers and must not be relabeled as approved.
