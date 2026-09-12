<?php
/**
 * Public chat REST execution resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use Closure;
use Throwable;

/**
 * Applies cheap public abuse controls before trusted runtime composition and chat execution.
 */
final class PublicChatRestResource {
	/** @var Closure(PublicChatRuntime):ProductionPublicChatExecutor */
	private readonly Closure $executor_factory;

	/**
	 * Create one public chat REST resource.
	 *
	 * @param PublicChatAbuseGuard                         $abuse_guard Public request budget authority.
	 * @param PublicChatRuntimeResolver                    $runtime_resolver Persisted server-owned runtime authority.
	 * @param callable(PublicChatRuntime):ProductionPublicChatExecutor $executor_factory Request-local production executor factory.
	 */
	public function __construct(
		private readonly PublicChatAbuseGuard $abuse_guard,
		private readonly PublicChatRuntimeResolver $runtime_resolver,
		callable $executor_factory
	) {
		$this->executor_factory = Closure::fromCallable( $executor_factory );
	}

	/**
	 * Execute one validated public request with server-derived client scope.
	 *
	 * @param PublicChatRequest $request Validated closed public request.
	 * @param string            $client_scope Server-derived opaque client scope.
	 * @return array<string,mixed>
	 */
	public function run( PublicChatRequest $request, string $client_scope ): array {
		try {
			if ( ! $this->abuse_guard->allow( $request->bot_id, $client_scope ) ) {
				return self::error( 'rate_limited' );
			}
		} catch ( Throwable ) {
			return self::error( 'rate_limited' );
		}

		try {
			$runtime  = $this->runtime_resolver->resolve( $request->bot_id );
			$executor = ( $this->executor_factory )( $runtime );
			$response = $executor->execute( $request );
		} catch ( Throwable ) {
			return self::error( 'chat_unavailable' );
		}

		$citations = array();
		foreach ( $response->citations as $citation ) {
			$citations[] = array(
				'id'    => $citation->id,
				'title' => $citation->title,
				'url'   => $citation->url,
			);
		}

		return array(
			'ok'              => true,
			'answer'          => $response->answer,
			'conversation_id' => $response->conversation_id,
			'citations'       => $citations,
		);
	}

	/**
	 * Create one stable non-sensitive public error payload.
	 *
	 * @param string $code Repository-owned public error code.
	 * @return array<string,array<string,string>>
	 */
	private static function error( string $code ): array {
		return array(
			'error' => array(
				'code' => $code,
			),
		);
	}
}
