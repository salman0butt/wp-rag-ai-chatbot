# M15 — Display Rules, Proactive Triggers, Multilingual/RTL & Accessibility Implementation Plan

Status: AUTO-APPROVED FOR SCHEDULED AUTONOMOUS EXECUTION
Date: 2026-09-13
Design: `docs/superpowers/specs/2026-09-13-m15-display-rules-rtl-accessibility-design.md`
Milestone: `docs/milestones/M15-display-rules-rtl-accessibility.md`

## Operating rules

- Follow repository `AGENTS.md` and `docs/AUTONOMOUS-DEVELOPMENT.md`.
- Strict RED -> verify real RED -> minimal GREEN -> verify -> review -> durable evidence for every behavior slice.
- Test-only RED commits must be pushed when durable CI evidence is required.
- `NOT RED`/`NOT GREEN` checkpoints remain recorded honestly.
- Re-check remote branch head and Actions immediately before every write/push.
- Do not create a second public widget, chat path, provider/RAG path, appearance authority, or authorization path.
- Display facts are presentation inputs only; backend access remains independently server-enforced.
- Scheduled-mode architecture/spec/plan gates are auto-approved; do not stop for routine approval.

## Task 1 — normalized display-rule domain + pure evaluator

### 1A — M14-compatible defaults and disabled behavior

**Files**
- Create: `src-js/display-rules.ts`
- Create: `src-js/display-rules.test.ts`
- Create/update evidence: `docs/progress/M15-TASK1-DISPLAY-RULES.md`

**RED**
1. Add a focused Jest test importing `normalizeDisplayRules` and `evaluateDisplayRules`.
2. Assert missing config normalizes to M14-equivalent defaults: visible, proactive disabled, empty starters, site/default locale direction.
3. Assert `enabled: false` produces hidden decision with stable `disabled` reason.
4. Push test-only commit.
5. Verify CI reaches Jest and fails because production module/API is absent. Formatting/type-only failures are `NOT RED` and must be repaired before implementation.

**GREEN**
1. Add minimal exported types/defaults/normalizer/evaluator required by the test.
2. Keep evaluator side-effect free.
3. Run/verify focused JS test plus full `verify:js` via exact-head CI.
4. Review correctness/security/performance/architecture; record findings and fixes.

### 1B — include/exclude URL/path matching and precedence

**Files**
- Update `src-js/display-rules.test.ts`
- Update `src-js/display-rules.ts`

**RED**
1. Add fixtures for exact paths, bounded `*` glob, exclusion winning over inclusion, category-empty semantics, malformed patterns falling back safely.
2. Assert stable reason codes and equal-specificity first-rule tie break where relevant.
3. Push test-only RED and verify expected Jest failure.

**GREEN**
1. Add one deterministic matcher; do not use arbitrary regex supplied by config.
2. Normalize paths/wildcards conservatively.
3. Verify all JS gates and exact-head CI.

### 1C — audience/post/Woo/device gates

**RED**
1. Add fixture table for authenticated/anonymous/selected-role match tokens, post type, Woo area, and desktop/tablet/mobile.
2. Assert values within a category are OR, categories are AND.
3. Push/verify RED.

**GREEN**
1. Add only explicit fact fields and finite enums needed by evaluator.
2. Keep facts presentation-only.
3. Verify/review.

### 1D — schedule/day/time including overnight windows

**RED**
1. Fixtures: normal same-day interval, exact boundaries, inactive day, overnight window crossing midnight, invalid/missing schedule defaults.
2. Use explicit minutes/day/site-time facts; no evaluator call to ambient clock.
3. Push/verify RED.

**GREEN**
1. Implement pure schedule matching.
2. Verify/review.

### 1E — page starters + locale/direction decision

**RED**
1. Assert exact-path starter beats glob; first normalized equal-specificity rule wins.
2. Assert explicit `rtl`/`ltr` override; auto maps known RTL locale fixture to RTL and fallback locale to LTR.
3. Assert prompt lists remain bounded/plain text.
4. Push/verify RED.

**GREEN**
1. Implement deterministic starter/locale/direction projection.
2. Verify/review.
3. Complete Task 1 evidence with exact RED/GREEN SHAs/runs and all invalid checkpoints.

## Task 2 — PHP normalized config, bot persistence, protected admin and public projection

### 2A — immutable `DisplayRulesConfig`

**Files**
- Create `src/Frontend/DisplayRulesConfig.php`
- Create `tests/Unit/Frontend/DisplayRulesConfigTest.php` (or exact repository unit-test path recovered at execution time)

**RED**
- Tests for defaults, unknown-key rejection, finite enums, collection/string/timer/scroll bounds, glob/selector validation, prompt bounds.
- Push and verify PHPUnit reaches intended failures.

**GREEN**
- Implement immutable normalized value following `AppearanceConfig` conventions.
- Verify PHPUnit/PHPStan/PHPCS/Composer gates.

### 2B — bot-scoped persistence

**Files**
- Add/reuse repository/table storage following M14 bot appearance persistence conventions; exact DB/repository files selected after fresh schema recovery.
- Add database repository tests.

**RED**
- Per-bot isolation, defaults for existing bots, save/read round trip, deletion behavior.

**GREEN**
- Minimal schema/repository changes with existing migration conventions.
- Verify database smoke compatibility.

### 2C — protected admin REST read/write

**RED**
- Capability, bot ownership/existence, validation, no arbitrary runtime override.

**GREEN**
- Extend existing admin REST authority; do not add public write endpoint.
- Verify PHP + WordPress smoke where appropriate.

### 2D — public `WidgetConfig` projection

**RED**
- Public config includes only normalized display/localization fields and excludes raw admin/server/private values.

**GREEN**
- Extend `WidgetConfig`/resolver minimally.
- Verify no secrets/provider/model/embedding/vector config is exposed.
- Persist Task 2 evidence.

## Task 3 — WordPress server context fact projection

**Files**
- Create a focused frontend context resolver under `src/Frontend/`.
- Update `PublicWidgetBootstrap.php` only through the existing mount authority.
- Add unit/integration tests.

### 3A — page/post/auth/role facts

**RED**
- Current normalized path, post type, auth boolean, bounded role-match token/boolean behavior.
- Assert no user object/email/user ID/capabilities are serialized.

**GREEN**
- Resolve via WordPress server functions and project minimum browser-safe facts.

### 3B — Woo/site locale/timezone/direction facts

**RED**
- Woo area finite token when plugin/context exists; safe null otherwise.
- Site locale/timezone/direction fallback.

**GREEN**
- Optional Woo integration without hard dependency.
- Verify WordPress smoke and public output.
- Persist Task 3 evidence.

## Task 4 — runtime visibility integration

**Files**
- Update `src-js/widget-runtime.ts`
- Add focused `src-js/widget-display-rules.test.ts`

**RED**
- Ineligible mount remains unmounted/hidden without listeners or chat request.
- Eligible configuration preserves M14 floating/embedded/fullscreen behavior.
- Malformed optional M15 browser config fails to conservative M14-compatible behavior/no proactive action.

**GREEN**
- Invoke Task 1 pure evaluator before presentation behavior.
- Do not duplicate appearance/chat code.
- Verify surface regression suites and exact-head CI.
- Persist Task 4 evidence.

## Task 5 — proactive trigger coordinator

**Files**
- Create `src-js/widget-proactive.ts`
- Create `src-js/widget-proactive.test.ts`
- Update `widget-runtime.ts` only at one integration seam.

### 5A — delay and once-only lifecycle

**RED**
- bounded delay; fires once; no chat request; manual open cancels; dispose clears timer.

**GREEN**
- one coordinator, one timer maximum.

### 5B — scroll trigger

**RED**
- threshold crossing; no duplicate; listener cleanup; bounded calculation.

**GREEN**
- throttled/shared browser scheduling; one listener per mount.

### 5C — inactivity trigger

**RED**
- activity resets timer; bounded inactivity; cleanup.

**GREEN**
- one timer + bounded event set.

### 5D — exit intent

**RED**
- pointer-capable desktop only; top-boundary intent; once-only; keyboard/mobile not falsely triggered.

**GREEN**
- minimal event adapter.

### 5E — click selector + first visit

**RED**
- validated selector only; delegated match; storage marker; storage exception fallback; no cross-bot bleed.

**GREEN**
- safe matching/storage wrapper.
- Verify all JS gates; performance/security review; persist Task 5 evidence.

## Task 6 — page-specific starter suggestions

**Files**
- Add/extend runtime starter UI test and implementation using Task 1 selected starters.

**RED**
- matching suggestions render as native buttons/plain text;
- selecting starter uses existing input/submit path and does not bypass chat authority;
- no raw HTML execution; bounded list.

**GREEN**
- minimal adapter over existing M14 form submission.
- Verify accessibility and chat request count remains exactly one per submit.
- Persist evidence.

## Task 7 — admin rules editor + deterministic preview facts

**Files**
- Add admin editor module/tests following M14 `appearance-customizer.ts`/integration patterns.
- Update `src-js/index.ts` at existing bot edit route only.

**RED**
- load/edit/save isolation per bot; validation errors; stale response protection; preview uses same Task 1 evaluator; simulated preview facts not persisted as visitor facts.

**GREEN**
- reuse protected Task 2 REST authority and Task 1 evaluator.
- Verify keyboard labels and bounded native controls.
- Persist evidence.

## Task 8 — localization, RTL and accessibility hardening

### 8A — bounded message catalog

**RED**
- runtime labels resolve through one catalog; unsupported locale fallback; no raw translation HTML.

**GREEN**
- extract M14 hard-coded labels into catalog without behavior drift.

### 8B — `lang`/`dir` and RTL semantics

**RED**
- widget root/panel receives normalized lang/dir; RTL fixture does not reverse DOM reading/tab order; launcher/spacing behavior remains usable.

**GREEN**
- apply direction locally at widget boundary and CSS logical properties where needed.

### 8C — reduced motion/focus/screen reader regression

**RED**
- proactive prompt never steals focus; Escape/manual open cleanup; concise live status; reduced motion suppresses nonessential animation behavior.

**GREEN**
- minimal runtime/CSS changes; preserve M14 dialog semantics.
- Verify accessibility/mobile/performance review and persist evidence.

## Task 9 — integration, WordPress smoke, final reviews and merge gate

### 9A — real WordPress smoke

**Files**
- Extend `scripts/test-wp-widget-surfaces.sh` or add narrowly scoped M15 smoke script wired into permanent CI.

**Verify**
- URL rule visibility;
- server fact projection boundary;
- one proactive trigger without chat side effect;
- RTL root semantics;
- no provider secrets/config leakage.

### 9B — milestone review

Perform/request scoped:
- correctness review;
- security/privacy review;
- performance/listener cleanup review;
- accessibility/mobile/RTL review;
- architecture/duplication review.

Resolve every Critical and Important finding before proceeding. If independent reviewer transport is unavailable, use repository-approved fallback and record that limitation.

### 9C — durable closeout

Update:
- `docs/progress/STATUS.md`;
- `docs/milestones/M15-display-rules-rtl-accessibility.md`;
- Task evidence files;
- final closeout evidence.

Record exact RED/GREEN SHAs, CI runs, `NOT RED`/`NOT GREEN`, review findings/resolutions, exact final branch head.

### 9D — merge/post-merge

1. Verify exact-final-head permanent CI GREEN.
2. Recheck PR head, mergeability, review threads and concurrency.
3. Mark ready if draft.
4. Merge with expected-head protection.
5. Recover new `main` SHA.
6. Verify fresh post-merge `main` CI GREEN.
7. Mark M15 complete durably.
8. Recover M16 and continue during the same run if safe.

## Initial execution order

Start with Task 1A. Do not start PHP persistence before the pure evaluator contract is proven. Each sub-slice is a checkpoint, not a stopping condition.
