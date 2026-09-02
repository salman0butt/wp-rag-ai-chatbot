<?php
/**
 * WordPress provider HTTP transport tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\Http;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Http\HttpRequest;
use WpRagAiChatbot\Providers\Http\HttpTransportException;
use WpRagAiChatbot\Providers\Http\WordPressHttpTransport;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Tests\Support\Providers\Http\FakeWordPressHttpError;

/**
 * Verifies the fixed WordPress HTTP API boundary used by provider adapters.
 */
final class WordPressHttpTransportTest extends TestCase {
	/**
	 * Start Brain Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear Brain Monkey down after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * JSON requests carry method, headers, timeout, zero redirects, and encoded body.
	 */
	public function test_send_passes_explicit_request_policy_and_normalizes_response(): void {
		$this->require_transport();
		$request = new HttpRequest(
			ProviderIds::OPENAI_DIRECT,
			'POST',
			'https://api.openai.com/v1/responses',
			array(
				'Authorization' => 'Bearer provider-secret',
				'Content-Type'  => 'application/json',
			),
			array( 'input' => 'Hello' ),
			45,
			0
		);
		$wp_response = array( 'response-marker' => true );

		Functions\expect( 'wp_json_encode' )
			->once()
			->with( array( 'input' => 'Hello' ) )
			->andReturn( '{"input":"Hello"}' );
		Functions\expect( 'wp_remote_request' )
			->once()
			->with(
				$request->url,
				array(
					'method'      => 'POST',
					'headers'     => $request->headers,
					'timeout'     => 45,
					'redirection' => 0,
					'body'        => '{"input":"Hello"}',
				)
			)
			->andReturn( $wp_response );
		Functions\expect( 'is_wp_error' )->once()->with( $wp_response )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->with( $wp_response )->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_headers' )->once()->with( $wp_response )->andReturn( array( 'x-request-id' => 'req_123' ) );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->with( $wp_response )->andReturn( '{"id":"resp_123"}' );

		$response = ( new WordPressHttpTransport() )->send( $request );

		self::assertSame( 200, $response->status );
		self::assertSame( array( 'x-request-id' => 'req_123' ), $response->headers );
		self::assertSame( '{"id":"resp_123"}', $response->body );
	}

	/**
	 * Requests without JSON bodies do not send a body argument.
	 */
	public function test_send_omits_body_for_null_json_body(): void {
		$this->require_transport();
		$request     = $this->request();
		$wp_response = array( 'response-marker' => true );

		Functions\expect( 'wp_json_encode' )->never();
		Functions\expect( 'wp_remote_request' )
			->once()
			->with(
				$request->url,
				array(
					'method'      => 'GET',
					'headers'     => $request->headers,
					'timeout'     => 10,
					'redirection' => 0,
				)
			)
			->andReturn( $wp_response );
		Functions\expect( 'is_wp_error' )->once()->with( $wp_response )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 204 );
		Functions\expect( 'wp_remote_retrieve_headers' )->once()->andReturn( array() );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '' );

		$response = ( new WordPressHttpTransport() )->send( $request );

		self::assertSame( 204, $response->status );
		self::assertSame( '', $response->body );
	}

	/**
	 * cURL error 28 evidence is normalized as a timeout.
	 */
	public function test_send_classifies_curl_error_28_as_timeout(): void {
		$this->require_transport();
		$request = $this->request();
		$error   = new FakeWordPressHttpError( 'http_request_failed', 'cURL error 28: Operation reached timeout.' );

		Functions\expect( 'wp_remote_request' )->once()->andReturn( $error );
		Functions\expect( 'is_wp_error' )->once()->with( $error )->andReturn( true );

		try {
			( new WordPressHttpTransport() )->send( $request );
			self::fail( 'Expected timeout transport exception.' );
		} catch ( HttpTransportException $exception ) {
			self::assertSame( ProviderErrorCode::TIMEOUT, $exception->error_code );
		}
	}

	/**
	 * Plain timed-out evidence is also normalized as a timeout.
	 */
	public function test_send_classifies_timed_out_message_as_timeout(): void {
		$this->require_transport();
		$request = $this->request();
		$error   = new FakeWordPressHttpError( 'http_request_failed', 'The provider connection timed out.' );

		Functions\expect( 'wp_remote_request' )->once()->andReturn( $error );
		Functions\expect( 'is_wp_error' )->once()->with( $error )->andReturn( true );

		try {
			( new WordPressHttpTransport() )->send( $request );
			self::fail( 'Expected timeout transport exception.' );
		} catch ( HttpTransportException $exception ) {
			self::assertSame( ProviderErrorCode::TIMEOUT, $exception->error_code );
		}
	}

	/**
	 * Generic WordPress HTTP failures remain transport errors with secret-free text.
	 */
	public function test_send_classifies_generic_error_as_transport_without_leaking_authorization(): void {
		$this->require_transport();
		$secret  = 'provider-secret-value';
		$request = new HttpRequest(
			ProviderIds::OPENROUTER_DIRECT,
			'GET',
			'https://openrouter.ai/api/v1/models',
			array( 'Authorization' => 'Bearer ' . $secret ),
			null,
			10,
			0
		);
		$error = new FakeWordPressHttpError( 'http_request_failed', 'Could not resolve provider host.' );

		Functions\expect( 'wp_remote_request' )->once()->andReturn( $error );
		Functions\expect( 'is_wp_error' )->once()->with( $error )->andReturn( true );

		try {
			( new WordPressHttpTransport() )->send( $request );
			self::fail( 'Expected transport exception.' );
		} catch ( HttpTransportException $exception ) {
			self::assertSame( ProviderErrorCode::TRANSPORT, $exception->error_code );
			self::assertStringNotContainsString( $secret, $exception->getMessage() );
			self::assertStringNotContainsString( 'Authorization', $exception->getMessage() );
		}
	}

	/**
	 * Build a deterministic model-discovery request.
	 */
	private function request(): HttpRequest {
		return new HttpRequest(
			ProviderIds::OPENROUTER_DIRECT,
			'GET',
			'https://openrouter.ai/api/v1/models',
			array( 'Authorization' => 'Bearer provider-secret' ),
			null,
			10,
			0
		);
	}

	/**
	 * Require the intended missing-class RED before transport implementation.
	 */
	private function require_transport(): void {
		self::assertTrue(
			class_exists( WordPressHttpTransport::class ),
			'WordPressHttpTransport must exist before WordPress provider HTTP behavior can pass.'
		);
	}
}
