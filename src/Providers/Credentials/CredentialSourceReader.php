<?php
/**
 * Runtime provider credential-source reader contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Credentials;

/**
 * Reads server-owned runtime credential sources.
 */
interface CredentialSourceReader {
	/**
	 * Read an environment variable as a string when available.
	 *
	 * @param string $name Environment variable name.
	 */
	public function environment( string $name ): ?string;

	/**
	 * Read a PHP constant only when its value is a string.
	 *
	 * @param string $name Constant name.
	 */
	public function constant( string $name ): ?string;
}
