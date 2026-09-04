<?php
/**
 * Vector collection identity.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

use InvalidArgumentException;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;

/**
 * Immutable collection boundary and compatibility profile.
 */
final class VectorCollection {
	/**
	 * Create a collection boundary.
	 *
	 * @param string             $id Stable collection ID.
	 * @param VectorIndexProfile $profile Compatibility profile.
	 * @throws InvalidArgumentException When the ID is invalid.
	 */
	public function __construct(
		public readonly string $id,
		public readonly VectorIndexProfile $profile
	) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $id ) ) {
			throw new InvalidArgumentException( 'Vector collection ID is invalid.' );
		}
	}
}
