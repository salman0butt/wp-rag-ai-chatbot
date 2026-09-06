# Repository Agent Instructions

These instructions apply repository-wide.

## Autonomous scheduled-development mode

When a task explicitly says it is a scheduled, recurring, hourly, autonomous, unattended, or fresh-session continuation run, operate in **autonomous scheduled-development mode**.

In this mode, the repository owner has pre-authorized the agent to continue milestone-by-milestone without waiting for additional human approval at internal workflow gates.

Read and follow `docs/AUTONOMOUS-DEVELOPMENT.md` before making changes.

## Source of truth

Every run must reconstruct state from GitHub. Do not rely on prior chat memory.

The current default-branch versions of `AGENTS.md` and `docs/AUTONOMOUS-DEVELOPMENT.md` are the controller policy for scheduled runs, even when resuming an older feature branch whose copies may predate the latest process policy.

Use the current default branch, active feature branches, open pull requests, recent commits, CI results, artifacts, source/tests, milestone ledgers, progress docs, Superpowers specs/plans, and repository decisions to determine the actual state.

For active work, inspect the active PR/branch checkpoint before trusting the default-branch status summary. Git + current code + fresh exact-SHA CI + current PR/review state take precedence over stale progress text.

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

## Continuous execution

A coherent unit is a transaction/checkpoint boundary, not an invocation boundary.

Do not intentionally stop because a design/spec/plan, task, commit, PR update, green CI run, merge, post-merge verification, or milestone completed. After a verified checkpoint, recover the immediately relevant state, select the next legitimate ready unit, and continue in the same invocation for as much safe productive work as the execution environment permits.

## Autonomous write lease

Before repository writes on an active PR, every autonomous worker must obey the canonical PR worker-lease protocol in `docs/AUTONOMOUS-DEVELOPMENT.md`. An unexpired lease for the same unit blocks competing writes; an expired lease is recoverable and must not permanently block progress.

## Durable memory

Maintain the repository's existing durable progress records after meaningful checkpoints and before an invocation ends so the next completely fresh run can recover without conversational memory.

`docs/progress/STATUS.md` must expose a compact live autonomous checkpoint for the active work: milestone, task, branch, PR, head SHA, current gate, latest valid CI/review evidence, blocker/wait state, and exact next executable action. Detailed historical evidence belongs in milestone/task closeout records rather than bloating the live checkpoint.

Do not create redundant status files when existing milestone/progress ledgers already serve the purpose.

## Stop conditions

Do not stop merely because an ordinary workflow gate or convenient handoff point was reached.

Intentional stopping is allowed only when the defined roadmap is genuinely complete or safe productive progress is impossible, for example:

- a required secret/credential is unavailable;
- an external service or dependency required for the task is unavailable and no repository-approved fallback exists;
- GitHub permissions or branch protection require a human action the agent cannot perform;
- two active runs would create conflicting writes and the conflict cannot be safely avoided;
- requirements are logically contradictory and repository evidence cannot resolve them;
- the current execution/tool/runtime environment prevents further productive work.

If runtime/tool limits end a run while work remains, preserve the exact continuation point durably. Record the blocker or stop reason and exact next action when appropriate.
