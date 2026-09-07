# M12 Task 4 — Provider credential/configuration REST resource

Status: **COMPLETE**

## Goal
Allow WordPress administrators to configure direct-provider credentials without ever reading plaintext or ciphertext back into JavaScript.

## TDD evidence
- Rejected pre-RED `8cd6acc1605e8088afa6679d3933a10bc0add8d4`, CI `34102710453`: PHPCS stopped before PHPUnit, so this is not behavioral RED evidence.
- Genuine core RED `26ce3eefef6dd7d5198b77a669268bc07cbcafbf`, CI `34102834048`: PHPStan passed all 280 files; PHPUnit executed 658 tests / 2,729 assertions and failed exactly five Task 4 cases because `ProviderCredentialRestResource` did not exist.
- Core implementation `311ce0be96c451dadf02d15a586c293d47039aec` added the minimum write-only credential resource over the established M03 seams.
- Genuine REST-route RED `4ff5916c809cf104d7e753a2d5531837ef093a49`, CI `34103192408`: PHPStan passed all 281 files; PHPUnit executed 659 tests / 2,743 assertions and failed exactly because `/admin/providers/(?P<provider_id>[^/]+)/credential` was not registered.
- Route implementation `86969f6bb6e2c6bbdc1908f5a811b10e9eed9c51` composed the existing M03 runtime source reader, authenticated cipher, WordPress credential store, and centralized admin capability.
- Verification-only corrections `5804902fcd06df7f5cb5a581fdad59100472b920`, `8485901c283ca2d3b54aaf96c7e80ff5dbede103`, and `759db1cc1c20ccebb906fc03cc51d1434dc6110a` resolved a WordPress-stub static-type mismatch and made Brain Monkey REST-route expectations order-independent without broadening Task 4 behavior.

## Implemented behavior
- `GET /wp-rag-ai-chatbot/v1/admin/providers/{provider_id}/credential` resolves the effective M03 credential source and returns only `configured` and `source` metadata.
- `PUT` accepts a non-empty `credential`, validates the direct provider, and saves through the existing encrypted `CredentialStore` path. It returns no credential value.
- `DELETE` removes only the plugin-managed credential. Environment and WordPress-constant credentials remain untouched.
- GET/PUT/DELETE all use `AdminCapability::can_manage` as the WordPress REST permission callback.
- Unsupported provider IDs, malformed credential payloads, and upstream failures produce stable, secret-free error responses.

## Security checkpoint
- No plaintext credential is returned by REST.
- No encrypted/ciphertext credential is returned by REST.
- No credential is localized into JavaScript by Task 4.
- No credential logging was added.
- Exception text is never returned; upstream failures collapse to `credential_operation_failed` with a constant safe message.
- M03 `AuthenticatedCredentialCipher`, `WordPressCredentialStore`, `CredentialResolver`, and runtime credential-source contracts are reused; no parallel encryption/storage path exists.
- Environment -> constant -> encrypted-option precedence remains owned by the M03 resolver.

## Performance checkpoint
Credential resolution/storage occurs only when the protected provider credential route is invoked. Task 4 adds no public widget/request work, frontend asset, or provider network call.

## Verification
Exact implementation head `759db1cc1c20ccebb906fc03cc51d1434dc6110a`, CI `34103975996`:
- `php-quality`: GREEN
- `js-quality`: GREEN
- `package`: GREEN
- `wordpress-smoke`: GREEN, including activation, database lifecycle, providers, knowledge, file ingestion, WooCommerce knowledge, and cleanup.

## Review
Scoped correctness/security/performance review `5130113628`, anchored to `759db1cc1c20ccebb906fc03cc51d1434dc6110a`: **0 Critical / 0 Important findings**.

## Handoff
Task 5 is next: model capability and onboarding-readiness resources. Begin with behavioral tests that distinguish unavailable provider, missing credential, and unsupported capability; normalize/filter models by purpose/capability; and derive onboarding readiness only from persisted server truth. Reuse M03 model catalog/provider capability contracts and Task 2 bot state.
