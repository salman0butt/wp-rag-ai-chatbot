<?php
/**
 * Native runtime credential-source reader.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Credentials;

/**
 * Reads environment variables and PHP constants without persistence access.
 */
final class RuntimeCredentialSourceReader implements CredentialSourceReader {
	/**
	 * Read an environment variable.
	 *
	 * @param string $name Environment variable name.
	 */
	public function environment( string $name ): ?string {
		$value = getenv( $name );
		return false === $value ? null : $value;
	}

	/**
	 * Read a string PHP constant.
	 *
	 * @param string $name Constant name.
	 */
	public function constant( string $name ): ?string {
		if ( ! defined( $name ) ) {
			return null;
		}

		$value = constant( $name );
		return is_string( $value ) ? $value : null;
	}
}
