<?php
/**
 * Knowledge source registry.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Knowledge\Sources;

/**
 * Stores knowledge source implementations by stable type identifier.
 */
final class KnowledgeSourceRegistry {
	/**
	 * Registered sources keyed by source type.
	 *
	 * @var array<string, KnowledgeSource>
	 */
	private array $sources = array();

	/**
	 * Register one knowledge source implementation.
	 *
	 * @param KnowledgeSource $source Source implementation.
	 */
	public function register( KnowledgeSource $source ): void {
		$type = trim( $source->type() );

		if ( '' === $type ) {
			throw new KnowledgeSourceException( 'Knowledge source type must not be empty.' );
		}
		if ( isset( $this->sources[ $type ] ) ) {
			throw new KnowledgeSourceException( 'Knowledge source type is already registered: ' . $type );
		}

		$this->sources[ $type ] = $source;
	}

	/**
	 * Return whether a source type is registered.
	 *
	 * @param string $type Source type.
	 */
	public function has( string $type ): bool {
		return isset( $this->sources[ $type ] );
	}

	/**
	 * Return the source registered for a type.
	 *
	 * @param string $type Source type.
	 */
	public function get( string $type ): KnowledgeSource {
		if ( ! isset( $this->sources[ $type ] ) ) {
			throw new KnowledgeSourceException( 'Knowledge source type is not registered: ' . $type );
		}

		return $this->sources[ $type ];
	}

	/**
	 * Return registered source type identifiers in deterministic order.
	 *
	 * @return list<string>
	 */
	public function types(): array {
		$types = array_keys( $this->sources );
		sort( $types, SORT_STRING );

		return $types;
	}
}
