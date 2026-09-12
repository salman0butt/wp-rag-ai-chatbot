<?php
/**
 * Public production responder composition boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use WpRagAiChatbot\Admin\Rest\PlaygroundConfiguration;
use WpRagAiChatbot\Admin\Rest\PlaygroundGenerationProviderResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundHybridRetrieverResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundRetrievalConfiguration;
use WpRagAiChatbot\Admin\Rest\PlaygroundSemanticConfigurationResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundSemanticRetrieverResolver;
use WpRagAiChatbot\Chat\ChatResponder;
use WpRagAiChatbot\Chat\ProductionChatResponderFactory;
use WpRagAiChatbot\Conversations\MessageRepository;

/**
 * Adapts persisted public runtime authority into the existing production RAG graph.
 */
final readonly class PublicChatResponderResolver {
	/**
	 * Create the public production responder resolver.
	 *
	 * @param PublicChatKnowledgeSourceResolver       $knowledge_sources Persisted knowledge-source authority.
	 * @param PlaygroundSemanticConfigurationResolver $semantic_configuration Existing persisted semantic configuration authority.
	 * @param PlaygroundSemanticRetrieverResolver     $semantic_retrievers Existing production semantic-retriever composer.
	 * @param PlaygroundHybridRetrieverResolver       $hybrid_retrievers Existing production hybrid-retriever composer.
	 * @param PlaygroundGenerationProviderResolver    $generation_providers Existing persisted generation-provider authority.
	 * @param ProductionChatResponderFactory           $responders Shared M11 responder composition authority.
	 * @param MessageRepository                        $messages Owner-scoped assistant-message persistence boundary.
	 */
	public function __construct(
		private PublicChatKnowledgeSourceResolver $knowledge_sources,
		private PlaygroundSemanticConfigurationResolver $semantic_configuration,
		private PlaygroundSemanticRetrieverResolver $semantic_retrievers,
		private PlaygroundHybridRetrieverResolver $hybrid_retrievers,
		private PlaygroundGenerationProviderResolver $generation_providers,
		private ProductionChatResponderFactory $responders,
		private MessageRepository $messages
	) {
	}

	/**
	 * Compose one request-local responder using only persisted server-side runtime authority.
	 *
	 * @param PublicChatRuntime $runtime Trusted persisted public runtime.
	 */
	public function resolve( PublicChatRuntime $runtime ): ChatResponder {
		$retrieval = new PlaygroundRetrievalConfiguration(
			$this->knowledge_sources->resolve( $runtime ),
			$runtime->retrieval->collection_id
		);
		$configuration = new PlaygroundConfiguration( $runtime->bot, $retrieval );
		$semantic      = $this->semantic_configuration->resolve( $configuration );
		$semantic      = $this->semantic_retrievers->resolve( $retrieval, $semantic );
		$hybrid        = $this->hybrid_retrievers->resolve( $semantic );
		$provider      = $this->generation_providers->resolve( $configuration );

		return $this->responders->create( $hybrid, $provider, $this->messages );
	}
}
