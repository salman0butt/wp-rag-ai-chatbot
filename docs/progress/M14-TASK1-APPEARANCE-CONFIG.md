# M14 Task 1 — Shared Appearance Configuration

Status: **COMPLETE**

## Scope

Task 1 establishes one immutable, browser-safe appearance configuration contract shared by the future public runtime and administrator preview.

The bounded schema currently includes:

- `primary_color` — normalized six-digit hexadecimal color;
- `color_mode` — `light|dark|system`;
- `position` — `bottom-left|bottom-right`;
- `launcher_style` — `bubble|icon|text`;
- `panel_size` — `small|medium|large`;
- `radius_px` — integer from 0 through 32;
- `font_family` — `system|sans|serif|mono`.

Unknown keys and unsafe values fail closed. The contract accepts no raw CSS, HTML, provider/runtime configuration, credentials, model overrides, embedding/vector-store configuration, or retrieval controls.

## TDD evidence

### Genuine RED

- Test-only SHA: `709c64f2c4817fd85408753644c58db4dbf7f551`
- CI run: `34683797716`
- PHP static analysis completed successfully before PHPUnit.
- PHPUnit executed 753 tests / 3,151 assertions and reached five intended `AppearanceConfig`-missing failures in `AppearanceConfigTest`.
- This is a genuine behavioral RED.

### NOT GREEN checkpoints

- `90294757d82c185c244107c4c0f6403bf89aea02` — initial production implementation. CI `34683852562` stopped in PHPCS before PHPStan/PHPUnit; therefore **NOT GREEN**.
- `5f9034164dd94d3fef7835b29a9c739d4302a7da` — first standards repair. CI `34684237497` reported 21 PHPCS errors in `AppearanceConfig.php`; therefore **NOT GREEN**.

The history is preserved as-is. No failed quality checkpoint is relabeled as GREEN.

### Genuine GREEN

- Implementation SHA: `e9dac2b506747085def72c560088c0d87b1afeb3`
- CI run: `34684305472`
- `php-quality`: GREEN, including Composer validation, PHPCS, PHPStan, PHPUnit, and Composer audit.
- `js-quality`: GREEN.
- `package`: GREEN.
- `wordpress-smoke`: GREEN, including activation, database, providers, knowledge, file ingestion, WooCommerce knowledge, and Playground REST smoke.

## Scoped review

Fallback correctness/security/performance/architecture review found **0 Critical / 0 Important unresolved** findings for this bounded unit.

- Correctness: deterministic defaults and explicit allow-listed normalization are covered by unit tests.
- Security: arbitrary keys, unsafe color strings, and out-of-range numeric CSS input are rejected; no unrestricted CSS/HTML/runtime configuration surface is introduced.
- Performance: validation is bounded local scalar/array work with no I/O.
- Architecture: runtime and customizer can consume the same normalized projection instead of creating parallel appearance rules.

Independent reviewer transport was not available for this subunit, so no independent-review claim is made.

## Next unfinished work

M14 Task 2: add bot-scoped appearance persistence and a public-safe widget configuration projection using existing bot persistence authority. Start with the smallest repository-consistent persistence/projection contract under a new genuine RED. Do not expose credentials, provider/model selection, embedding/vector-store configuration, retrieval limits, or unrestricted bot records to the public browser contract.
