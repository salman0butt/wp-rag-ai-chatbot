<?php
/**
 * Public chat production runtime bootstrap tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Frontend\PublicChatProductionExecutorResolver;
use WpRagAiChatbot\Frontend\PublicChatRuntimeBootstrap;

/**
 * Specifies the public composition root around existing production RAG authorities.
 */
final class PublicChatRuntimeBootstrapTest extends TestCase {
	/** The public bootstrap accepts only canonical database authorities and returns the existing executor resolver. */
	public function test_executor_resolver_contract_uses_existing_runtime_authorities(): void {
		$class  = new ReflectionClass( PublicChatRuntimeBootstrap::class );
		$method = $class->getMethod( 'executor_resolver' );

		self::assertTrue( $method->isStatic() );
		self::assertSame(
			array( Connection::class, TableNames::class ),
			array_map(
				static fn ( \ReflectionParameter $parameter ): string => (string) $parameter->getType(),
				$method->getParameters()
			)
		);
		self::assertSame( PublicChatProductionExecutorResolver::class, (string) $method->getReturnType() );
	}
}
