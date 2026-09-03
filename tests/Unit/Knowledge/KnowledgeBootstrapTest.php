<?php
/**
 * Knowledge bootstrap tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Knowledge;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Knowledge\KnowledgeBootstrap;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSource;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceException;
use WpRagAiChatbot\Knowledge\Sources\WooCommerceProductSource;

/**
 * Verifies knowledge-source runtime composition.
 */
final class KnowledgeBootstrapTest extends TestCase {
	/**
	 * Start Brain Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Reset static bootstrap state and Brain Monkey after each test.
	 */
	protected function tearDown(): void {
		if ( class_exists( KnowledgeBootstrap::class ) ) {
			$reflection = new ReflectionClass( KnowledgeBootstrap::class );
			if ( $reflection->hasProperty( 'registry' ) ) {
				$reflection->getProperty( 'registry' )->setValue( null, null );
			}
		}

		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Native sources and valid third-party sources are registered together.
	 */
	public function test_register_builds_default_registry_and_applies_extension_filter(): void {
		$extension = new class() implements KnowledgeSource {
			/**
			 * Return a test source type.
			 */
			public function type(): string {
				return 'third_party';
			}

			/**
			 * Return no documents for this registry-only test double.
			 *
			 * @param KnowledgeSourceRecord $source Persisted source.
			 * @return iterable<int, DocumentRecord>
			 */
			public function documents( KnowledgeSourceRecord $source ): iterable {
				unset( $source );

				return array();
			}
		};

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'wp_rag_ai_chatbot_knowledge_sources', array() )
			->andReturn( array( $extension ) );

		KnowledgeBootstrap::register();

		$registry = KnowledgeBootstrap::registry();

		self::assertSame( array( 'faq', 'file', 'manual_text', 'third_party', 'woocommerce_product', 'wordpress_posts' ), $registry->types() );
		self::assertInstanceOf( WooCommerceProductSource::class, $registry->get( 'woocommerce_product' ) );
		self::assertSame( $extension, $registry->get( 'third_party' ) );
	}

	/**
	 * Filter additions must honor the explicit KnowledgeSource contract.
	 */
	public function test_register_rejects_non_source_filter_values(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'wp_rag_ai_chatbot_knowledge_sources', array() )
			->andReturn( array( new stdClass() ) );

		$this->expectException( KnowledgeSourceException::class );

		KnowledgeBootstrap::register();
	}
}
