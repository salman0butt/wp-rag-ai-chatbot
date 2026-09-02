# Autonomous Scheduled Development

This document defines how recurring fresh-session engineering runs continue this repository without relying on prior conversation state.

It is intentionally process-focused. Product scope, milestone acceptance criteria, architecture, security rules, and feature boundaries remain defined by the existing repository documentation.

## 1. Mode activation

Use this mode when the invocation explicitly says it is scheduled, recurring, hourly, autonomous, unattended, or a fresh-session continuation run.

The repository owner has pre-authorized the agent to execute the complete internal development workflow without pausing for additional human approval between design, specification, planning, implementation, review, branch-finishing, PR, and merge stages.

The agent must still perform every required stage. Pre-authorization removes waiting, not engineering rigor.

## 2. Fresh-session recovery is mandatory

Assume every invocation starts with no reliable memory of earlier runs.

Before modifying anything:

1. fetch/inspect the current default branch;
2. inspect active feature branches;
3. inspect open pull requests and unresolved review threads;
4. inspect recent commits and branch divergence;
5. inspect relevant GitHub Actions runs, job results, and artifacts;
6. inspect the actual source and tests for the current milestone;
7. read the relevant durable documentation, including:
   - `README.md` and `readme.txt`;
   - `docs/PRODUCT.md`;
   - `docs/ARCHITECTURE.md`;
   - `docs/DECISIONS.md`;
   - `docs/FEATURE-MATRIX.md`;
   - `docs/milestones/**`;
   - `docs/progress/**`;
   - `docs/superpowers/specs/**`;
   - `docs/superpowers/plans/**`;
   - this file and `AGENTS.md`;
8. reconcile documentation claims against Git, code, tests, CI, and artifacts;
9. determine the first legitimate unfinished task.

Do not redo completed work simply because a scheduled run starts fresh.

## 3. Recovery precedence

When sources disagree, prefer this order:

1. current Git graph and branch state;
2. actual source/tests at the relevant SHA;
3. fresh CI/job/artifact evidence for that SHA;
4. current PR/review state;
5. milestone/progress documentation;
6. old execution notes or historical handoff text;
7. prior conversational memory.

Correct stale durable documentation when evidence proves it is stale.

## 4. Autonomous approval policy

### 4.1 Internal workflow gates are pre-approved

During scheduled mode, do not stop to ask the owner to approve:

- brainstorming output;
- a recommended architecture/design;
- a written design spec;
- a written implementation plan;
- transition from plan to TDD implementation;
- routine code-review fixes;
- documentation reconciliation;
- branch finishing;
- creation of commits/PRs;
- merging a verified PR when repository policy permits it;
- transition to the next milestone after the current one is truly complete.

Instead, perform the required review at each gate, choose the strongest repository-consistent option, document the decision, and continue.

### 4.2 Design auto-approval procedure

For architectural milestones or changes:

1. inspect current architecture and neighboring milestone boundaries;
2. identify 2-3 viable approaches when meaningful alternatives exist;
3. compare correctness, security, compatibility, complexity, maintainability, performance, migration risk, and scope leakage;
4. prefer the smallest design that satisfies the milestone and preserves future extensibility;
5. record why the recommended approach wins;
6. self-review the design for ambiguity, hidden coupling, future-milestone leakage, and unsupported assumptions;
7. fix findings;
8. mark the selected approach as `AUTO-APPROVED — SCHEDULED MODE` in the relevant spec/plan evidence when useful;
9. continue without waiting for a human reply.

### 4.3 Spec auto-approval procedure

After writing a required spec:

1. scan for `TODO`, `TBD`, placeholders, contradictions, and ambiguous language;
2. verify scope against PRODUCT/ARCHITECTURE/DECISIONS/milestone docs;
3. verify security and data-boundary implications;
4. verify acceptance criteria are objectively testable;
5. fix all material findings;
6. record `AUTO-APPROVED — SCHEDULED MODE` when appropriate;
7. continue to planning.

### 4.4 Plan auto-approval procedure

After writing a required implementation plan:

1. verify sequencing and dependencies;
2. verify RED -> GREEN -> REFACTOR evidence can be captured;
3. verify branch/isolation strategy;
4. verify CI and integration coverage;
5. verify review/security/performance/accessibility gates as applicable;
6. verify durable documentation updates are included;
7. fix material plan defects;
8. record `AUTO-APPROVED — SCHEDULED MODE` when appropriate;
9. continue to implementation.

### 4.5 What cannot be auto-approved

The following are evidence/technical gates, not human-confirmation gates, and must remain real:

- failing tests;
- failing lint/static analysis/type checks;
- failing WordPress smoke/integration checks;
- failing package validation;
- failed or stale CI evidence;
- unresolved Critical review findings;
- unresolved Important review findings unless an existing repository rule explicitly records them as accepted debt;
- secret leakage or credential exposure;
- destructive data behavior not authorized by product requirements;
- merge conflicts;
- branch protection failures;
- missing required credentials;
- unavailable required external systems without an approved fallback.

Never label one of these states "approved" merely to continue.

## 5. Work-selection priority

Each run selects work in this order:

1. repair a broken default branch or failed post-integration CI;
2. repair failed CI on the active milestone branch/PR;
3. resolve Critical/Important review findings;
4. continue unfinished work already present on an active branch or PR;
5. finish documentation/evidence/integration required to close the current milestone;
6. implement the next unfinished task in the current milestone;
7. begin the next milestone only after the current milestone completion gate is satisfied.

Do not jump ahead because later work appears easier or more interesting.

## 6. One coherent unit per invocation

A schedule controls when a run starts; it does not require the agent to spend exactly one hour working.

For each invocation, complete the largest coherent unit that can be safely implemented, verified, reviewed, documented, and integrated with available tools/evidence.

Do not begin a large second unit simply to consume remaining time.

If a milestone can be safely completed in one invocation, complete it. If not, leave precise durable state for the next run.

## 7. Required engineering loop

For every meaningful milestone/task, preserve the repository's established loop:

`RECOVER -> DESIGN -> PLAN -> ISOLATE -> RED -> VERIFY RED -> IMPLEMENT MINIMUM CORRECT CHANGE -> GREEN -> REFACTOR -> INTEGRATION TEST -> SECURITY REVIEW -> PERFORMANCE REVIEW -> ACCESSIBILITY REVIEW WHEN UI -> INDEPENDENT REVIEW -> FIX FINDINGS -> RE-REVIEW -> FULL VERIFICATION -> UPDATE DURABLE DOCS -> COMMIT -> FINISH BRANCH/PR -> VERIFY DEFAULT-BRANCH CI -> MARK COMPLETE`

Use applicable Superpowers skills throughout.

## 8. Strict TDD

For meaningful behavior changes:

1. add the smallest regression/behavior test first;
2. execute it against the relevant pre-fix state;
3. prove RED for the expected reason;
4. record exact SHA/CI evidence when milestone ledgers require it;
5. implement only enough to make the behavior pass;
6. verify focused GREEN;
7. run broader relevant verification;
8. refactor only while green.

Do not fabricate RED/GREEN history.

## 9. CI and execution-environment adaptation

When the active runtime cannot execute dependency-backed tests locally, use the repository-approved GitHub Actions path documented in existing ADRs/process docs.

Always associate verification with the exact SHA being evaluated.

Do not reuse stale CI from an older SHA as proof for newer code.

## 10. Branch, commit, PR, and merge authorization

Scheduled mode explicitly authorizes the agent to:

- create or reuse repository-approved feature branches/worktrees;
- modify source, tests, docs, workflow files, and configuration within milestone scope;
- create focused commits;
- push branches;
- open/update pull requests;
- respond to review findings;
- merge verified work when repository rules and GitHub permissions allow.

Prefer existing unfinished branches/PRs over creating duplicates.

Never:

- force-push protected branches;
- bypass branch protection;
- merge known failing code;
- disable useful tests or security checks to obtain green CI;
- expose credentials;
- overwrite unrelated developer work;
- rewrite completed milestones without evidence of a defect;
- silently broaden milestone scope.

## 11. Merge gate

A PR/branch may be integrated automatically only when all applicable conditions are true:

- intended bounded scope is complete;
- focused tests pass;
- broader relevant tests pass;
- PHP quality passes;
- JS quality passes;
- WordPress smoke/integration passes;
- package validation passes;
- required CI checks on the exact final SHA pass;
- security review is complete;
- performance review is complete where relevant;
- accessibility review is complete for UI work where relevant;
- no unresolved Critical findings remain;
- no unresolved Important findings remain unless explicitly accepted by existing repository policy;
- no unresolved blocking review threads remain;
- milestone/progress documentation matches the final implementation;
- merge conflicts are absent;
- repository finishing-development-branch rules are satisfied.

After integration, verify fresh default-branch CI before marking the milestone complete.

## 12. Concurrency and duplicate-run safety

Hourly schedules can overlap with CI or another long-running invocation.

Before writing:

1. inspect active automation/feature branches and PRs;
2. inspect whether CI is already running for the same task;
3. inspect recent commits for the same milestone/task;
4. reuse/resume existing work instead of creating a parallel implementation.

If another active run is clearly modifying the same unit and conflicting writes cannot be safely avoided, make no competing implementation changes. Record/report the state and exit that invocation.

Do not create repeated specs/plans for an already-selected design simply because a new scheduled session starts.

## 13. Architectural decision defaults

When repository evidence does not dictate a single choice and human confirmation would normally be requested, choose autonomously using these priorities:

1. preserve existing product requirements and milestone boundaries;
2. preserve security and least privilege;
3. preserve backwards compatibility and migration safety;
4. follow established repository architecture/conventions;
5. prefer deterministic behavior over unnecessary AI/network dependencies;
6. prefer the smallest maintainable implementation that satisfies acceptance criteria;
7. avoid future-milestone scope leakage;
8. minimize new dependencies and operational complexity;
9. keep interfaces extensible only where a documented later milestone requires it;
10. document the choice and alternatives.

Do not invent missing product requirements merely to make a decision easier.

## 14. Durable repository memory

Before ending a productive run, update the appropriate existing durable ledgers so another completely fresh session can recover accurately.

Keep synchronized as applicable:

- `docs/milestones/MXX-*.md`;
- `docs/progress/STATUS.md`;
- `docs/progress/TEST-MATRIX.md`;
- `docs/progress/SECURITY.md`;
- `docs/progress/KNOWN-ISSUES.md`;
- `docs/progress/TECH-DEBT.md`;
- `docs/DECISIONS.md` when an architectural/process decision materially changes;
- `docs/superpowers/specs/**`;
- `docs/superpowers/plans/**`;
- execution evidence already used by the repository.

Record only evidence that actually exists.

## 15. True blocker policy

Scheduled runs must not pause for ordinary internal approvals.

A run may stop when safe progress is genuinely impossible, including:

- missing credentials/secrets required for the selected work;
- GitHub permissions/branch protection requiring an action the agent cannot perform;
- required external service unavailable with no approved fallback;
- infrastructure failure that prevents required verification;
- logically contradictory requirements not resolvable from repository evidence;
- unavoidable concurrent-write conflict with another active run.

When blocked:

1. do not fabricate completion;
2. preserve safe partial work only if it is coherent and clearly documented;
3. record the blocker in the appropriate durable location when it affects project state;
4. report the exact condition and next action;
5. let the next scheduled invocation recover from GitHub again.

## 16. Milestone transition

When the current milestone satisfies every repository completion gate:

1. mark it complete in durable ledgers;
2. record exact final SHA and required CI/artifact evidence;
3. integrate using the repository finishing-development-branch workflow;
4. verify fresh default-branch CI;
5. begin the next milestone in the same invocation only if doing so remains a coherent, safely bounded unit; otherwise leave the exact next action for the next scheduled run.

No separate human "approved" message is required in scheduled mode.

## 17. End-of-run report

Every invocation should report, as available:

- repository/default-branch SHA;
- active branch/PR;
- current milestone;
- task selected;
- work completed;
- RED/GREEN evidence;
- verification and CI results;
- review findings and resolutions;
- documentation updated;
- commit SHA(s);
- PR number/status;
- merge status;
- post-merge default-branch CI status;
- overall milestone status;
- blockers, if any;
- exact next unfinished task.

The next scheduled invocation must independently recover state from GitHub and continue from the resulting repository state.
