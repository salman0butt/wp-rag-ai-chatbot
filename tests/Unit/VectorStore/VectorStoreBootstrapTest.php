<?php
/**
 * Shared production vector-store composition tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\VectorStore;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\VectorStore\Local\LocalVectorStoreConfig;
use WpRagAiChatbot\VectorStore\VectorStoreBootstrap;
use WpRagAiChatbot\VectorStore\VectorStoreRegistry;

/**
 * Keeps Playground and production indexing on one vector-store registry authority.
 */
final class VectorStoreBootstrapTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$this->reset_bootstrap();
	}

	protected function tearDown(): void {
		$this->reset_bootstrap();
		parent::tearDown();
	}

	/** Production runtime must expose one shared registry composition root. */
	public function test_exposes_shared_production_registry_authority(): void {
		self::assertTrue( class_exists( VectorStoreBootstrap::class ) );
		self::assertTrue( method_exists( VectorStoreBootstrap::class, 'registry' ) );

		$method = new \ReflectionMethod( VectorStoreBootstrap::class, 'registry' );
		self::assertTrue( $method->isStatic() );
		self::assertSame( VectorStoreRegistry::class, (string) $method->getReturnType() );
	}

	/** Production runtime must register the local WordPress adapter through the shared authority. */
	public function test_exposes_local_wordpress_registration_seam(): void {
		self::assertTrue( method_exists( VectorStoreBootstrap::class, 'register_local' ) );

		$method = new \ReflectionMethod( VectorStoreBootstrap::class, 'register_local' );
		self::assertTrue( $method->isStatic() );
		self::assertSame( 'void', (string) $method->getReturnType() );

		$parameters = $method->getParameters();
		self::assertCount( 3, $parameters );
		self::assertSame( Connection::class, (string) $parameters[0]->getType() );
		self::assertSame( TableNames::class, (string) $parameters[1]->getType() );
		self::assertSame( LocalVectorStoreConfig::class, (string) $parameters[2]->getType() );
	}

	/** A long-lived PHP worker must not reuse one site's local adapter for another site. */
	public function test_local_registration_follows_current_site_table_authority(): void {
		$config = new LocalVectorStoreConfig( 100, 20 );

		VectorStoreBootstrap::register_local(
			$this->createMock( Connection::class ),
			new TableNames( 'wp_' ),
			$config
		);
		VectorStoreBootstrap::register_local(
			$this->createMock( Connection::class ),
			new TableNames( 'wp_2_' ),
			$config
		);

		$store  = VectorStoreBootstrap::registry()->get( 'local-wordpress' );
		$tables = new ReflectionProperty( $store, 'tables' );
		$value  = $tables->getValue( $store );

		self::assertInstanceOf( TableNames::class, $value );
		self::assertSame( 'wp_2_rag_ai_vectors', $value->vectors() );
	}

	/** Reset process-local bootstrap state so tests remain isolated. */
	private function reset_bootstrap(): void {
		$registry = new ReflectionProperty( VectorStoreBootstrap::class, 'registry' );
		$registry->setValue( null, null );

		$registered = new ReflectionProperty( VectorStoreBootstrap::class, 'local_registered' );
		$registered->setValue( null, false );
	}
}
