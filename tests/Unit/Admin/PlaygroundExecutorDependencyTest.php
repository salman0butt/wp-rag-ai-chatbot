<?php
/**
 * M13 Playground executor dependency contract test.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WpRagAiChatbot\Admin\Rest\PlaygroundExecutor;

/**
 * Prevents the REST boundary from accepting arbitrary closure return values.
 */
final class PlaygroundExecutorDependencyTest extends TestCase {
	/** PlaygroundRestResource must depend on the typed production executor contract. */
	public function test_resource_constructor_requires_playground_executor(): void {
		$class = new ReflectionClass( 'WpRagAiChatbot\\Admin\\Rest\\PlaygroundRestResource' );
		$constructor = $class->getConstructor();
		self::assertNotNull( $constructor );
		$parameters = $constructor->getParameters();
		self::assertCount( 1, $parameters );
		$type = $parameters[0]->getType();
		self::assertNotNull( $type );

		self::assertSame( PlaygroundExecutor::class, (string) $type );
	}
}
