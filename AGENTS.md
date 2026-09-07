# Repository Agent Instructions

These instructions apply repository-wide.

## Autonomous scheduled-development mode

When a task explicitly says it is a scheduled, recurring, hourly, autonomous, unattended, or fresh-session continuation run, operate in **autonomous scheduled-development mode**.

In this mode, the repository owner has pre-authorized the agent to continue milestone-by-milestone without waiting for additional human approval at internal workflow gates.

Read and follow `docs/AUTONOMOUS-DEVELOPMENT.md` before making changes.

## Source of truth

Every run must reconstruct state from GitHub. Do not rely on prior chat memory.

Use the current default branch, active feature branches, open pull requests, recent commits, CI results, artifacts, source/tests, milestone ledgers, progress docs, Superpowers specs/plans, and repository decisions to determine the actual state.

Git + current code + fresh CI evidence take precedence over stale progress text.

## Superpowers

Use the installed Superpowers workflow and all applicable skills. Preserve design, planning, TDD, debugging, review, verification, and finishing-development-branch discipline.

### Pre-authorization for scheduled runs

For autonomous scheduled-development runs only, the owner pre-approves internal Superpowers design/spec/plan checkpoints. The agent must still perform the required thinking and produce the required artifacts, but it must not pause for a human response between those stages.

At a design gate:

1. inspect the repository and milestone boundaries;
2. develop viable approaches;
3. choose the recommended approach using existing architecture, YAGNI, compatibility, security, maintainability, and milestone scope as decision criteria;
4. document the alternatives and rationale;
5. self-review the design;
6. treat the recommended design as approved and continue.

At a spec gate:

1. write the spec;
2. self-review it for placeholders, contradictions, ambiguity, scope leakage, security, and testability;
3. fix findings;
4. treat the corrected spec as approved and continue.

At a plan gate:

1. write the executable plan;
2. review sequencing, TDD evidence requirements, rollback/risk points, CI strategy, and milestone boundaries;
3. fix findings;
4. treat the corrected plan as approved and continue into implementation.

This pre-authorization applies to internal development-process approvals only. It does **not** convert failed tests, failed CI, unresolved Critical/Important review findings, security defects, merge conflicts, missing credentials, unavailable external services, or repository permission failures into approved states.

## Authorized repository actions

In autonomous scheduled-development mode, the owner authorizes the agent to create/update branches, files, commits, pull requests, reviews, and to merge completed work when repository policy permits and all required quality gates are green.

Never bypass branch protection, force-push protected branches, expose secrets, weaken tests/CI to obtain green status, or merge known failing work.

## Continuation priority

Always continue existing unfinished work before starting new work:

1. broken default branch or failed post-merge CI;
2. failed CI on active work;
3. unresolved Critical/Important review findings;
4. unfinished active branch or PR;
5. documentation/evidence required to close the current milestone;
6. next unfinished task in the current milestone;
7. next milestone only after the current milestone is verified complete.

Do not duplicate work already present on another active branch or PR.

## Durable memory

Before ending a run, update the repository's existing durable progress records so the next completely fresh run can recover without conversational memory.

Do not create redundant status files when existing milestone/progress ledgers already serve the purpose.

## Stop conditions

Do not stop merely because a design, spec, plan, review, commit, PR, or merge step would normally request confirmation. Those steps are pre-authorized in scheduled mode.

Stop only when safe autonomous progress is genuinely impossible, for example:

- a required secret/credential is unavailable;
- an external service or dependency required for the task is unavailable and no repository-approved fallback exists;
- GitHub permissions or branch protection require a human action the agent cannot perform;
- two active runs would create conflicting writes and the conflict cannot be safely avoided;
- requirements are logically contradictory and repository evidence cannot resolve them.

Record the blocker and exact next action in durable project state when appropriate.
