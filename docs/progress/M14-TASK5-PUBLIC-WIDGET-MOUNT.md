# M14 Task 5 — Conditional Public Widget Mount

Status: **COMPLETE**

## Scope

Task 5 establishes the bounded public mount seam for the M14 chatbot widget without introducing any new runtime authority.

The implementation:

- registers the `[wp_rag_ai_chatbot]` shortcode through the existing WordPress bootstrap;
- accepts only a bot identifier at the mount boundary;
- resolves the existing public-safe `WidgetConfig` projection;
- fails closed for missing, invalid, or disabled bots;
- emits no widget assets until a valid mount is rendered;
- conditionally enqueues the dedicated widget JavaScript and CSS bundle;
- exposes only the bot-scoped public configuration and REST base required by the browser widget;
- reuses the existing public chat REST/runtime path rather than duplicating provider, retrieval, generation, memory, citation, or persistence authority.

## Security and architecture boundary

The browser bootstrap contains only the already allow-listed `WidgetConfig` plus the public REST base. It does not expose provider credentials, provider/model selection, embedding/vector-store configuration, retrieval limits, or arbitrary runtime overrides.

Shortcode input cannot become a provider/model/credential/retrieval/vector override channel. Invalid and disabled bot mounts render nothing and enqueue nothing.

## TDD / implementation chronology

Task 5 was developed through the existing durable implementation plan at `docs/superpowers/plans/2026-09-12-m14-task5-public-widget-mount.md`.

Representative chronology:

- `24f50783d8cb59d466cb56f40d80e4e5891413f3` — test: define public widget mount boundary.
- `db4a009967e1ea70e32fbf01abdae4345d3c45f0` — test-only formatting repair; not an implementation GREEN.
- `d47ca564e90edd17ab26be5b48d7fc68b881837d` — feat: add closed public widget mount boundary.
- `dc5a06d212486de8ba3caa961da202d43d9d448f` — test: specify public widget shortcode bootstrap.
- `edbd492809250b361fb1697a52d8ab974c73cd15` — test fixture repair.
- `dfc4a08c78667a55993100f766093f3351ed3a9b` — feat: add conditional public widget shortcode bootstrap.
- `aadddbd2a79c5ce342072a4652e1fb180d464b30` — test: specify public widget core hook composition.
- `329102b868c60a60fb2c2247dc379dab6be77001` — feat: wire public widget bootstrap into WordPress.
- `3ba67f02986e85b3f89443f40a00c9eeaf26a0fb` — test: require public widget bundle in package; CI `34721718389` failed at the intended package boundary before the dedicated bundle existed.
- `5d84f5a4be17778ee13bcfb1513e791105722e89` — feat: package dedicated public widget entry; CI `34721889298` was **NOT GREEN**.
- `9619f53cc7130dacbb835f54652ff46f412442f4` — fix: preserve stable widget bundle names; CI `34721951718` GREEN across all permanent jobs. This is the verified production implementation head before the additional real-WordPress Task 5 smoke assertions below.

## Real WordPress integration verification

A real WordPress smoke extension was added after the production implementation to prove the integration seam end to end.

### NOT RED — representation-only smoke assertion

- SHA: `a1e0ad17905aba666444ce45c8f4db57db834272`
- CI: `34722293078`
- `php-quality`, `js-quality`, and `package` passed.
- `wordpress-smoke` reached the new widget assertions but failed on `Public widget bootstrap data is missing the public bot identity or REST base.`
- Root cause: the assertion searched only the literal `wp-rag-ai-chatbot/v1` representation while `wp_json_encode()` may encode the slash as `\/` inside the inline JavaScript JSON.
- No production behavior was missing, so this checkpoint is explicitly **NOT RED**.

### Corrected integration verification — GREEN

- SHA: `18436c394f826e83885cbb43bff30b509a00a205`
- CI: `34722486144`
- `php-quality`: GREEN.
- `js-quality`: GREEN.
- `package`: GREEN.
- `wordpress-smoke`: GREEN, including the Task 5 public widget mount assertions.

The real WordPress smoke verifies:

- shortcode registration after plugin activation;
- no eager public widget assets before a valid mount;
- missing bot identifier fails closed without assets;
- disabled bot fails closed through both public REST and shortcode mount paths;
- enabled bot renders the deterministic widget mount;
- enabled mount conditionally enqueues the dedicated script/style assets;
- browser bootstrap includes the public bot identity and REST base;
- distinctive persisted provider/model values and forbidden runtime-authority markers are absent from the browser bootstrap.

Because the final smoke correction changed only the assertion representation and not production behavior, it is recorded as verification GREEN rather than being relabeled as a new behavioral RED→GREEN cycle.

## Scoped review

Independent reviewer transport was unavailable for this bounded unit, so no independent-review claim is made. The repository-approved scoped fallback review found **0 Critical / 0 Important unresolved** findings.

- Correctness: invalid/disabled mounts fail closed; valid mounts resolve the established public-safe config and enqueue exactly the dedicated widget assets.
- Security: request/mount input cannot inject runtime authority; browser bootstrap is allow-listed and real-WordPress smoke checks for representative secret/runtime leakage.
- Performance: assets are not enqueued globally and are loaded only when a valid shortcode mount is rendered; enqueue logic is idempotent.
- Accessibility: Task 5 introduces only the mount/bootstrap shell; interactive accessibility becomes a required gate in Task 6.
- Architecture/duplication: the mount reuses `WidgetConfigResolver`, the established public REST endpoint, and the M10/M11 production runtime rather than introducing a parallel chatbot/RAG path.

## Exact next unfinished work

M14 Task 6 — floating launcher/panel UI. Build the interactive browser widget on top of the dedicated `src-js/widget.ts` entry and the Task 5 bootstrap contract. Start with strict JavaScript TDD for deterministic mount discovery, launcher/panel open-close behavior, public appearance projection, and keyboard/accessibility semantics. Do not add a second chat/retrieval/runtime authority.