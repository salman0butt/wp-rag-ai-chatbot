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
	 * Create the table-name resolver.
	 *
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
	 * Local vector collections table.
	 */
	public function vector_collections(): string {
		return $this->prefix . 'rag_ai_vector_collections';
	}

	/**
	 * Local vectors table.
	 */
	public function vectors(): string {
		return $this->prefix . 'rag_ai_vectors';
	}

	/**
	 * Persisted jobs table.
	 */
	public function jobs(): string {
		return $this->prefix . 'rag_ai_jobs';
	}

	/**
	 * All plugin tables in safe deletion order.
	 *
	 * @return string[]
	 */
	public function all(): array {
		return array( $this->jobs(), $this->vectors(), $this->vector_collections(), $this->documents(), $this->sources() );
	}
}
