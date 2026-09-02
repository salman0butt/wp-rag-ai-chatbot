<?php
/**
 * Knowledge source registry tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Knowledge\Sources;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSource;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceException;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceRegistry;

/**
 * Verifies deterministic source registration and lookup.
 */
final class KnowledgeSourceRegistryTest extends TestCase {
	/**
	 * Registered sources are addressable by their stable type IDs.
	 */
	public function test_registers_and_returns_sources_with_sorted_types(): void {
		$this->requireProductionTypes();

		$registry = new KnowledgeSourceRegistry();
		$faq      = $this->source( 'faq' );
		$manual   = $this->source( 'manual_text' );

		$registry->register( $manual );
		$registry->register( $faq );

		self::assertTrue( $registry->has( 'manual_text' ) );
		self::assertSame( $manual, $registry->get( 'manual_text' ) );
		self::assertSame( array( 'faq', 'manual_text' ), $registry->types() );
	}

	/**
	 * Duplicate source type IDs are rejected rather than overwritten.
	 */
	public function test_rejects_duplicate_source_types(): void {
		$this->requireProductionTypes();

		$registry = new KnowledgeSourceRegistry();
		$registry->register( $this->source( 'faq' ) );

		$this->expectException( KnowledgeSourceException::class );
		$registry->register( $this->source( 'faq' ) );
	}

	/**
	 * Empty source type IDs are invalid registry keys.
	 */
	public function test_rejects_empty_source_type(): void {
		$this->requireProductionTypes();

		$registry = new KnowledgeSourceRegistry();

		$this->expectException( KnowledgeSourceException::class );
		$registry->register( $this->source( '' ) );
	}

	/**
	 * Missing source lookups fail closed.
	 */
	public function test_rejects_unknown_source_type_lookup(): void {
		$this->requireProductionTypes();

		$registry = new KnowledgeSourceRegistry();

		$this->expectException( KnowledgeSourceException::class );
		$registry->get( 'missing' );
	}

	/**
	 * Build a deterministic source double that exercises the real contract.
	 */
	private function source( string $type ): KnowledgeSource {
		return new class( $type ) implements KnowledgeSource {
			public function __construct( private readonly string $sourceType ) {
			}

			public function type(): string {
				return $this->sourceType;
			}

			public function documents( KnowledgeSourceRecord $source ): iterable {
				return array();
			}
		};
	}

	/**
	 * Fail as a PHPUnit assertion while the test-first production types do not exist.
	 */
	private function requireProductionTypes(): void {
		if ( ! interface_exists( KnowledgeSource::class ) ) {
			self::fail( 'KnowledgeSource interface does not exist yet.' );
		}
		if ( ! class_exists( KnowledgeSourceRegistry::class ) ) {
			self::fail( 'KnowledgeSourceRegistry class does not exist yet.' );
		}
		if ( ! class_exists( KnowledgeSourceException::class ) ) {
			self::fail( 'KnowledgeSourceException class does not exist yet.' );
		}
	}
}
