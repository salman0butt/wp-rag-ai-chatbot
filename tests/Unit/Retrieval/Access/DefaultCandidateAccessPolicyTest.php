<?php
/**
 * Post-fusion candidate access policy tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Retrieval\Access;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Retrieval\Access\CandidateAccessPolicy;
use WpRagAiChatbot\Retrieval\Access\DefaultCandidateAccessPolicy;
use WpRagAiChatbot\Retrieval\ChannelEvidence;
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;

/**
 * Defines the fail-closed post-fusion access boundary for M10.
 */
final class DefaultCandidateAccessPolicyTest extends TestCase {
	/**
	 * Candidates matching every trusted scope constraint are allowed.
	 */
	public function test_matching_candidate_is_allowed(): void {
		$policy = new DefaultCandidateAccessPolicy();

		self::assertInstanceOf( CandidateAccessPolicy::class, $policy );
		self::assertTrue(
			$policy->allows(
				$this->candidate(),
				new RetrievalFilter( 'public', 'en', array( 8 ), array( 'doc-allowed' ) )
			)
		);
	}

	/**
	 * Any trusted lineage mismatch fails closed after fusion.
	 */
	public function test_lineage_mismatch_is_rejected_fail_closed(): void {
		$policy    = new DefaultCandidateAccessPolicy();
		$candidate = $this->candidate();

		self::assertFalse( $policy->allows( $candidate, new RetrievalFilter( 'private' ) ) );
		self::assertFalse( $policy->allows( $candidate, new RetrievalFilter( null, 'fr' ) ) );
		self::assertFalse( $policy->allows( $candidate, new RetrievalFilter( null, null, array( 99 ) ) ) );
		self::assertFalse( $policy->allows( $candidate, new RetrievalFilter( null, null, array(), array( 'other-doc' ) ) ) );
	}

	/**
	 * Create a valid fused candidate fixture.
	 */
	private function candidate(): RetrievalCandidate {
		$evidence = new ChannelEvidence( 'semantic', 0.9, 1, 1.0, 1.0 / 61.0 );

		return new RetrievalCandidate(
			'chunk-allowed',
			'doc-allowed',
			8,
			'Allowed candidate content.',
			'en',
			'public',
			array( $evidence ),
			1.0 / 61.0
		);
	}
}
