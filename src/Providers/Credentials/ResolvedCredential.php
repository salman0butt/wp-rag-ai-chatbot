<?php
/**
 * Resolved provider credential.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Credentials;

/**
 * Couples a server-only secret with the source that supplied it.
 */
final readonly class ResolvedCredential {
	/**
	 * Create a resolved credential.
	 *
	 * @param Secret           $secret Resolved secret.
	 * @param CredentialSource $source Credential source.
	 */
	public function __construct(
		public Secret $secret,
		public CredentialSource $source
	) {
	}
}
