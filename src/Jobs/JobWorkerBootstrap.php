<?php
/**
 * WordPress composition root for M09 job execution entrypoints.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use WpRagAiChatbot\Database\Repository\WpdbJobRepository;
use WpRagAiChatbot\Database\Repository\WpdbDocumentRepository;
use WpRagAiChatbot\Database\Repository\WpdbKnowledgeSourceRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbConnection;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexDependencies;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobEnqueuer;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobHandler;
use WpRagAiChatbot\Jobs\Sync\KnowledgeSourceSyncJobHandler;
use WpRagAiChatbot\Jobs\Sync\SearchProjectionDocumentIndexDependencies;
use WpRagAiChatbot\Jobs\Sync\UnavailableDocumentIndexDependencies;
use WpRagAiChatbot\Jobs\Sync\WordPressDocumentIndexDependencies;
use WpRagAiChatbot\Knowledge\KnowledgeBootstrap;
use WpRagAiChatbot\Providers\ProviderBootstrap;
use WpRagAiChatbot\Retrieval\Lexical\WpdbChunkSearchStore;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\VectorStore\Local\LocalVectorStoreConfig;
use WpRagAiChatbot\VectorStore\VectorStoreBootstrap;

/**
 * Composes one shared worker for cron and WP-CLI entrypoints.
 */
final class JobWorkerBootstrap {
	/**
	 * Register WordPress execution entrypoints.
	 */
	public static function register(): void {
		global $wpdb;

		$connection = new WpdbConnection( $wpdb );
		$tables     = new TableNames( $connection->prefix() );
		$repository = new WpdbJobRepository( $connection, $tables );
		$clock      = new SystemClock();
		$retrieval  = new RetrievalConfig();
		$sources    = new WpdbKnowledgeSourceRepository( $connection, $tables );
		$documents  = new WpdbDocumentRepository( $connection, $tables );
		$chunks     = new WpdbChunkSearchStore( $connection, $tables );

		ProviderBootstrap::register();
		VectorStoreBootstrap::register_local(
			$connection,
			$tables,
			new LocalVectorStoreConfig( $retrieval->lexical_candidate_limit, $retrieval->semantic_top_k )
		);

		$document_dependencies = new SearchProjectionDocumentIndexDependencies(
			new WordPressDocumentIndexDependencies(
				$sources,
				$documents,
				ProviderBootstrap::registry(),
				VectorStoreBootstrap::registry(),
				$chunks
			),
			$chunks
		);
		$source_handler        = new KnowledgeSourceSyncJobHandler(
			$sources,
			KnowledgeBootstrap::registry(),
			$documents,
			new DocumentIndexJobEnqueuer( $repository ),
			$clock
		);
		$worker                = new JobWorker(
			$repository,
			self::handler_registry( $document_dependencies, $source_handler ),
			$clock
		);

		$cron = new WordPressJobCron( $worker );
		$cron->register();
		WordPressCliJobsCommand::register_if_available( $worker );
	}

	/**
	 * Build the explicit allowlisted handler registry used by production workers.
	 *
	 * @param DocumentIndexDependencies|null     $document_index_dependencies Reconstructed document-index dependencies.
	 * @param KnowledgeSourceSyncJobHandler|null $source_sync_handler Optional source-sync handler.
	 */
	public static function handler_registry(
		?DocumentIndexDependencies $document_index_dependencies = null,
		?KnowledgeSourceSyncJobHandler $source_sync_handler = null
	): JobHandlerRegistry {
		$document_index_dependencies ??= new UnavailableDocumentIndexDependencies();
		$registry                      = new JobHandlerRegistry();
		$registry->register( new DocumentIndexJobHandler( $document_index_dependencies ) );
		if ( null !== $source_sync_handler ) {
			$registry->register( $source_sync_handler );
		}
		return $registry;
	}
}
