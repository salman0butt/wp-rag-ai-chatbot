<?php
/**
 * Public production chat responder composition tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WpRagAiChatbot\Admin\Rest\PlaygroundGenerationProviderResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundHybridRetrieverResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundSemanticConfigurationResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundSemanticRetrieverResolver;
use WpRagAiChatbot\Chat\ProductionChatResponderFactory;
use WpRagAiChatbot\Conversations\MessageRepository;
use WpRagAiChatbot\Frontend\PublicChatKnowledgeSourceResolver;
use WpRagAiChatbot\Frontend\PublicChatRuntime;

/**
 * Specifies the public adapter around the existing production RAG composition authorities.
 */
final class PublicChatResponderResolverTest extends TestCase {
	/**
	 * Public composition must depend on existing production authorities rather than duplicate RAG behavior.
	 */
	public function test_public_responder_resolver_contract_reuses_existing_authorities(): void {
		$class = new ReflectionClass( 'WpRagAiChatbot\\Frontend\\PublicChatResponderResolver' );

		$constructor = $class->getConstructor();
		self::assertNotNull( $constructor );
		self::assertSame(
			array(
				PublicChatKnowledgeSourceResolver::class,
				PlaygroundSemanticConfigurationResolver::class,
				PlaygroundSemanticRetrieverResolver::class,
				PlaygroundHybridRetrieverResolver::class,
				PlaygroundGenerationProviderResolver::class,
				ProductionChatResponderFactory::class,
				MessageRepository::class,
			),
			array_map(
				static fn ( \ReflectionParameter $parameter ): string => (string) $parameter->getType(),
				$constructor->getParameters()
			)
		);

		$resolve = $class->getMethod( 'resolve' );
		self::assertSame(
			array( PublicChatRuntime::class ),
			array_map(
				static fn ( \ReflectionParameter $parameter ): string => (string) $parameter->getType(),
				$resolve->getParameters()
			)
		);
		self::assertSame( 'WpRagAiChatbot\\Chat\\ChatResponder', (string) $resolve->getReturnType() );
	}
}
