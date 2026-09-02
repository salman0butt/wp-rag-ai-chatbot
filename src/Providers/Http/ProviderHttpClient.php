<?php
/**
 * Provider HTTP retry policy client.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Http;

/**
 * Applies operation-specific retry policy over a single-send transport.
 */
final class ProviderHttpClient {
	/**
	 * Create a provider HTTP client.
	 *
	 * @param HttpTransport $transport Single-send HTTP transport.
	 */
	public function __construct( private HttpTransport $transport ) {
	}

	/**
	 * Send a paid generation request exactly once.
	 *
	 * @param HttpRequest $request Provider generation request.
	 * @throws HttpTransportException When transport fails.
	 */
	public function generation( HttpRequest $request ): HttpResponse {
		return $this->transport->send( $request );
	}

	/**
	 * Send model discovery with at most one narrowly allowed retry.
	 *
	 * @param HttpRequest $request Provider model-discovery request.
	 * @throws HttpTransportException When the final transport attempt fails.
	 */
	public function discovery( HttpRequest $request ): HttpResponse {
		try {
			$response = $this->transport->send( $request );
		} catch ( HttpTransportException ) {
			return $this->transport->send( $request );
		}

		if ( in_array( $response->status, array( 502, 503, 504 ), true ) ) {
			return $this->transport->send( $request );
		}

		return $response;
	}
}
