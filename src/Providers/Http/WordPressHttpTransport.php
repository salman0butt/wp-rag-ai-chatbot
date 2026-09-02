<?php
/**
 * WordPress provider HTTP transport.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Http;

use WpRagAiChatbot\Providers\ProviderErrorCode;

// phpcs:disable WordPress.Security.EscapeOutput -- Internal normalized exceptions are never rendered as output.
/**
 * Executes provider HTTP requests through the WordPress HTTP API.
 */
final class WordPressHttpTransport implements HttpTransport {
	/**
	 * Send one provider HTTP request through WordPress.
	 *
	 * @param HttpRequest $request Provider HTTP request.
	 * @throws HttpTransportException When request serialization or transport fails.
	 */
	public function send( HttpRequest $request ): HttpResponse {
		$args = array(
			'method'      => $request->method,
			'headers'     => $request->headers,
			'timeout'     => $request->timeout,
			'redirection' => $request->redirection,
		);

		if ( null !== $request->json_body ) {
			$body = wp_json_encode( $request->json_body );
			if ( false === $body ) {
				throw new HttpTransportException(
					ProviderErrorCode::TRANSPORT,
					'Provider request serialization failed.'
				);
			}
			$args['body'] = $body;
		}

		$response = wp_remote_request( $request->url, $args );
		if ( is_wp_error( $response ) ) {
			$this->throw_transport_failure( $response->get_error_code(), $response->get_error_message() );
		}

		return new HttpResponse(
			(int) wp_remote_retrieve_response_code( $response ),
			$this->normalize_headers( wp_remote_retrieve_headers( $response ) ),
			(string) wp_remote_retrieve_body( $response )
		);
	}

	/**
	 * Throw a normalized transport exception without exposing provider secrets.
	 *
	 * @param int|string $code WordPress error code.
	 * @param string     $message WordPress error message used only for classification.
	 * @throws HttpTransportException Always.
	 */
	private function throw_transport_failure( int|string $code, string $message ): never {
		$evidence   = strtolower( (string) $code . ' ' . $message );
		$is_timeout = '28' === (string) $code
			|| 1 === preg_match( '/curl error\s*28\b/i', $message )
			|| str_contains( $evidence, 'timed out' );

		throw new HttpTransportException(
			$is_timeout ? ProviderErrorCode::TIMEOUT : ProviderErrorCode::TRANSPORT,
			$is_timeout ? 'Provider request timed out.' : 'Provider transport failed.'
		);
	}

	/**
	 * Normalize WordPress response headers to a plain array.
	 *
	 * @param mixed $headers WordPress response headers.
	 * @return array<string, mixed>
	 */
	private function normalize_headers( mixed $headers ): array {
		if ( is_array( $headers ) ) {
			return $headers;
		}

		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$all = $headers->getAll();
			return is_array( $all ) ? $all : array();
		}

		return array();
	}
}
