# M12 Admin Onboarding, Bot Management & Provider Configuration — Design

Status: AUTO-APPROVED — SCHEDULED MODE
Date: 2026-09-07
Milestone: M12

## Problem

The backend now has provider, credential, retrieval, orchestration, and persistence foundations, but administrators do not yet have a WordPress-native control plane for setup, bot management, or provider/model configuration. M12 must expose those capabilities without leaking provider secrets to JavaScript or pulling M13 knowledge/debugger, M14 appearance, or M21 analytics scope forward.

## Design constraints

- WordPress remains the authority for authentication, capabilities, nonces, REST routing, and persistence lifecycle.
- React/TypeScript is used for the admin application where it improves interaction quality; server-rendered PHP remains the bootstrap boundary.
- Provider credentials are write-only from the browser. Reads return only presence/source/masked metadata, never plaintext or reversible ciphertext.
- All mutating REST routes require both WordPress authentication/capability checks and nonce-protected REST requests.
- Bot records are independently addressable and validated so one bot cannot accidentally mutate another bot's settings.
- Model choices must be filtered by provider capabilities and purpose rather than presenting arbitrary strings as safe options.
- The public chatbot/widget asset path must not regress because of admin assets.

## Existing seams to preserve

M03 already supplies provider contracts, model catalogs, credential resolution/storage, authenticated encryption, runtime credential sources, and provider bootstrapping. M11 supplies chat orchestration and persistence contracts. M01 supplies the repository's frontend build/tooling conventions. M12 should wrap those seams rather than duplicate provider clients, encryption, or chat logic.

## Alternatives considered

### A. WordPress-native admin shell + granular REST resources + React screens — selected

A small PHP bootstrap registers the admin menu, capability boundary, assets, and REST controllers. REST resources expose bot metadata/configuration, provider configuration state, model catalogs, and onboarding readiness. React screens consume those resources.

Advantages: least privilege, testable resource boundaries, no secret serialization, clean progressive enhancement, and compatibility with future M13/M14 screens. It also keeps provider/domain logic server-side.

### B. One large settings REST endpoint and one settings blob

Simpler initially, but couples unrelated concerns, makes capability/validation errors coarse, increases accidental overwrite risk, and makes multi-bot isolation fragile. Rejected.

### C. Server-render all M12 screens in PHP

Avoids a JS admin bundle but makes onboarding state, model capability filtering, async validation, loading/error states, and later admin extensions significantly harder to maintain. Rejected for the interaction-heavy screens while retaining a minimal PHP bootstrap/fallback boundary.

## Selected architecture

### 1. Admin foundation

Create an `Admin` module registered from `Core\Bootstrap`. It owns:

- menu/page registration under a single plugin top-level page;
- the capability constant/policy used for M12 administration;
- admin-only asset enqueueing scoped to the plugin page;
- a bootstrap payload containing REST base URL, REST nonce, current screen, non-secret feature/readiness metadata, and versioned asset information;
- REST controller registration.

The bootstrap payload must never contain credentials, encrypted credential blobs, provider authorization headers, or chat content.

### 2. REST boundary

Use a versioned namespace and granular resources. Exact route naming may follow repository conventions, but behavior is grouped as:

- onboarding/readiness: read setup state and allowed next actions;
- bots: paginated list, create, read, update, delete/archive according to repository persistence policy;
- providers: read configuration state, write/replace/delete credential configuration, read supported capabilities;
- models: list normalized models/capabilities for a configured provider and requested purpose.

Every route has an explicit permission callback. Mutations validate and sanitize server-side. REST responses use stable error codes/messages suitable for accessible UI feedback without exposing secrets or upstream sensitive payloads.

### 3. Bot domain

Introduce a bot configuration aggregate/repository rather than storing the entire admin state in one WordPress option. A bot has a stable identifier, human name, enabled/status state, provider/model selections needed by current chat contracts, and timestamps/version metadata required for safe updates. Future knowledge bindings and appearance fields remain outside M12 unless already required by an existing backend contract.

Bot list endpoints are paginated from the start. Creation produces isolated records; update/delete operations are scoped to one identifier and reject missing/stale targets deterministically.

### 4. Provider configuration

Reuse M03 credential storage/resolution and model catalog contracts. Read responses report only configuration state such as configured/not configured, credential source, provider availability, and safe masked metadata where the existing credential abstraction permits it. Browser clients may submit a new secret, but the persisted plaintext value is never returned after the write.

Model catalogs are normalized server-side. The UI requests models for a purpose/capability and only allows compatible selections. Provider/capability unavailability is a first-class state, not an empty-success ambiguity.

### 5. Onboarding

Onboarding is a state machine derived from server truth rather than a client-only wizard flag. Minimum stages:

1. provider readiness/configuration;
2. compatible model selection;
3. first bot creation/configuration;
4. completion/entry into bot management.

Reloading the page reconstructs progress from persisted state. A missing crypto capability, unavailable provider, invalid credential, or empty compatible model catalog produces an actionable state without exposing secret data.

### 6. React admin application

The admin bundle uses typed API adapters and screen-level state. Initial screens:

- onboarding;
- bot list/empty state;
- bot create/edit form;
- provider configuration/model selection.

Loading, empty, validation, permission/error, and retry states are explicit. Keyboard navigation, focus placement after route/form errors, labels/descriptions, status announcements, and usable narrow/mobile WordPress admin layouts are mandatory acceptance conditions.

### 7. Security

- Capability check on page access and every REST route.
- WordPress REST nonce used for browser mutations.
- Server-side sanitization/validation for all text/enums/identifiers.
- Escaping at render boundaries.
- Credential reads never expose plaintext/ciphertext.
- Upstream provider errors are normalized before returning to JS.
- No secrets in localized bootstrap data, logs, query strings, or thrown client errors.
- Bot identifiers are authorization-scoped and cannot be used to cross-mutate unrelated records.

### 8. Performance

- Admin assets load only on plugin admin screens.
- Bot lists are paginated.
- Provider/model data is fetched on demand and may reuse existing safe cache abstractions.
- Screen bundles may be lazy where the current build supports it.
- No admin JavaScript/CSS is enqueued on public pages, preserving widget/frontend payloads.

## Test strategy

Backend unit/integration coverage must prove permissions, route registration, validation, bot isolation/persistence, provider secret write-only behavior, readiness state, and model capability filtering. Component tests cover onboarding and admin forms including loading/empty/error states and keyboard-accessible behavior. E2E/WordPress smoke covers menu/page boot, REST persistence, multiple bot isolation, provider unavailable states, and no public asset regression.

Strict TDD applies to each meaningful behavior: behavioral test first, observed expected RED, minimum implementation, focused GREEN, broader verification, then refactor.

## Planned task sequence

1. Admin foundation and capability-protected REST bootstrap.
2. Bot aggregate/repository and persistence migration as required.
3. Bot CRUD REST resources with pagination/isolation.
4. Provider credential/configuration REST resource with write-only secrets.
5. Provider model/capability/readiness resources.
6. React admin shell and typed API layer.
7. Onboarding flow.
8. Bot management screens.
9. Provider/model configuration screens.
10. Integration/E2E, security, accessibility, performance, review, durable closeout.

## Scope guardrails

M13 knowledge manager/debugger, M14 appearance editor, and M21 analytics remain out of scope. M12 may expose only identifiers/readiness data needed to navigate toward those future capabilities.

## Self-review

The design was checked for scope leakage, secret exposure, hidden client authority, unbounded bot listing, duplicate provider abstractions, unsupported analytics/appearance work, and testability. The selected approach is the smallest repository-consistent design that satisfies M12 acceptance criteria.

AUTO-APPROVED — SCHEDULED MODE
