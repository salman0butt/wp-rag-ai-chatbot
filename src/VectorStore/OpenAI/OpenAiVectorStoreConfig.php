<?php
/**
 * OpenAI managed vector-store configuration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\OpenAI;

use InvalidArgumentException;

/**
 * Immutable server-side configuration for one OpenAI managed vector store.
 */
final class OpenAiVectorStoreConfig {
	/**
	 * @param string $api_key OpenAI server-side API key.
	 * @param string $vector_store_id OpenAI vector-store ID.
	 */
	public function __construct(
		public readonly string $api_key,
		public readonly string $vector_store_id
	) {
		if ( '' === $api_key || strlen( $api_key ) > 4096 || preg_match( '/[\r\n]/', $api_key ) ) {
			throw new InvalidArgumentException( 'OpenAI API key is invalid.' );
		}
		if ( 1 !== preg_match( '/^vs_[A-Za-z0-9_-]{1,191}$/', $vector_store_id ) ) {
			throw new InvalidArgumentException( 'OpenAI vector store ID is invalid.' );
		}
	}
}
