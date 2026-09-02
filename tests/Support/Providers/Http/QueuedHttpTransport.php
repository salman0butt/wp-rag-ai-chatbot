<?php
/**
 * Queued provider HTTP transport test double.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Providers\Http;

use LogicException;
use WpRagAiChatbot\Providers\Http\HttpRequest;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Providers\Http\HttpTransport;
use WpRagAiChatbot\Providers\Http\HttpTransportException;

/**
 * Records requests and returns or throws queued deterministic outcomes.
 */
final class QueuedHttpTransport implements HttpTransport {
	/**
	 * Recorded requests in send order.
	 *
	 * @var HttpRequest[]
	 */
	public array $requests = array();

	/**
	 * Remaining deterministic outcomes.
	 *
	 * @var array<int, HttpResponse|HttpTransportException>
	 */
	private array $outcomes;

	/**
	 * Create a queued transport.
	 *
	 * @param array<int, HttpResponse|HttpTransportException> $outcomes Outcomes in send order.
	 */
	public function __construct( array $outcomes = array() ) {
		$this->outcomes = array_values( $outcomes );
	}

	/**
	 * Record a request and consume the next outcome.
	 *
	 * @param HttpRequest $request Provider HTTP request.
	 * @throws HttpTransportException|LogicException When the queued outcome fails or no outcome remains.
	 */
	public function send( HttpRequest $request ): HttpResponse {
		$this->requests[] = $request;

		$outcome = array_shift( $this->outcomes );
		if ( $outcome instanceof HttpTransportException ) {
			throw $outcome;
		}
		if ( ! $outcome instanceof HttpResponse ) {
			throw new LogicException( 'No queued HTTP transport outcome remains.' );
		}

		return $outcome;
	}
}
