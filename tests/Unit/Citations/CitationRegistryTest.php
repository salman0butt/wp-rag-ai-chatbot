<?php
/**
 * Citation registry tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Citations;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;

/**
 * Specifies deterministic bounded citations over final retrieval candidates.
 */
final class CitationRegistryTest extends TestCase {
	/**
	 * Citation contracts must exist before chat orchestration can expose grounded sources.
	 */
	public function test_citation_registry_contracts_exist(): void {
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Citations\\Citation' ), 'Citation contract is missing.' );
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Citations\\CitationRegistry' ), 'CitationRegistry contract is missing.' );
	}

	/**
	 * Registry IDs follow final context order and preserve canonical candidate lineage.
	 */
	public function test_registry_assigns_deterministic_ids_and_preserves_lineage(): void {
		$registry_class = 'WpRagAiChatbot\\Citations\\CitationRegistry';
		self::assertTrue( class_exists( $registry_class ), 'CitationRegistry contract is missing.' );

		$registry  = ( new ReflectionClass( $registry_class ) )
			->getMethod( 'from_candidates' )
			->invoke(
				null,
				array(
					$this->candidate( 'chunk-b', 'document-b', 22 ),
					$this->candidate( 'chunk-a', 'document-a', 11 ),
				)
			);
		$citations = $registry->all();

		self::assertCount( 2, $citations );
		self::assertSame( 'C1', $citations[0]->id );
		self::assertSame( 'chunk-b', $citations[0]->chunk_id );
		self::assertSame( 'document-b', $citations[0]->document_id );
		self::assertSame( 22, $citations[0]->source_id );
		self::assertNull( $citations[0]->canonical_url );
		self::assertSame( 'C2', $citations[1]->id );
		self::assertSame( 'chunk-a', $citations[1]->chunk_id );
		self::assertSame( 'document-a', $citations[1]->document_id );
		self::assertSame( 11, $citations[1]->source_id );
	}

	/**
	 * A registry cannot exceed the same twelve-candidate ceiling as M11 context.
	 */
	public function test_registry_rejects_more_than_twelve_candidates(): void {
		$registry_class = 'WpRagAiChatbot\\Citations\\CitationRegistry';
		self::assertTrue( class_exists( $registry_class ), 'CitationRegistry contract is missing.' );

		$candidates = array();
		for ( $index = 1; $index <= 13; ++$index ) {
			$candidates[] = $this->candidate( 'chunk-' . $index, 'document-' . $index, $index );
		}

		$this->expectException( InvalidArgumentException::class );
		( new ReflectionClass( $registry_class ) )->getMethod( 'from_candidates' )->invoke( null, $candidates );
	}

	/**
	 * Build one valid M10 retrieval candidate fixture.
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
