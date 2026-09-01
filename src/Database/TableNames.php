<?php
/**
 * Database table names.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

/**
 * Derives per-site plugin table names from the WordPress table prefix.
 */
final class TableNames {
	/**
	 * @param string $prefix Current WordPress site prefix.
	 */
	public function __construct( private readonly string $prefix ) {
	}

	/**
	 * Sources table.
	 */
	public function sources(): string {
		return $this->prefix . 'rag_ai_sources';
	}

	/**
	 * Documents table.
	 */
	public function documents(): string {
		return $this->prefix . 'rag_ai_documents';
	}

	/**
	 * All tables introduced by M02 in safe deletion order.
	 *
	 * @return string[]
	 */
	public function all(): array {
		return array( $this->documents(), $this->sources() );
	}
}
