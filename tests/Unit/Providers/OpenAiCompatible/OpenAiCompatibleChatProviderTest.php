<?php
/**
 * OpenAI-compatible direct provider adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Providers\OpenAiCompatible;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\CredentialStore;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationStatus;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Providers\Http\ProviderHttpClient;
use WpRagAiChatbot\Providers\OpenAiCompatible\OpenAiCompatibleChatProvider;
use WpRagAiChatbot\Providers\ProviderIds;
use WpRagAiChatbot\Providers\Security\SecretRedactor;
use WpRagAiChatbot\Tests\Support\Providers\Http\QueuedHttpTransport;

/**
 * Verifies Gemini and Groq can share a safe OpenAI-compatible transport adapter.
 */
final class OpenAiCompatibleChatProviderTest extends TestCase {
	/**
	 * Groq generation uses its fixed endpoint and normalizes chat-completions output.
	 */
	public function test_groq_generation_uses_fixed_compatible_endpoint(): void {
		$this->require_adapter();
		$transport = new QueuedHttpTransport(
			array(
				new HttpResponse(
					200,
					array( 'x-request-id' => 'groq-request-1' ),
					'{"model":"llama-3.3-70b-versatile","choices":[{"message":{"content":"Hello from Groq"},"finish_reason":"stop"}],"usage":{"prompt_tokens":3,"completion_tokens":4,"total_tokens":7}}'
				),
			)
		);
		$provider  = $this->provider(
			ProviderIds::GROQ_DIRECT,
			'https://api.groq.com/openai/v1/chat/completions',
			'https://api.groq.com/openai/v1/models',
			$transport,
			'groq-secret'
		);
		$result    = $provider->generate(
			new GenerationRequest( 'llama-3.3-70b-versatile', 'Say hello', 'Be concise', 64 )
		);

		self::assertCount( 1, $transport->requests );
		$request = $transport->requests[0];
		self::assertSame( ProviderIds::GROQ_DIRECT, $request->provider_id );
		self::assertSame( 'https://api.groq.com/openai/v1/chat/completions', $request->url );
		self::assertSame( 'Bearer groq-secret', $request->headers['Authorization'] );
		self::assertSame( 'Hello from Groq', $result->output_text );
		self::assertSame( GenerationStatus::COMPLETED, $result->status );
		self::assertSame( 7, $result->usage->total_tokens );
		self::assertSame( 'groq-request-1', $result->request_id );
	}

	/**
	 * Gemini model discovery uses its OpenAI-compatible model endpoint.
	 */
	public function test_gemini_model_catalog_uses_fixed_compatible_endpoint(): void {
		$this->require_adapter();
		$transport = new QueuedHttpTransport(
			array(
				new HttpResponse(
					200,
					array(),
					'{"data":[{"id":"models/gemini-2.5-flash","name":"Gemini 2.5 Flash"}]}'
				),
			)
		);
		$provider  = $this->provider(
			ProviderIds::GEMINI_DIRECT,
			'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
			'https://generativelanguage.googleapis.com/v1beta/openai/models',
			$transport,
			'gemini-secret'
		);
		$models    = $provider->models();

		self::assertCount( 1, $transport->requests );
		self::assertSame(
			'https://generativelanguage.googleapis.com/v1beta/openai/models',
			$transport->requests[0]->url
		);
		self::assertSame( 'Bearer gemini-secret', $transport->requests[0]->headers['Authorization'] );
		self::assertCount( 1, $models );
		self::assertSame( ProviderIds::GEMINI_DIRECT, $models[0]->provider_id );
		self::assertSame( 'gemini-2.5-flash', $models[0]->model_id );
		self::assertSame( 'Gemini 2.5 Flash', $models[0]->display_name );
	}

	/**
	 * Gemini's REST model catalog names are accepted after removing its resource prefix.
	 */
	public function test_gemini_generation_normalizes_catalog_resource_model_id(): void {
		$this->require_adapter();
		$transport = new QueuedHttpTransport(
			array(
				new HttpResponse(
					200,
					array(),
					'{"model":"gemini-2.5-flash","choices":[{"message":{"content":"Grounded answer"},"finish_reason":"stop"}]}'
				),
			)
		);
		$provider  = $this->provider(
			ProviderIds::GEMINI_DIRECT,
			'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
			'https://generativelanguage.googleapis.com/v1beta/openai/models',
			$transport,
			'gemini-secret'
		);

		$provider->generate( new GenerationRequest( 'models/gemini-2.5-flash', 'Answer', null, 64 ) );

		self::assertSame( 'gemini-2.5-flash', $transport->requests[0]->json_body['model'] );
	}

	/** Google-compatible responses may return an uppercase native stop reason. */
	public function test_gemini_generation_accepts_uppercase_stop_reason(): void {
		$this->require_adapter();
		$transport = new QueuedHttpTransport(
			array(
				new HttpResponse(
					200,
					array(),
					'{"model":"gemini-2.5-flash","choices":[{"message":{"content":"Grounded answer"},"finish_reason":"STOP"}]}'
				),
			)
		);
		$provider  = $this->provider(
			ProviderIds::GEMINI_DIRECT,
			'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
			'https://generativelanguage.googleapis.com/v1beta/openai/models',
			$transport,
			'gemini-secret'
		);

		$result = $provider->generate( new GenerationRequest( 'gemini-2.5-flash', 'Answer', null, 64 ) );

		self::assertSame( GenerationStatus::COMPLETED, $result->status );
	}

	/** Google-compatible responses may return text parts instead of one string. */
	public function test_gemini_generation_normalizes_text_parts(): void {
		$this->require_adapter();
		$transport = new QueuedHttpTransport(
			array(
				new HttpResponse(
					200,
					array(),
					'{"model":"gemini-2.5-flash","choices":[{"message":{"content":[{"type":"text","text":"Grounded "},{"type":"text","text":"answer"}]},"finish_reason":"STOP"}]}'
				),
			)
		);
		$provider  = $this->provider(
			ProviderIds::GEMINI_DIRECT,
			'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
			'https://generativelanguage.googleapis.com/v1beta/openai/models',
			$transport,
			'gemini-secret'
		);

		$result = $provider->generate( new GenerationRequest( 'gemini-2.5-flash', 'Answer', null, 64 ) );

		self::assertSame( 'Grounded answer', $result->output_text );
		self::assertSame( GenerationStatus::COMPLETED, $result->status );
	}

	/**
	 * Build one compatible provider around deterministic boundaries.
	 *
	 * @param string              $provider_id Stable provider identifier.
	 * @param string              $generation_url Fixed chat-completions URL.
	 * @param string              $models_url Fixed model-catalog URL.
	 * @param QueuedHttpTransport $transport Deterministic transport.
	 * @param string              $credential Provider API key.
	 */
	private function provider(
		string $provider_id,
		string $generation_url,
		string $models_url,
		QueuedHttpTransport $transport,
		string $credential
	): OpenAiCompatibleChatProvider {
		$reader = $this->createMock( CredentialSourceReader::class );
		$store  = $this->createMock( CredentialStore::class );
		$reader->method( 'environment' )->willReturn( $credential );
		$reader->method( 'constant' )->willReturn( null );
		$store->method( 'load' )->willReturn( null );

		return new OpenAiCompatibleChatProvider(
			$provider_id,
			$generation_url,
			$models_url,
			new CredentialResolver( $reader, $store ),
			new ProviderHttpClient( $transport ),
			new SecretRedactor()
		);
	}

	/**
	 * Require the intended missing-adapter RED before implementation.
	 */
	private function require_adapter(): void {
		self::assertTrue(
			class_exists( OpenAiCompatibleChatProvider::class ),
			'OpenAiCompatibleChatProvider must exist before Gemini/Groq behavior can pass.'
		);
	}
}
