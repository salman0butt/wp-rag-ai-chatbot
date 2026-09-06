<?php
/**
 * Deterministic grounding-policy tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\RAG;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use WpRagAiChatbot\Chat\ChatFailureReason;
use WpRagAiChatbot\RAG\GroundingMode;
use WpRagAiChatbot\Retrieval\Confidence\RetrievalConfidence;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;

/**
 * Specifies deterministic evidence sufficiency without model/provider involvement.
 */
final class DeterministicGroundingPolicyTest extends TestCase {
	private const NO_ANSWER = "I don't have enough reliable information in the selected sources to answer that.";

	/**
	 * Task 6 contracts must exist before orchestration can make grounding decisions.
	 */
	public function test_grounding_policy_contracts_exist(): void {
		self::assertTrue( class_exists( 'WpRagAiChatbot\\RAG\\GroundingDecision' ), 'GroundingDecision contract is missing.' );
		self::assertTrue( interface_exists( 'WpRagAiChatbot\\RAG\\GroundingPolicy' ), 'GroundingPolicy contract is missing.' );
		self::assertTrue( class_exists( 'WpRagAiChatbot\\RAG\\DeterministicGroundingPolicy' ), 'DeterministicGroundingPolicy contract is missing.' );
	}

	/**
	 * Strict mode fails closed without selected evidence and returns application-owned no-answer content.
	 */
	public function test_strict_mode_denies_generation_when_no_candidates_are_selected(): void {
		$decision = $this->decide( GroundingMode::STRICT, array() );

		self::assertFalse( $this->property( $decision, 'may_generate' ) );
		self::assertSame( ChatFailureReason::INSUFFICIENT_EVIDENCE, $this->property( $decision, 'reason' ) );
		self::assertSame( self::NO_ANSWER, $this->property( $decision, 'no_answer' ) );
	}

	/**
	 * Strict mode treats low or absent deterministic confidence as insufficient evidence.
	 */
	public function test_strict_mode_denies_generation_for_only_low_confidence_evidence(): void {
		$decision = $this->decide(
			GroundingMode::STRICT,
			array( $this->candidate( new RetrievalConfidence( 0.49, 'low' ) ) )
		);

		self::assertFalse( $this->property( $decision, 'may_generate' ) );
		self::assertSame( ChatFailureReason::INSUFFICIENT_EVIDENCE, $this->property( $decision, 'reason' ) );
		self::assertSame( self::NO_ANSWER, $this->property( $decision, 'no_answer' ) );
	}

	/**
	 * Existing M10 medium/high confidence levels are sufficient for strict generation eligibility.
	 */
	public function test_strict_mode_allows_generation_for_medium_or_high_confidence_evidence(): void {
		foreach (
			array(
				new RetrievalConfidence( 0.50, 'medium' ),
				new RetrievalConfidence( 0.75, 'high' ),
			) as $confidence
		) {
			$decision = $this->decide( GroundingMode::STRICT, array( $this->candidate( $confidence ) ) );

			self::assertTrue( $this->property( $decision, 'may_generate' ) );
			self::assertNull( $this->property( $decision, 'reason' ) );
			self::assertNull( $this->property( $decision, 'no_answer' ) );
		}
	}

	/**
	 * Assisted mode remains eligible even when deterministic retrieval confidence is weak or absent.
	 */
	public function test_assisted_mode_remains_generation_eligible_without_strict_evidence(): void {
		foreach (
			array(
				array(),
				array( $this->candidate( new RetrievalConfidence( 0.10, 'low' ) ) ),
			) as $candidates
		) {
			$decision = $this->decide( GroundingMode::ASSISTED, $candidates );

			self::assertTrue( $this->property( $decision, 'may_generate' ) );
			self::assertNull( $this->property( $decision, 'reason' ) );
			self::assertNull( $this->property( $decision, 'no_answer' ) );
		}
	}

	/**
	 * Grounding cannot expand the upstream hard selected-candidate ceiling.
	 */
	public function test_policy_rejects_more_than_twelve_selected_candidates(): void {
		$this->expectException( InvalidArgumentException::class );

		$candidates = array_fill( 0, 13, $this->candidate( new RetrievalConfidence( 0.75, 'high' ) ) );
		$this->decide( GroundingMode::ASSISTED, $candidates );
	}

	/**
	 * Invoke the Task 6 policy without statically depending on production contracts before RED is proven.
	 *
	 * @param GroundingMode $mode Grounding mode.
	 * @param array         $candidates Selected retrieval candidates.
	 * @phpstan-param list<RetrievalCandidate> $candidates
	 */
	private function decide( GroundingMode $mode, array $candidates ): object {
		$class = 'WpRagAiChatbot\\RAG\\DeterministicGroundingPolicy';
		self::assertTrue( class_exists( $class ), 'DeterministicGroundingPolicy contract is missing.' );

		$policy   = ( new ReflectionClass( $class ) )->newInstance();
		$decision = ( new ReflectionMethod( $class, 'decide' ) )->invoke( $policy, $mode, $candidates );
		self::assertIsObject( $decision );

		return $decision;
	}

	/**
	 * Read one public decision property without statically depending on the RED contract.
	 *
	 * @param object $decision Grounding decision object.
	 * @param string $name Property name.
	 * @return mixed
	 */
	private function property( object $decision, string $name ): mixed {
		return ( new ReflectionProperty( $decision, $name ) )->getValue( $decision );
	}

	/**
	 * Build one selected retrieval candidate using an existing M10 deterministic confidence value.
	 *
	 * @param RetrievalConfidence $confidence Existing deterministic retrieval confidence.
	 */
	private function candidate( RetrievalConfidence $confidence ): RetrievalCandidate {
		return new RetrievalCandidate(
			'chunk-1',
			'document-1',
			1,
			'Grounding evidence.',
			'en',
			'public',
			array(),
			1.0,
			$confidence
		);
	}
}
