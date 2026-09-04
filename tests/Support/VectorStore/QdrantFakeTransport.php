<?php
/**
 * Qdrant fake HTTP transport.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\VectorStore;

use RuntimeException;
use WpRagAiChatbot\Providers\Http\HttpRequest;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Providers\Http\HttpTransport;

/**
 * Records Qdrant requests and returns queued offline responses.
 */
final class QdrantFakeTransport implements HttpTransport {
	/**
	 * Recorded requests.
	 *
	 * @var list<HttpRequest>
	 */
	public array $requests = array();

	/**
	 * Create the fake transport.
	 *
	 * @param array $responses Queued responses.
	 * @phpstan-param list<HttpResponse> $responses
	 */
	public function __construct( private array $responses ) {
	}

	/**
	 * Record one request and return the next response.
	 *
	 * @param HttpRequest $request Request to record.
	 * @throws RuntimeException When a request has no queued response.
	 */
	public function send( HttpRequest $request ): HttpResponse {
		$this->requests[] = $request;
		if ( array() === $this->responses ) {
			throw new RuntimeException( 'Unexpected HTTP request.' );
		}

		return array_shift( $this->responses );
	}
}
