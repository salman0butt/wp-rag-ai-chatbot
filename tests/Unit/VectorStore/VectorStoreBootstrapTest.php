<?php
/**
 * Shared production vector-store composition tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\VectorStore;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\VectorStore\Local\LocalVectorStoreConfig;
use WpRagAiChatbot\VectorStore\VectorStoreBootstrap;
use WpRagAiChatbot\VectorStore\VectorStoreRegistry;

/**
 * Keeps Playground and production indexing on one vector-store registry authority.
 */
final class VectorStoreBootstrapTest extends TestCase {
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
}
