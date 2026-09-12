<?php
/**
 * Protected administrator Playground execution resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

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

	/** Maximum citation title bytes exposed by the administrator REST DTO. */
	private const MAX_CITATION_TITLE_BYTES = 256;

	/** Maximum citation canonical URL bytes exposed by the administrator REST DTO. */
	private const MAX_CITATION_URL_BYTES = 2048;

	/**
	 * Create the Playground resource.
	 *
	 * @param PlaygroundExecutor $executor Request-local production executor.
	 */
	public function __construct( private readonly PlaygroundExecutor $executor ) {
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
			$response = $this->executor->execute( $question );
		} catch ( RetrievalException $exception ) {
			unset( $exception );

			return $this->error( 'retrieval_unavailable' );
		} catch ( Throwable $throwable ) {
			unset( $throwable );

			return $this->error( 'playground_failed' );
		}

		return $this->project_success( $response );
	}

	/**
	 * Project one typed execution result without recursively exposing arbitrary values.
	 *
	 * @param PlaygroundExecutionResult $result Typed production result.
	 * @return array<string,mixed>
	 */
	private function project_success( PlaygroundExecutionResult $result ): array {
		$citations = array();
		foreach ( $result->chat->citations as $citation ) {
			if ( ! $citation instanceof Citation ) {
				continue;
			}

			$citations[] = array(
				'id'            => $citation->id,
				'chunk_id'      => $citation->chunk_id,
				'document_id'   => $citation->document_id,
				'source_id'     => $citation->source_id,
				'title'         => self::bound_nullable_utf8( $citation->title, self::MAX_CITATION_TITLE_BYTES ),
				'canonical_url' => self::bound_nullable_utf8( $citation->canonical_url, self::MAX_CITATION_URL_BYTES ),
			);
		}

		return array(
			'ok'          => true,
			'answer'      => $result->chat->answer,
			'no_answer'   => $result->chat->no_answer,
			'citations'   => $citations,
			'model_id'    => $result->model_id,
			'latency_ms'  => $result->latency_ms,
			'usage'       => $this->project_usage( $result->chat->usage ),
			'debug_trace' => $result->debug->to_array(),
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
	 * Bound nullable display metadata without leaving an invalid trailing UTF-8 sequence.
	 *
	 * @param string|null $value Display metadata value.
	 * @param int         $max_bytes Maximum byte length.
	 */
	private static function bound_nullable_utf8( ?string $value, int $max_bytes ): ?string {
		if ( null === $value || strlen( $value ) <= $max_bytes ) {
			return $value;
		}

		$bounded = substr( $value, 0, $max_bytes );
		while ( '' !== $bounded && 1 !== preg_match( '//u', $bounded ) ) {
			$bounded = substr( $bounded, 0, -1 );
		}

		return $bounded;
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
