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
2. read the **default-branch** versions of `AGENTS.md` and this file as the current controller policy;
3. inspect active feature branches and open PRs before deciding which status checkpoint is current;
4. read `docs/progress/STATUS.md` from the active work branch when it contains a newer live checkpoint; otherwise use the default-branch checkpoint as the recovery index;
5. inspect the current milestone document and its active design/spec and implementation plan;
6. inspect the active branch/PR head, latest relevant commits, exact-head CI, and unresolved review threads/findings;
7. inspect the source/tests directly relevant to the current task;
8. reconcile the durable state against Git, code, tests, CI, and PR evidence;
9. determine the first legitimate unfinished task and continue immediately when the state is consistent.

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

### 9.1 Autonomous CI completion wake-up signal

For same-repository pull requests, the permanent `.github/workflows/ci.yml` workflow publishes one sticky top-level PR conversation comment after the four permanent CI jobs reach terminal states.

The comment contains the marker:

`<!-- autonomous-ci-status -->`

The CI workflow must update the existing marker comment rather than create a new status comment for every run. The signal records at least:

- the real PR head SHA from `github.event.pull_request.head.sha`;
- workflow run ID and attempt;
- aggregate status;
- non-successful job names/results;
- individual permanent-job results;
- update timestamp and workflow-run URL.

The wake-up job must:

1. depend on all permanent CI jobs and use `always()` so failure/cancellation can still be signaled;
2. run only for `pull_request` events whose head repository is this repository;
3. use narrowly scoped permissions, with write access only to the PR conversation comment;
4. never expose secrets or raw logs in the signal;
5. never use the synthetic PR merge SHA as the autonomous action SHA;
6. preserve the existing CI jobs and their results unchanged.

Autonomous workers may treat an `autonomous-ci-status` comment update as a wake-up signal only when its `head_sha` matches the current PR head and the represented workflow result is new/relevant to the current gate.

A stale signal for an older head SHA is not actionable evidence. Re-fetch the current PR, exact-head CI, reviews, and lease before repository writes.

The CI-status comment is control-plane activity. Updating it must not by itself justify duplicate work, and workers must not recursively rewrite it.

For fork-origin pull requests, this repository does not publish the write-capable status comment; normal CI remains authoritative.

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

Hourly schedules, event-triggered runs, manual Work runs, and CI-triggered continuations can overlap. Autonomous workers must serialize repository writes for the same active unit.

Before writing:

1. inspect active automation/feature branches and PRs;
2. inspect whether CI is already running for the same task;
3. inspect recent commits for the same milestone/task;
4. reuse/resume existing work instead of creating a parallel implementation;
5. when an active PR exists, obey the canonical PR lease protocol below before the first repository write.

### 12.1 Canonical PR worker lease

For active PR work, keep exactly one top-level PR conversation comment containing:

`<!-- autonomous-worker-lease -->`

That comment is the canonical advisory lease for autonomous repository writes on that PR. Do not create a committed lock file merely to represent worker ownership.

The lease records at least:

- `state`: `ACTIVE` or `RELEASED`;
- `milestone`;
- `task`;
- `branch`;
- `owner`;
- `lease_id`;
- `head_sha`;
- `acquired_at`;
- `expires_at`;
- `last_checkpoint_at`.

Default lease lifetime: **30 minutes** from acquisition or renewal.

### 12.2 Lease acquisition

Before the first repository write on an active PR:

1. fetch the PR and the canonical lease comment;
2. reconcile the lease `head_sha`, milestone, and task against current Git/PR state;
3. if `state: RELEASED`, the worker may attempt acquisition;
4. if `state: ACTIVE` and `expires_at` is in the past, treat the lease as stale and the worker may recover/take over;
5. if `state: ACTIVE` and unexpired for the same unit, do not make competing repository writes;
6. immediately before claiming, re-fetch the lease and PR head;
7. acquire only if the lease is still eligible and the head/task have not changed unexpectedly;
8. write a unique `lease_id`, owner identity for the invocation, current head SHA, acquisition time, and a new expiry.

The re-read-before-write step is mandatory. The lease is advisory rather than a transactional database lock, so fresh objective Git/PR evidence remains part of the collision check.

### 12.3 Lease renewal and transfer

Renew the lease after meaningful progress checkpoints, including:

- genuine behavioral RED established;
- production implementation commit;
- focused/broad GREEN established;
- review completed;
- Critical/Important finding fixed;
- exact-head CI transition requiring continued work;
- merge/closeout transition when the same invocation will continue.

A renewal updates at least `head_sha`, `last_checkpoint_at`, and `expires_at = now + 30 minutes`.

When one task or milestone completes and the same invocation immediately continues to the next legitimate unit, **transfer/renew the existing lease** to the new milestone/task instead of releasing it between units.

### 12.4 Lease release and crash recovery

Release the lease only when the invocation genuinely ends or no further write work will be performed by that invocation.

On release:

- set `state: RELEASED`;
- set `owner: none`;
- set `lease_id: none`;
- set `acquired_at: none`;
- set `expires_at: none`;
- preserve the latest milestone, task, branch, head SHA, and checkpoint timestamp for recovery context.

A crashed/terminated worker requires no manual cleanup. Once an ACTIVE lease expires, a later worker may recover current Git/PR/CI state and take it over.

An expired lease must never permanently block development.

### 12.5 Conflict and stale-worker rules

Treat another worker as active only when there is fresh objective evidence, such as:

- an unexpired canonical lease for the same unit;
- a recent conflicting commit combined with evidence the writer is still progressing;
- a currently running write-sensitive workflow explicitly associated with that worker/unit.

A stale branch, old status text, historical CI, merely open PR, or expired lease is not sufficient evidence of an active conflicting worker.

If another active worker owns the same unit and conflicting writes cannot be safely avoided, make no competing implementation changes. Safe read-only/non-conflicting analysis may continue; otherwise record/report the collision and end that invocation.

If work appears abandoned — no fresh conflicting commits, no relevant running work, and no unexpired ownership signal — recover from Git/PR/CI and resume rather than waiting indefinitely.

Lease-comment updates are control-plane activity. Event-triggered automation must not recursively start duplicate coding work solely because the lease comment itself was created, renewed, transferred, or released.

### 12.6 Worker health states

The live checkpoint may summarize autonomous worker health with one of these advisory states:

- `ACTIVE` — an unexpired lease and fresh progress evidence indicate a worker currently owns the unit;
- `WAITING_CI` — the current relevant head has required CI still running and no immediate write is justified;
- `IDLE_READY` — no active conflicting worker exists and the recorded next action is immediately executable;
- `STALLED` — roadmap work remains, no active lease/relevant CI is running, and meaningful progress has not advanced despite an executable next action;
- `BLOCKED_EXTERNAL` — a true credential, permission, service, contradiction, or other external blocker prevents safe progress;
- `COMPLETE` — the defined roadmap has passed final completion verification.

These states are recovery hints, not stronger evidence than the current PR, lease, Git graph, reviews, code/tests, and exact-SHA CI.

### 12.7 Hourly watchdog and stale-worker recovery

The event-triggered autonomous task is the normal fast continuation path. The hourly scheduled task is a recovery watchdog and must not blindly create a second writer.

At the beginning of every watchdog run:

1. read the current default-branch controller documents and recover the live checkpoint;
2. verify default-branch/post-merge health before feature work;
3. discover the active PR/branch, if any;
4. re-fetch the canonical worker lease;
5. re-fetch the current PR head SHA, exact-head CI/checks, autonomous CI-status signal if present, unresolved reviews/threads, recent commits, and the exact next task;
6. reconcile all of that evidence before deciding whether a repository write is allowed.

Apply this decision order:

1. **broken default branch/post-merge CI** — repair first;
2. **unexpired ACTIVE lease for the same unit** — do not compete; perform only safe read-only/non-conflicting work, then end if no independent work is useful;
3. **current exact-head CI failure** with no active conflicting lease — acquire/recover the lease and debug/fix;
4. **unresolved Critical/Important finding** with no active conflicting lease — acquire/recover the lease and run the required regression/fix/re-review loop;
5. **expired lease** — treat the prior worker as stale, recover current Git/PR/CI state, acquire a fresh lease, and continue;
6. **RELEASED/no lease + executable unfinished work** — acquire the lease and resume immediately;
7. **current exact-head CI still running** — mark/recover as `WAITING_CI`; use the invocation only for safe non-conflicting review/docs/analysis unless new evidence justifies a new SHA;
8. **no active PR but roadmap incomplete** — recover the next unfinished milestone/task from default-branch state and begin/resume it according to the normal controller;
9. **roadmap complete** — run the repository-wide completion gate before recording `COMPLETE`.

Work is considered **stalled/abandoned and must be resumed** when all of the following are true after fresh recovery:

- the defined roadmap is incomplete;
- no unexpired worker lease protects the current unit;
- no relevant required CI is currently running;
- no true external blocker exists;
- an exact next action is executable;
- there is no fresh objective evidence that another worker is actively progressing the same unit.

Do not use a fixed elapsed-time threshold as the sole proof of a stall. Timestamps such as `last_progress_at` help recovery, but current lease/CI/PR/commit/review evidence decides whether takeover is safe.

A watchdog takeover must still perform the normal re-read-before-lease-acquisition rule and must never overwrite a newer PR head or newly acquired lease observed during acquisition.

If a watchdog discovers that the event-driven chain is healthy, it should leave it alone rather than manufacture work.

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

### 14.1 Live autonomous checkpoint

`docs/progress/STATUS.md` must keep a compact, easy-to-scan live checkpoint near the top of the file for the active work. Record, when applicable:

- completed milestone range on the default branch;
- current milestone and current task;
- active branch and PR;
- exact active head SHA;
- `worker_state` using the advisory health states above;
- current `lease_state`, lease ID/owner/expiry when applicable;
- current engineering gate/state, such as design, test-only RED, implementation GREEN candidate, review-fix, exact-head CI, merge, or post-merge verification;
- latest **valid** exact-SHA CI evidence and whether it is still current;
- latest observed CI state for the current head, including running/failed/success when known;
- unresolved Critical/Important review findings;
- current blocker/wait condition, if any;
- exact next executable action;
- `last_progress_at` for the last meaningful repository progress checkpoint when useful;
- `watchdog_observed_at` or equivalent recovery-observation timestamp when the checkpoint itself is refreshed without new engineering progress.

For active feature work, maintain the freshest checkpoint on the active branch at meaningful task gates. The default-branch checkpoint may act as a recovery index that points to the active PR/head until that work is integrated.

Never let status prose override stronger evidence from Git, source/tests, the active PR, reviews, or exact-SHA CI.

Distinguish genuine TDD evidence from pre-test failures. A lint/static-analysis/setup failure that prevents the intended test from executing is **not** a behavioral RED and must be recorded as an invalid RED/pre-test gate failure. Claim genuine RED only when the intended test actually executes and fails for the expected missing/incorrect behavior.

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
