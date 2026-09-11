<?php
/**
 * M13 Playground hybrid retriever composition tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\PlaygroundHybridRetrieverResolver;
use WpRagAiChatbot\Retrieval\Access\CandidateAccessPolicy;
use WpRagAiChatbot\Retrieval\Confidence\ConfidenceEstimator;
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\Fusion\ReciprocalRankFusion;
use WpRagAiChatbot\Retrieval\HybridRetriever;
use WpRagAiChatbot\Retrieval\Lexical\LexicalFilter;
use WpRagAiChatbot\Retrieval\Lexical\LexicalRetrievalChannel;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\Retrieval\RetrievalQuery;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalChannel;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalContext;

/**
 * Proves the Playground composes the existing M10 hybrid retriever instead of a parallel stack.
 */
final class PlaygroundHybridRetrieverResolverTest extends TestCase {
	/** Composition must bind the existing semantic and lexical channels without executing retrieval. */
	public function test_composes_existing_hybrid_retriever_without_running_channels(): void {
		$config = new RetrievalConfig(
			4096,
			128,
			20,
			100,
			40,
			20,
			12,
			60,
			1.0,
			1.0,
			true
		);

		$semantic = new class() implements SemanticRetrievalChannel {
			/** Retrieval must not run during composition. */
			public function retrieve( RetrievalQuery $query, SemanticRetrievalContext $context ): array {
				self::fail_if_called();
			}

			/** Fail if the composition path performs semantic work. */
			private static function fail_if_called(): never {
				throw new \LogicException( 'Semantic retrieval must not run during composition.' );
			}
		};
		$lexical = new class() implements LexicalRetrievalChannel {
			/** Retrieval must not run during composition. */
			public function retrieve( RetrievalQuery $query, LexicalFilter $filter ): array {
				throw new \LogicException( 'Lexical retrieval must not run during composition.' );
			}
		};
		$access = new class() implements CandidateAccessPolicy {
			/** Allowing candidates is outside this composition contract. */
			public function allows( RetrievalCandidate $candidate, RetrievalFilter $filter ): bool {
				throw new \LogicException( 'Candidate access checks must not run during composition.' );
			}
		};

		$resolver = new PlaygroundHybridRetrieverResolver(
			$lexical,
			new ReciprocalRankFusion( $config ),
			new ConfidenceEstimator(),
			$access,
			$config
		);

		self::assertInstanceOf( HybridRetriever::class, $resolver->resolve( $semantic ) );
	}
}
