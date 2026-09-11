<?php
/**
 * Shared production vector-store composition tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\VectorStore;

use PHPUnit\Framework\TestCase;
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
}
