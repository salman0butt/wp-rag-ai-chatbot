<?php
/**
 * M09 unavailable document-index dependency behavior tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs\Sync;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\JobExecutionException;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobPayload;
use WpRagAiChatbot\Jobs\Sync\UnavailableDocumentIndexDependencies;

/**
 * Proves unreconstructible runtime dependencies fail explicitly and terminally.
 */
final class UnavailableDocumentIndexDependenciesTest extends TestCase {
	/**
	 * Missing server-side reconstruction is a safe non-retryable execution failure.
	 */
	public function test_unavailable_dependencies_fail_terminally(): void {
		$dependencies = new UnavailableDocumentIndexDependencies();
		$payload      = new DocumentIndexJobPayload( 'doc-42', 42, 'collection-main', 'index-profile-default', 'generation-7' );

		try {
			$dependencies->plan( $payload );
			self::fail( 'Unavailable document-index dependencies did not fail.' );
		} catch ( JobExecutionException $error ) {
			self::assertSame( 'index_dependencies_unavailable', $error->safe_code() );
			self::assertSame( 'Document indexing dependencies are unavailable for this configuration.', $error->safe_message() );
			self::assertFalse( $error->retryable() );
		}
	}
}
