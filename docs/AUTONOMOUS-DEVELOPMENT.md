# Autonomous Scheduled Development

This document defines how recurring fresh-session engineering runs continue this repository without relying on prior conversation state.

It is intentionally process-focused. Product scope, milestone acceptance criteria, architecture, security rules, and feature boundaries remain defined by the existing repository documentation.

## 1. Mode activation

Use this mode when the invocation explicitly says it is scheduled, recurring, hourly, autonomous, unattended, or a fresh-session continuation run.

The repository owner has pre-authorized the agent to execute the complete internal development workflow without pausing for additional human approval between design, specification, planning, implementation, review, branch-finishing, PR, and merge stages.

The agent must still perform every required stage. Pre-authorization removes waiting, not engineering rigor.

## 2. Fresh-session recovery is mandatory

Assume every invocation starts with no reliable memory of earlier runs.

Start with a fast, evidence-driven recovery:

1. fetch/inspect the current default branch;
2. read `AGENTS.md` and this file;
3. read `docs/progress/STATUS.md`;
4. inspect the current milestone document and its active design/spec and implementation plan;
5. inspect the active feature branch/PR, latest relevant commits, exact-head CI, and unresolved review threads/findings;
6. inspect the source/tests directly relevant to the current task;
7. reconcile the durable state against Git, code, tests, CI, and PR evidence;
8. determine the first legitimate unfinished task and continue immediately when the state is consistent.

Escalate to broader repository recovery only when evidence is inconsistent or the transition requires it, including when:

- durable status disagrees with Git or code;
- an active branch/PR changed unexpectedly;
- a merge occurred;
- CI evidence is stale or ambiguous;
- another autonomous worker changed the same area;
- milestone transition requires broader product/architecture context;
- documentation appears stale or contradictory.

A broader recovery may include `README.md`, `readme.txt`, `docs/PRODUCT.md`, `docs/ARCHITECTURE.md`, `docs/DECISIONS.md`, `docs/FEATURE-MATRIX.md`, milestone/progress ledgers, Superpowers specs/plans, relevant CI artifacts, and neighboring source/tests.

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

## 6. Continuous coherent execution per invocation

A schedule controls when a run starts; it does not require the agent to spend exactly one hour working or to stop after one unit.

A coherent unit is a transaction/checkpoint boundary, not an invocation boundary.

For each invocation:

1. recover the highest-priority ready unit;
2. implement, verify, review, document, and integrate it safely;
3. update durable state when appropriate;
4. recover the immediately relevant branch/PR/CI state;
5. select the next legitimate ready unit;
6. continue in the same invocation.

Completing a design, spec, plan, task, commit, PR update, green CI run, merge, post-merge verification, coherent unit, or milestone is **not** an intentional stop condition.

Do not start speculative or conflicting work merely to consume time. Continue only with legitimate ready work that preserves milestone boundaries and repository safety.

If the current execution environment ends before all ready work is exhausted, leave precise durable state for the next fresh run.

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

Do not waste an invocation merely waiting for CI when safe, non-conflicting work remains. While exact-SHA CI is running, the agent may perform review, security/performance/accessibility analysis, documentation reconciliation, unresolved-thread inspection, coverage analysis, milestone acceptance checks, or preparation of the next bounded unit.

Never treat that parallel work as a substitute for CI evidence. Do not merge until required exact-final-SHA CI is green. If a new commit changes the candidate SHA, prior CI evidence becomes stale and the new SHA must be verified.

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

After meaningful checkpoints, and always before ending a productive run, update the appropriate existing durable ledgers so another completely fresh session can recover accurately.

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

## 15. True stop/blocker policy

Scheduled runs must not pause for ordinary internal approvals or convenient handoff points.

A run may intentionally stop only when safe productive progress is genuinely impossible or the defined project is complete, including:

- the currently defined repository roadmap is genuinely complete;
- missing credentials/secrets required for the selected work;
- GitHub permissions/branch protection requiring an action the agent cannot perform;
- required external service unavailable with no approved fallback;
- infrastructure failure that prevents required verification and leaves no safe independent work;
- logically contradictory requirements not resolvable from repository evidence;
- unavoidable concurrent-write conflict with another active run;
- the current execution/tool/runtime environment actually prevents further productive work.

Task completion, PR completion, merge completion, post-merge verification, and milestone completion are not stop conditions.

When blocked or runtime-limited:

1. do not fabricate completion;
2. preserve safe partial work only if it is coherent and clearly documented;
3. record the blocker or exact continuation point in the appropriate durable location when it affects project state;
4. report the exact condition and next action;
5. let the next scheduled invocation recover from GitHub again.

## 16. Milestone transition

When the current milestone satisfies every repository completion gate:

1. mark it complete in durable ledgers;
2. record exact final SHA and required CI/artifact/review evidence;
3. integrate using the repository finishing-development-branch workflow;
4. verify fresh default-branch CI;
5. recover the next unfinished milestone immediately;
6. run its architecture classification and required design/spec/plan workflow under Scheduled Mode auto-approval;
7. begin its first legitimate implementation unit with strict TDD;
8. continue in the same invocation for as much safe productive work as the execution environment permits.

Milestone completion is a checkpoint, not a stop condition. Do not wait for the next scheduled run solely because a milestone completed.

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
- exact next unfinished task;
- reason the invocation actually stopped.

The next scheduled invocation must independently recover state from GitHub and continue from the resulting repository state.
