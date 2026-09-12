# M13 Task 7 — Playground admin navigation evidence

Status: **IN PROGRESS**

This checkpoint adds the Playground as a first-class route in the existing M12 admin shell without adding any client-side retrieval, ranking, provider, credential, or RAG authority.

## Scope

- Register `playground` in the existing `AdminScreen` route authority.
- Add a `Playground` link to the existing accessible Administration navigation.
- Preserve the existing unknown-route fallback to Onboarding.
- Keep Playground submission/runtime wiring for a separate strict-TDD unit.

## TDD chronology

### NOT RED — formatting stopped before Jest

- SHA: `c2987d20c3b1f00c4746f21f4875fed30092841a`
- CI: `34672805427`
- Result: **NOT RED**
- Reason: Prettier rejected the test-only `AdminScreen` union before typecheck/Jest reached the intended missing-navigation behavior.

### Genuine RED

- SHA: `6105c9495e1e707a80b4e62b3ddbd2e764ab6713`
- CI: `34672864882`
- Result: **RED**
- Evidence: lint and TypeScript typecheck passed; 28 existing Jest suites passed; only the new `src-js/index.test.ts` assertions failed because `#/playground` was absent from navigation, `playground` resolved to `onboarding`, and no selected Playground nav item existed.

### Implementation transport checkpoint

- SHA: `aaf6eaa448f4e7e5d6efcb2b5e8612d722c3ad6b`
- Change: added only the `playground` `AdminScreen` member and `ADMIN_SCREENS` entry.
- CI: `34673031210`
- Result: **NOT GREEN**
- Reason: GitHub reported `action_required` with no runnable jobs for the bot-authored transport commit, so it cannot serve as GREEN evidence.

The one-shot transport workflow used to apply the two exact source edits removed itself in the same implementation commit and is not present in the current tree.

## Architecture / security review

- No request schema, credential, provider, embedding, vector-store, retrieval, scoring, fusion, reranking, grounding, prompt, memory, citation, or generation authority was added.
- The browser still owns navigation/rendering only.
- Unknown hashes continue to fail safely to the existing Onboarding screen.
- No arbitrary backend/provider message rendering was introduced.

## Remaining Task 7 work

After exact-head GREEN is established for this checkpoint:

1. Add a separate RED for the labelled Playground request form and bounded `bot_id`, positive `source_id`, `collection_id`, and question draft.
2. Submit only through the existing same-origin nonce-authenticated M12 admin client to `/admin/debug/playground`.
3. Add live pending/error/result state with latest-request-wins behavior.
4. Harden long diagnostic content, keyboard access, responsive layout, and final accessibility/security/performance review.
