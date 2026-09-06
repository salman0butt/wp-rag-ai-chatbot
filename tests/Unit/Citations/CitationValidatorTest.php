<?php
/**
 * Citation validator tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Citations;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;

/**
 * Specifies fail-closed citation validation over the request-local registry.
 */
final class CitationValidatorTest extends TestCase {
	/**
	 * Citation validation contracts must exist before orchestration consumes them.
	 */
	public function test_citation_validation_contracts_exist(): void {
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Citations\\CitationValidationResult' ), 'CitationValidationResult contract is missing.' );
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Citations\\CitationValidator' ), 'CitationValidator contract is missing.' );
	}

	/**
	 * Known markers resolve only to registry citations in answer order.
	 */
	public function test_validator_accepts_known_markers_and_resolves_registry_lineage(): void {
		$registry = $this->registry();
		$result   = $this->validator()->validate( 'Use the first source [C1] and then [C2].', $registry );

		self::assertTrue( $result->valid );
		self::assertSame( array(), $result->invalid_markers );
		self::assertCount( 2, $result->citations );
		self::assertSame( 'C1', $result->citations[0]->id );
		self::assertSame( 'chunk-1', $result->citations[0]->chunk_id );
		self::assertSame( 'C2', $result->citations[1]->id );
		self::assertSame( 'chunk-2', $result->citations[1]->chunk_id );
	}

	/**
	 * Unknown, duplicate, and malformed citation markers fail closed.
	 */
	public function test_validator_rejects_unknown_duplicate_and_malformed_markers(): void {
		$registry  = $this->registry();
		$validator = $this->validator();

		$unknown = $validator->validate( 'Unsupported source [C9].', $registry );
		self::assertFalse( $unknown->valid );
		self::assertSame( array( 'C9' ), $unknown->invalid_markers );

		$duplicate = $validator->validate( 'Repeated [C1] and [C1].', $registry );
		self::assertFalse( $duplicate->valid );
		self::assertSame( array( 'C1' ), $duplicate->invalid_markers );

		$malformed = $validator->validate( 'Malformed [C01] marker.', $registry );
		self::assertFalse( $malformed->valid );
		self::assertSame( array( 'C01' ), $malformed->invalid_markers );
	}

	/**
	 * A model-authored URL never becomes authoritative citation metadata.
	 */
	public function test_validator_does_not_trust_model_authored_urls(): void {
		$result = $this->validator()->validate( 'See [C1](https://evil.example/forged).', $this->registry() );

		self::assertTrue( $result->valid );
		self::assertCount( 1, $result->citations );
		self::assertSame( 'C1', $result->citations[0]->id );
		self::assertNull( $result->citations[0]->canonical_url );
	}

	/**
	 * Build the request-local registry using the public Task 4 contract.
	 */
	private function registry(): object {
		$registry_class = 'WpRagAiChatbot\\Citations\\CitationRegistry';
		self::assertTrue( class_exists( $registry_class ), 'CitationRegistry contract is missing.' );

		return ( new ReflectionClass( $registry_class ) )
			->getMethod( 'from_candidates' )
			->invoke(
				null,
				array(
					$this->candidate( 'chunk-1', 'document-1', 1 ),
					$this->candidate( 'chunk-2', 'document-2', 2 ),
				)
			);
	}

	/**
	 * Build the Task 4 validator through its public contract.
	 */
	private function validator(): object {
		$validator_class = 'WpRagAiChatbot\\Citations\\CitationValidator';
		self::assertTrue( class_exists( $validator_class ), 'CitationValidator contract is missing.' );

		return ( new ReflectionClass( $validator_class ) )->newInstance();
	}

	/**
	 * Build one valid retrieval candidate fixture.
	 *
	 * @param string $chunk_id Chunk identifier.
	 * @param string $document_id Document identifier.
	 * @param int    $source_id Source identifier.
	 */
	private function candidate( string $chunk_id, string $document_id, int $source_id ): RetrievalCandidate {
		return new RetrievalCandidate(
			$chunk_id,
			$document_id,
			$source_id,
			'Grounded evidence.',
			'en',
			'public',
			array(),
			1.0
		);
	}
}
