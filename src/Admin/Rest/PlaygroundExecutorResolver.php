<?php
/**
 * Playground production-executor composition boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

/**
 * Composes one request-local production Playground executor from established authorities.
 */
final readonly class PlaygroundExecutorResolver {
	/**
	 * Create the production-executor resolver.
	 *
	 * @param PlaygroundSemanticConfigurationResolver $semantic_configuration Persisted semantic identity resolver.
	 * @param PlaygroundSemanticRetrieverResolver     $semantic_retriever Existing production semantic retriever composer.
	 * @param PlaygroundHybridRetrieverResolver       $hybrid_retriever Existing production hybrid retriever composer.
	 * @param PlaygroundGenerationProviderResolver    $generation_provider Persisted generation-provider resolver.
	 * @param PlaygroundAccessContextResolver         $access_context Trusted persisted retrieval-scope resolver.
	 * @param PlaygroundChatGraphResolver             $chat_graph Existing production M11 graph composer.
	 */
	public function __construct(
		private PlaygroundSemanticConfigurationResolver $semantic_configuration,
		private PlaygroundSemanticRetrieverResolver $semantic_retriever,
		private PlaygroundHybridRetrieverResolver $hybrid_retriever,
		private PlaygroundGenerationProviderResolver $generation_provider,
		private PlaygroundAccessContextResolver $access_context,
		private PlaygroundChatGraphResolver $chat_graph
	) {
	}

	/**
	 * Compose one request-local executor without running retrieval or generation.
	 *
	 * @param PlaygroundConfiguration $configuration Closed persisted Playground configuration.
	 */
	public function resolve( PlaygroundConfiguration $configuration ): ProductionPlaygroundExecutor {
		$semantic = $this->semantic_configuration->resolve( $configuration );
		$semantic_retriever = $this->semantic_retriever->resolve( $configuration->retrieval, $semantic );
		$retriever = $this->hybrid_retriever->resolve( $semantic_retriever );
		$provider = $this->generation_provider->resolve( $configuration );
		$access = $this->access_context->resolve( $configuration );
		$capture = new PlaygroundRetrievalCapture();

		return $this->chat_graph->resolve(
			$retriever,
			$provider,
			$access,
			$capture,
			$configuration->bot->model_id
		);
	}
}
