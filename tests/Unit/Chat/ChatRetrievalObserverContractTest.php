<?php
/**
 * Retrieval observer contract tests for M13 playground diagnostics.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Chat;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WpRagAiChatbot\Retrieval\RetrievalResult;

final class ChatRetrievalObserverContractTest extends TestCase {
	public function test_chat_orchestrator_exposes_optional_retrieval_observer_seam(): void {
		$observer_class = 'WpRagAiChatbot\\Chat\\ChatRetrievalObserver';

		self::assertTrue( interface_exists( $observer_class ), 'ChatRetrievalObserver contract is missing.' );

		$reflection = new ReflectionClass( 'WpRagAiChatbot\\Chat\\ChatOrchestrator' );
		$constructor = $reflection->getConstructor();
		self::assertNotNull( $constructor );
		$parameters = $constructor->getParameters();
		$observer = $parameters[ count( $parameters ) - 1 ];

		self::assertSame( 'retrieval_observer', $observer->getName() );
		self::assertTrue( $observer->isDefaultValueAvailable() );
		self::assertNull( $observer->getDefaultValue() );
		self::assertSame( '?' . $observer_class, (string) $observer->getType() );
	}

	public function test_retrieval_observer_accepts_the_production_retrieval_result(): void {
		$observer_class = 'WpRagAiChatbot\\Chat\\ChatRetrievalObserver';
		self::assertTrue( interface_exists( $observer_class ), 'ChatRetrievalObserver contract is missing.' );

		$method = ( new ReflectionClass( $observer_class ) )->getMethod( 'observe' );
		self::assertSame( 'void', (string) $method->getReturnType() );
		self::assertSame( RetrievalResult::class, (string) $method->getParameters()[0]->getType() );
	}
}
