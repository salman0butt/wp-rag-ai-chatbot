<?php
/**
 * Provider HTTP client policy tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\Http;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Http\HttpRequest;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Providers\Http\HttpTransport;
use WpRagAiChatbot\Providers\Http\HttpTransportException;
use WpRagAiChatbot\Providers\Http\ProviderHttpClient;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderIds;

/**
 * Verifies retry-sensitive provider HTTP policy independently from WordPress I/O.
 */
final class ProviderHttpClientTest extends TestCase {
	/**
	 * Generation requests carry the required timeout and default redirect policy.
	 */
	public function test_generation_request_carries_timeout_and_zero_redirects(): void {
		$this->require_http_contracts();

		$request = new HttpRequest(
			ProviderIds::OPENAI_DIRECT,
			'POST',
			'https://api.openai.com/v1/responses',
			array( 'Authorization' => 'Bearer secret' ),
			array( 'input' => 'Hello' ),
			45
		);

		self::assertSame( 45, $request->timeout );
		self::assertSame( 0, $request->redirection );
		self::assertSame( 'POST', $request->method );
	}

	/**
	 * Discovery requests carry the required timeout and default redirect policy.
	 */
	public function test_discovery_request_carries_timeout_and_zero_redirects(): void {
		$this->require_http_contracts();

		$request = new HttpRequest(
			ProviderIds::OPENROUTER_DIRECT,
			'GET',
			'https://openrouter.ai/api/v1/models',
			array(),
			null,
			10
		);

		self::assertSame( 10, $request->timeout );
		self::assertSame( 0, $request->redirection );
		self::assertNull( $request->json_body );
	}

	/**
	 * Generation sends exactly once on success.
	 */
	public function test_generation_sends_exactly_once(): void {
		$this->require_http_contracts();
		$request   = $this->request();
		$response  = new HttpResponse( 200, array(), '{"ok":true}' );
		$transport = $this->createMock( HttpTransport::class );
		$transport->expects( self::once() )->method( 'send' )->with( $request )->willReturn( $response );

		self::assertSame( $response, ( new ProviderHttpClient( $transport ) )->generation( $request ) );
	}

	/**
	 * Generation transport failures are never retried.
	 */
	public function test_generation_does_not_retry_transport_failure(): void {
		$this->require_http_contracts();
		$request   = $this->request();
		$transport = $this->createMock( HttpTransport::class );
		$failure   = new HttpTransportException( ProviderErrorCode::TRANSPORT, 'Provider transport failed.' );
		$transport->expects( self::once() )->method( 'send' )->with( $request )->willThrowException( $failure );

		$this->expectExceptionObject( $failure );
		( new ProviderHttpClient( $transport ) )->generation( $request );
	}

	/**
	 * Discovery retries exactly once after a transport failure.
	 */
	public function test_discovery_retries_once_after_transport_failure(): void {
		$this->require_http_contracts();
		$request   = $this->request( 10 );
		$response  = new HttpResponse( 200, array(), '[]' );
		$transport = $this->createMock( HttpTransport::class );
		$calls     = 0;
		$transport->expects( self::exactly( 2 ) )->method( 'send' )->with( $request )->willReturnCallback(
			static function () use ( &$calls, $response ): HttpResponse {
				++$calls;
				if ( 1 === $calls ) {
					// phpcs:ignore WordPress.Security.EscapeOutput -- Test-only transport exception is never rendered.
					throw new HttpTransportException( ProviderErrorCode::TRANSPORT, 'Provider transport failed.' );
				}
				return $response;
			}
		);

		self::assertSame( $response, ( new ProviderHttpClient( $transport ) )->discovery( $request ) );
	}

	/**
	 * Discovery retries only the explicitly allowed gateway statuses.
	 */
	public function test_discovery_retries_502_503_and_504_once(): void {
		$this->require_http_contracts();

		foreach ( array( 502, 503, 504 ) as $status ) {
			$request   = $this->request( 10 );
			$recovered = new HttpResponse( 200, array(), '[]' );
			$transport = $this->createMock( HttpTransport::class );
			$calls     = 0;
			$transport->expects( self::exactly( 2 ) )->method( 'send' )->with( $request )->willReturnCallback(
				static function () use ( &$calls, $status, $recovered ): HttpResponse {
					++$calls;
					return 1 === $calls ? new HttpResponse( $status, array(), 'temporary' ) : $recovered;
				}
			);

			self::assertSame( $recovered, ( new ProviderHttpClient( $transport ) )->discovery( $request ) );
		}
	}

	/**
	 * Discovery does not retry forbidden or non-retryable statuses.
	 */
	public function test_discovery_does_not_retry_other_statuses(): void {
		$this->require_http_contracts();

		foreach ( array( 200, 400, 401, 403, 404, 429, 500, 501, 505, 599 ) as $status ) {
			$request   = $this->request( 10 );
			$response  = new HttpResponse( $status, array(), 'body' );
			$transport = $this->createMock( HttpTransport::class );
			$transport->expects( self::once() )->method( 'send' )->with( $request )->willReturn( $response );

			self::assertSame( $response, ( new ProviderHttpClient( $transport ) )->discovery( $request ) );
		}
	}

	/**
	 * Discovery never exceeds two total sends when the retry also receives 503.
	 */
	public function test_discovery_stops_after_second_retryable_response(): void {
		$this->require_http_contracts();
		$request   = $this->request( 10 );
		$first     = new HttpResponse( 503, array(), 'first' );
		$second    = new HttpResponse( 503, array(), 'second' );
		$transport = $this->createMock( HttpTransport::class );
		$calls     = 0;
		$transport->expects( self::exactly( 2 ) )->method( 'send' )->with( $request )->willReturnCallback(
			static function () use ( &$calls, $first, $second ): HttpResponse {
				++$calls;
				return 1 === $calls ? $first : $second;
			}
		);

		self::assertSame( $second, ( new ProviderHttpClient( $transport ) )->discovery( $request ) );
	}

	/**
	 * Build a deterministic request for client policy tests.
	 *
	 * @param int $timeout Request timeout.
	 */
	private function request( int $timeout = 45 ): HttpRequest {
		return new HttpRequest(
			ProviderIds::OPENAI_DIRECT,
			'GET',
			'https://example.test/models',
			array(),
			null,
			$timeout
		);
	}

	/**
	 * Require the intended missing-contract RED before implementation.
	 */
	private function require_http_contracts(): void {
		self::assertTrue( class_exists( HttpRequest::class ), 'HttpRequest must exist before provider HTTP policy can pass.' );
		self::assertTrue( class_exists( HttpResponse::class ), 'HttpResponse must exist before provider HTTP policy can pass.' );
		self::assertTrue( interface_exists( HttpTransport::class ), 'HttpTransport must exist before provider HTTP policy can pass.' );
		self::assertTrue( class_exists( HttpTransportException::class ), 'HttpTransportException must exist before provider HTTP policy can pass.' );
		self::assertTrue( class_exists( ProviderHttpClient::class ), 'ProviderHttpClient must exist before provider HTTP policy can pass.' );
	}
}
