<?php
/**
 * M09 document synchronization worker composition tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\JobWorkerBootstrap;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobHandler;

/**
 * Proves the production worker registry explicitly allowlists document synchronization.
 */
final class JobWorkerBootstrapDocumentSyncTest extends TestCase {
	/**
	 * Production worker composition registers the stable index.document handler.
	 */
	public function test_default_registry_registers_document_index_handler(): void {
		$handler = JobWorkerBootstrap::handler_registry()->for_type( 'index.document' );

		self::assertInstanceOf( DocumentIndexJobHandler::class, $handler );
		self::assertSame( 'index.document', $handler->type() );
	}
}
