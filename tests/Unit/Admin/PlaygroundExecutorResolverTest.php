<?php
/**
 * Playground production-executor composition tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use WpRagAiChatbot\Admin\Rest\PlaygroundAccessContextResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundChatGraphResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundGenerationProviderResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundHybridRetrieverResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundSemanticConfigurationResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundSemanticRetrieverResolver;
use WpRagAiChatbot\Admin\Rest\ProductionPlaygroundExecutor;

/**
 * Specifies the request-local composition boundary for the production Playground executor.
 */
final class PlaygroundExecutorResolverTest extends TestCase {
	/**
	 * The resolver contract must compose only the already-established production authorities.
	 */
	public function test_executor_resolver_contract_uses_existing_production_resolvers(): void {
		$resolver_class = 'WpRagAiChatbot\\Admin\\Rest\\PlaygroundExecutorResolver';
		self::assertTrue( class_exists( $resolver_class ), 'PlaygroundExecutorResolver is missing.' );

		$constructor = ( new ReflectionClass( $resolver_class ) )->getConstructor();
		self::assertNotNull( $constructor );
		self::assertSame(
			array(
				PlaygroundSemanticConfigurationResolver::class,
				PlaygroundSemanticRetrieverResolver::class,
				PlaygroundHybridRetrieverResolver::class,
				PlaygroundGenerationProviderResolver::class,
				PlaygroundAccessContextResolver::class,
				PlaygroundChatGraphResolver::class,
			),
			array_map(
				static fn ( \ReflectionParameter $parameter ): string => (string) $parameter->getType(),
				$constructor->getParameters()
			)
		);

		$resolve = new ReflectionMethod( $resolver_class, 'resolve' );
		self::assertSame( 'WpRagAiChatbot\\Admin\\Rest\\PlaygroundConfiguration', (string) $resolve->getParameters()[0]->getType() );
		self::assertSame( ProductionPlaygroundExecutor::class, (string) $resolve->getReturnType() );
	}
}
