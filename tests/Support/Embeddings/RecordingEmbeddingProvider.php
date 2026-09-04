<?php
/**
 * Deterministic fake embedding provider for tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Embeddings;

use RuntimeException;
use WpRagAiChatbot\Providers\EmbeddingProvider;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\Providers\EmbeddingResult;

/**
 * Records requests and returns queued normalized embedding results.
 */
final class RecordingEmbeddingProvider implements EmbeddingProvider {
	/**
	 * Requests received in call order.
	 *
	 * @var EmbeddingRequest[]
	 */
	public array $requests = array();

	/**
	 * Results returned in call order.
	 *
	 * @var EmbeddingResult[]
	 */
	private array $results;

	/**
	 * Create a provider with deterministic queued results.
	 *
	 * @param EmbeddingResult[] $results Queued results.
	 */
	public function __construct( array $results ) {
		$this->results = $results;
	}

	/**
	 * Return the stable fake provider ID.
	 */
	public function provider_id(): string {
		return 'test-embedding';
	}

	/**
	 * The fake provider is always available.
	 */
	public function available(): bool {
		return true;
	}

	/**
	 * Record one request and return the next queued result.
	 *
	 * @param EmbeddingRequest $request Normalized embedding request.
	 * @throws RuntimeException When no queued result remains.
	 */
	public function embed( EmbeddingRequest $request ): EmbeddingResult {
		$this->requests[] = $request;
		$result           = array_shift( $this->results );
		if ( ! $result instanceof EmbeddingResult ) {
			throw new RuntimeException( 'No queued embedding result.' );
		}

		return $result;
	}
}
