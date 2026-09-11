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
			/**
			 * Number of semantic retrieval calls observed.
			 *
			 * @var int
			 */
			public int $calls = 0;

			/**
			 * Record semantic retrieval calls for the composition fixture.
			 *
			 * @param RetrievalQuery           $query Query fixture.
			 * @param SemanticRetrievalContext $context Context fixture.
			 * @return array
			 */
			public function retrieve( RetrievalQuery $query, SemanticRetrievalContext $context ): array {
				++$this->calls;
				return array();
			}
		};
		$lexical  = new class() implements LexicalRetrievalChannel {
			/**
			 * Number of lexical retrieval calls observed.
			 *
			 * @var int
			 */
			public int $calls = 0;

			/**
			 * Record lexical retrieval calls for the composition fixture.
			 *
			 * @param RetrievalQuery $query Query fixture.
			 * @param LexicalFilter  $filter Filter fixture.
			 * @return array
			 */
			public function retrieve( RetrievalQuery $query, LexicalFilter $filter ): array {
				++$this->calls;
				return array();
			}
		};
		$access   = new class() implements CandidateAccessPolicy {
			/**
			 * Number of access checks observed.
			 *
			 * @var int
			 */
			public int $calls = 0;

			/**
			 * Record access-policy calls for the composition fixture.
			 *
			 * @param RetrievalCandidate $candidate Candidate fixture.
			 * @param RetrievalFilter    $filter Trusted filter fixture.
			 */
			public function allows( RetrievalCandidate $candidate, RetrievalFilter $filter ): bool {
				++$this->calls;
				return true;
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
		self::assertSame( 0, $semantic->calls );
		self::assertSame( 0, $lexical->calls );
		self::assertSame( 0, $access->calls );
	}
}
