<?php
/**
 * M13 Playground-to-production retrieval correlation integration coverage.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\PlaygroundRestResource;
use WpRagAiChatbot\Admin\Rest\PlaygroundRetrievalCapture;
use WpRagAiChatbot\Admin\Rest\ProductionPlaygroundExecutor;
use WpRagAiChatbot\Admin\Rest\StatelessPlaygroundConversationHistory;
use WpRagAiChatbot\Chat\ChatAccessContext;
use WpRagAiChatbot\Chat\ChatOrchestrator;
use WpRagAiChatbot\Chat\ChatRequestPolicy;
use WpRagAiChatbot\Citations\CitationValidator;
use WpRagAiChatbot\Debug\DebugTraceProjector;
use WpRagAiChatbot\Memory\MemoryAssembler;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationResult;
use WpRagAiChatbot\Providers\GenerationStatus;
use WpRagAiChatbot\Providers\Usage;
use WpRagAiChatbot\RAG\DeterministicGroundingPolicy;
use WpRagAiChatbot\RAG\GroundingMode;
use WpRagAiChatbot\RAG\PromptBuilder;
use WpRagAiChatbot\Retrieval\Access\DefaultCandidateAccessPolicy;
use WpRagAiChatbot\Retrieval\Confidence\ConfidenceEstimator;
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\Fusion\RankedCandidate;
use WpRagAiChatbot\Retrieval\Fusion\ReciprocalRankFusion;
use WpRagAiChatbot\Retrieval\HybridRetriever;
use WpRagAiChatbot\Retrieval\Lexical\LexicalFilter;
use WpRagAiChatbot\Retrieval\Lexical\LexicalRetrievalChannel;
use WpRagAiChatbot\Retrieval\QueryPreprocessor;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\Retrieval\RetrievalQuery;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalChannel;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalContext;

/**
 * Proves Playground executes one real M10/M11 graph and projects the same observed retrieval evidence.
 */
final class PlaygroundRetrievalTest extends TestCase {
	/** Semantic and lexical production channels must each execute exactly once for one Playground request. */
	public function test_playground_projects_the_same_retrieval_consumed_by_chat_once(): void {
		$semantic_calls = 0;
		$lexical_calls  = 0;
		$config         = new RetrievalConfig();
		$candidate      = new RankedCandidate(
			'fixture-chunk',
			'fixture-document',
			7,
			'Fixture evidence for the administrator Playground.',
			'en',
			'public',
			1.0
		);
		$semantic       = new class( $candidate, $semantic_calls ) implements SemanticRetrievalChannel {
			/**
			 * Create the deterministic semantic channel fixture.
			 *
			 * @param RankedCandidate $candidate Fixture candidate.
			 * @param int             $calls Shared call counter.
			 */
			public function __construct( private RankedCandidate $candidate, private int &$calls ) {
			}

			/**
			 * Return the deterministic semantic candidate.
			 *
			 * @param RetrievalQuery           $query Normalized retrieval query.
			 * @param SemanticRetrievalContext $context Trusted semantic retrieval scope.
			 * @return list<RankedCandidate>
			 */
			public function retrieve( RetrievalQuery $query, SemanticRetrievalContext $context ): array {
				unset( $query, $context );
				++$this->calls;
				return array( $this->candidate );
			}
		};
		$lexical        = new class( $candidate, $lexical_calls ) implements LexicalRetrievalChannel {
			/**
			 * Create the deterministic lexical channel fixture.
			 *
			 * @param RankedCandidate $candidate Fixture candidate.
			 * @param int             $calls Shared call counter.
			 */
			public function __construct( private RankedCandidate $candidate, private int &$calls ) {
			}

			/**
			 * Return the deterministic lexical candidate.
			 *
			 * @param RetrievalQuery $query Normalized retrieval query.
			 * @param LexicalFilter  $filter Trusted lexical retrieval scope.
			 * @return list<RankedCandidate>
			 */
			public function retrieve( RetrievalQuery $query, LexicalFilter $filter ): array {
				unset( $query, $filter );
				++$this->calls;
				return array( $this->candidate );
			}
		};
		$retriever      = new HybridRetriever(
			$semantic,
			$lexical,
			new ReciprocalRankFusion( $config ),
			new ConfidenceEstimator(),
			new DefaultCandidateAccessPolicy(),
			$config
		);
		$capture        = new PlaygroundRetrievalCapture();
		$orchestrator   = new ChatOrchestrator(
			new ChatRequestPolicy( new QueryPreprocessor( $config ) ),
			new MemoryAssembler( new StatelessPlaygroundConversationHistory() ),
			$retriever,
			new DeterministicGroundingPolicy(),
			new PromptBuilder(),
			$this->provider(),
			new CitationValidator(),
			null,
			null,
			$capture
		);
		$filter         = new RetrievalFilter( 'public', 'en', array( 7 ) );
		$access         = new ChatAccessContext(
			'admin-playground',
			new SemanticRetrievalContext( $filter, static fn (): null => null ),
			new LexicalFilter( 'fixture-collection', null, 7, 'en', 'public' )
		);
		$executor       = new ProductionPlaygroundExecutor(
			$orchestrator,
			$access,
			$capture,
			new DebugTraceProjector(),
			'model-test',
			GroundingMode::STRICT
		);

		$response = ( new PlaygroundRestResource( $executor ) )->run( 'What does the fixture say?' );

		self::assertSame( 1, $semantic_calls );
		self::assertSame( 1, $lexical_calls );
		self::assertTrue( $response['ok'] );
		self::assertSame( 'Fixture answer. [C1]', $response['answer'] );
		self::assertSame( 'fixture-chunk', $response['debug_trace']['candidates'][0]['chunk_id'] );
		self::assertSame( hash( 'sha256', 'What does the fixture say?' ), $response['debug_trace']['query']['hash'] );
	}

	/** Build a deterministic generation provider around the real M11 orchestration path. */
	private function provider(): GenerationProvider {
		return new class() implements GenerationProvider {
			/** Return the stable fixture provider identifier. */
			public function provider_id(): string {
				return 'playground-integration';
			}

			/** Report that the deterministic fixture provider is available. */
			public function available(): bool {
				return true;
			}

			/**
			 * Return one deterministic cited generation result.
			 *
			 * @param GenerationRequest $request Normalized production generation request.
			 */
			public function generate( GenerationRequest $request ): GenerationResult {
				return new GenerationResult(
					$this->provider_id(),
					$request->model_id,
					'Fixture answer. [C1]',
					GenerationStatus::COMPLETED,
					new Usage( 10, 5, 15 )
				);
			}
		};
	}
}
