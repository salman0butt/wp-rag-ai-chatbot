<?php
/**
 * M09 document synchronization worker composition tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Documents\DocumentRepository;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobWorkerBootstrap;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexDependencies;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobHandler;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobEnqueuer;
use WpRagAiChatbot\Jobs\Sync\KnowledgeSourceSyncJobHandler;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceRegistry;

/**
 * Proves the production worker registry explicitly allowlists document synchronization.
 */
final class JobWorkerBootstrapDocumentSyncTest extends TestCase {
	/**
	 * Production worker composition registers the stable index.document handler.
	 */
	public function test_registry_registers_document_index_handler(): void {
		$source_handler = new KnowledgeSourceSyncJobHandler(
			$this->createMock( KnowledgeSourceRepository::class ),
			new KnowledgeSourceRegistry(),
			$this->createMock( DocumentRepository::class ),
			new DocumentIndexJobEnqueuer( $this->createMock( JobRepository::class ) ),
			$this->createMock( Clock::class )
		);
		$registry       = JobWorkerBootstrap::handler_registry( $this->createMock( DocumentIndexDependencies::class ), $source_handler );
		$handler        = $registry->for_type( 'index.document' );

		self::assertInstanceOf( DocumentIndexJobHandler::class, $handler );
		self::assertSame( 'index.document', $handler->type() );
		self::assertSame( $source_handler, $registry->for_type( 'sync.source' ) );
	}

	/** Direct callers retain the public no-argument fail-closed registry contract. */
	public function test_registry_accepts_no_dependencies(): void {
		$handler = JobWorkerBootstrap::handler_registry()->for_type( 'index.document' );

		self::assertInstanceOf( DocumentIndexJobHandler::class, $handler );
	}
}
