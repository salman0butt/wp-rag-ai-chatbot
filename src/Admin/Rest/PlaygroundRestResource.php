<?php
/**
 * Protected administrator Playground execution resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use Closure;
use Throwable;
use WpRagAiChatbot\Citations\Citation;
use WpRagAiChatbot\Providers\Usage;
use WpRagAiChatbot\Retrieval\RetrievalException;

/**
 * Validates bounded Playground input before delegating to production execution.
 */
final class PlaygroundRestResource {
	/** Maximum accepted question size, matching the production ChatRequest boundary. */
	private const MAX_QUESTION_BYTES = 16384;

	/**
	 * Request-local production executor.
	 *
	 * @var Closure
	 */
	private Closure $executor;

	/**
	 * Create the Playground resource.
	 *
	 * @param Closure $executor Request-local production executor.
	 */
	public function __construct( Closure $executor ) {
		$this->executor = $executor;
	}

	/**
	 * Execute one validated Playground question.
	 *
	 * @param string $question Administrator question.
	 * @return array<string,mixed>
	 */
	public function run( string $question ): array {
		$question = trim( $question );

		if ( '' === $question || strlen( $question ) > self::MAX_QUESTION_BYTES ) {
			return $this->error( 'invalid_request' );
		}

		try {
			$response = ( $this->executor )( $question );
		} catch ( RetrievalException $exception ) {
			unset( $exception );

			return $this->error( 'retrieval_unavailable' );
		} catch ( Throwable $throwable ) {
			unset( $throwable );

			return $this->error( 'playground_failed' );
		}

		return $response instanceof PlaygroundExecutionResult
			? $this->project_success( $response )
			: $this->error( 'playground_failed' );
	}

	/**
	 * Project one typed execution result without recursively exposing arbitrary values.
	 *
	 * @param PlaygroundExecutionResult $result Typed production result.
	 * @return array<string,mixed>
	 */
	private function project_success( PlaygroundExecutionResult $result ): array {
		$citations = array();
		foreach ( $result->chat_result->citations as $citation ) {
			if ( ! $citation instanceof Citation ) {
				continue;
			}

			$citations[] = array(
				'id'            => $citation->id,
				'chunk_id'      => $citation->chunk_id,
				'document_id'   => $citation->document_id,
				'source_id'     => $citation->source_id,
				'title'         => $citation->title,
				'canonical_url' => $citation->canonical_url,
			);
		}

		return array(
			'ok'          => true,
			'answer'      => $result->chat_result->answer,
			'no_answer'   => $result->chat_result->no_answer,
			'citations'   => $citations,
			'model_id'    => $result->model_id,
			'latency_ms'  => $result->latency_ms,
			'usage'       => $this->project_usage( $result->chat_result->usage ),
			'debug_trace' => $result->debug_trace->to_array(),
		);
	}

	/**
	 * Project only provider-neutral token counters.
	 *
	 * @param Usage|null $usage Normalized provider usage.
	 * @return array{input_tokens:int|null,output_tokens:int|null,total_tokens:int|null}
	 */
	private function project_usage( ?Usage $usage ): array {
		return array(
			'input_tokens'  => $usage?->input_tokens,
			'output_tokens' => $usage?->output_tokens,
			'total_tokens'  => $usage?->total_tokens,
		);
	}

	/**
	 * Create one stable safe error payload.
	 *
	 * @param string $code Repository-owned error code.
	 * @return array<string,array<string,string>>
	 */
	private function error( string $code ): array {
		return array(
			'error' => array(
				'code' => $code,
			),
		);
	}
}
