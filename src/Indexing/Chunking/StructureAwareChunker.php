<?php
/**
 * Deterministic structure-aware document chunker.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Indexing\Chunking;

use WpRagAiChatbot\Documents\DocumentHasher;
use WpRagAiChatbot\Documents\DocumentRecord;

// phpcs:disable WordPress.NamingConventions -- Domain record and configuration properties intentionally use the approved camelCase contract.
/**
 * Splits canonical document text at heading, paragraph, sentence and lexical boundaries.
 */
final class StructureAwareChunker {
	/**
	 * Create the chunker.
	 *
	 * @param TokenCounter   $counter Deterministic token-budget counter.
	 * @param ChunkingConfig $config Chunking configuration.
	 */
	public function __construct(
		private TokenCounter $counter,
		private ChunkingConfig $config
	) {
	}

	/**
	 * Split one canonical document into deterministic immutable chunk records.
	 *
	 * @param DocumentRecord $document Canonical document.
	 * @return array<int, ChunkRecord>
	 * @throws ChunkingException When content cannot be safely split to the configured budget.
	 */
	public function chunks( DocumentRecord $document ): array {
		$content = trim( $document->content );
		if ( '' === $content ) {
			return array();
		}

		$descriptors       = array();
		$section_sequences = array();
		foreach ( $this->structured_paragraphs( $content ) as $paragraph ) {
			$section_id = $paragraph['section_id'];
			foreach ( $this->split_to_budget( $paragraph['content'] ) as $piece ) {
				$section_sequence = $section_sequences[ $section_id ] ?? 0;
				$descriptors[]    = array(
					'content'          => $piece,
					'heading_path'     => $paragraph['heading_path'],
					'section_id'       => $section_id,
					'section_sequence' => $section_sequence,
				);
				$section_sequences[ $section_id ] = $section_sequence + 1;
			}
		}
		$descriptors = $this->apply_overlap( $descriptors );

		$chunks      = array();
		$fingerprint = $this->config->fingerprint();
		foreach ( $descriptors as $sequence => $descriptor ) {
			$heading_path = $descriptor['heading_path'];
			$parent_key   = DocumentHasher::hash(
				array(
					'document_key'         => $document->documentKey,
					'chunking_fingerprint' => $fingerprint,
					'heading_path'         => $heading_path,
					'section_id'           => $descriptor['section_id'],
				)
			);
			$chunk_key    = DocumentHasher::hash(
				array(
					'document_key'         => $document->documentKey,
					'chunking_fingerprint' => $fingerprint,
					'structural_path'      => $heading_path,
					'section_id'           => $descriptor['section_id'],
					'section_sequence'     => $descriptor['section_sequence'],
				)
			);
			$content_hash = DocumentHasher::hash(
				array(
					'document_key'  => $document->documentKey,
					'source_id'     => $document->sourceId,
					'canonical_url' => $document->canonicalUrl,
					'heading_path'  => $heading_path,
					'parent_key'    => $parent_key,
					'content'       => $descriptor['content'],
				)
			);
			$token_count  = $this->counter->count( $descriptor['content'] );

			if ( $token_count < 1 || $token_count > $this->config->maxTokens ) {
				throw new ChunkingException( 'Chunk content violates the configured token budget.' );
			}

			$chunks[] = new ChunkRecord(
				$chunk_key,
				$document->documentKey,
				$document->sourceId,
				$document->documentType,
				$document->title,
				$document->canonicalUrl,
				$descriptor['content'],
				$content_hash,
				$document->sourceVersion,
				$document->contentHash,
				$document->language,
				$document->visibility,
				$sequence,
				$parent_key,
				$heading_path,
				$token_count,
				$this->config->chunkingVersion,
				$fingerprint,
				$this->config->embeddingCompatibilityKey,
				$document->metadata
			);
		}

		return $chunks;
	}

	/**
	 * Apply configured overlap between adjacent chunks in one structural parent.
	 *
	 * @param array<int, array{content:string, heading_path:array<int, string>, section_id:int, section_sequence:int}> $descriptors Base chunk descriptors.
	 * @return array<int, array{content:string, heading_path:array<int, string>, section_id:int, section_sequence:int}>
	 * @throws ChunkingException When overlap source content is not valid UTF-8.
	 */
	private function apply_overlap( array $descriptors ): array {
		if ( 0 === $this->config->overlapTokens || count( $descriptors ) < 2 ) {
			return $descriptors;
		}

		$result   = array();
		$previous = null;
		foreach ( $descriptors as $descriptor ) {
			$content = $descriptor['content'];

			if (
				null !== $previous
				&& $previous['heading_path'] === $descriptor['heading_path']
				&& $previous['section_id'] === $descriptor['section_id']
			) {
				$available_tokens = $this->config->maxTokens - $this->counter->count( $content );
				$overlap_tokens   = min( $this->config->overlapTokens, $available_tokens );

				for ( $limit = $overlap_tokens; $limit > 0; --$limit ) {
					$overlap = $this->trailing_lexical_units( $previous['content'], $limit );
					if ( '' === $overlap ) {
						break;
					}
					if ( $this->counter->count( $overlap ) > $this->config->overlapTokens ) {
						continue;
					}

					$candidate = $overlap . ' ' . $content;
					if ( $this->counter->count( $candidate ) <= $this->config->maxTokens ) {
						$content = $candidate;
						break;
					}
				}
			}

			$result[] = array(
				'content'          => $content,
				'heading_path'     => $descriptor['heading_path'],
				'section_id'       => $descriptor['section_id'],
				'section_sequence' => $descriptor['section_sequence'],
			);
			$previous = $descriptor;
		}

		return $result;
	}

	/**
	 * Copy at most the requested trailing Unicode lexical units from one chunk.
	 *
	 * @param string $text Source chunk content.
	 * @param int    $limit Maximum lexical units to copy.
	 * @throws ChunkingException When source content is not valid UTF-8.
	 */
	private function trailing_lexical_units( string $text, int $limit ): string {
		$matched = preg_match_all(
			'/[\p{L}\p{N}]+|[^\s\p{L}\p{N}]/u',
			$text,
			$matches,
			PREG_OFFSET_CAPTURE
		);
		if ( false === $matched ) {
			throw new ChunkingException( 'Document content is not valid UTF-8.' );
		}
		if ( 0 === $matched || $limit < 1 ) {
			return '';
		}

		$tokens     = $matches[0];
		$start      = max( 0, count( $tokens ) - $limit );
		$start_byte = $tokens[ $start ][1];

		return trim( substr( $text, $start_byte ) );
	}

	/**
	 * Parse ATX headings and blank-line-delimited paragraphs.
	 *
	 * @param string $content Canonical document text.
	 * @return array<int, array{content:string, heading_path:array<int, string>, section_id:int}>
	 * @throws ChunkingException When content is not valid UTF-8.
	 */
	private function structured_paragraphs( string $content ): array {
		$lines = preg_split( '/\n/u', $content );
		if ( false === $lines ) {
			throw new ChunkingException( 'Document content is not valid UTF-8.' );
		}

		$result       = array();
		$heading_path = array();
		$paragraph    = array();
		$section_id   = 0;

		foreach ( $lines as $line ) {
			if ( 1 === preg_match( '/^(#{1,6})[ \t]+(.+?)\s*$/u', $line, $matches ) ) {
				$descriptor = $this->paragraph_descriptor( $paragraph, $heading_path, $section_id );
				if ( null !== $descriptor ) {
					$result[] = $descriptor;
				}
				$paragraph      = array();
				$level          = strlen( $matches[1] );
				$heading_path   = array_slice( $heading_path, 0, $level - 1 );
				$heading_path[] = trim( $matches[2] );
				++$section_id;
				continue;
			}

			if ( '' === trim( $line ) ) {
				$descriptor = $this->paragraph_descriptor( $paragraph, $heading_path, $section_id );
				if ( null !== $descriptor ) {
					$result[] = $descriptor;
				}
				$paragraph = array();
				continue;
			}

			$paragraph[] = $line;
		}

		$descriptor = $this->paragraph_descriptor( $paragraph, $heading_path, $section_id );
		if ( null !== $descriptor ) {
			$result[] = $descriptor;
		}

		return $result;
	}

	/**
	 * Build one paragraph descriptor if the pending paragraph has content.
	 *
	 * @param array<int, string> $paragraph Pending paragraph lines.
	 * @param array<int, string> $heading_path Current heading lineage.
	 * @param int                $section_id Deterministic section-instance identifier.
	 * @return array{content:string, heading_path:array<int, string>, section_id:int}|null
	 */
	private function paragraph_descriptor( array $paragraph, array $heading_path, int $section_id ): ?array {
		if ( array() === $paragraph ) {
			return null;
		}

		$text = trim( implode( "\n", $paragraph ) );
		if ( '' === $text ) {
			return null;
		}

		return array(
			'content'      => $text,
			'heading_path' => array_values( $heading_path ),
			'section_id'   => $section_id,
		);
	}

	/**
	 * Split one paragraph until every piece fits the configured budget.
	 *
	 * @param string $text Paragraph text.
	 * @return array<int, string>
	 * @throws ChunkingException When content is not valid UTF-8 or cannot fit the budget.
	 */
	private function split_to_budget( string $text ): array {
		if ( $this->counter->count( $text ) <= $this->config->maxTokens ) {
			return array( $text );
		}

		$sentences = preg_split( '/(?<=[.!?])\s+/u', trim( $text ) );
		if ( false === $sentences ) {
			throw new ChunkingException( 'Document content is not valid UTF-8.' );
		}

		if ( count( $sentences ) < 2 ) {
			return $this->split_lexically( $text );
		}

		$result  = array();
		$current = '';
		foreach ( $sentences as $sentence ) {
			if ( $this->counter->count( $sentence ) > $this->config->maxTokens ) {
				if ( '' !== $current ) {
					$result[] = $current;
					$current  = '';
				}
				array_push( $result, ...$this->split_lexically( $sentence ) );
				continue;
			}

			$candidate = '' === $current ? $sentence : $current . ' ' . $sentence;
			if ( $this->counter->count( $candidate ) <= $this->config->maxTokens ) {
				$current = $candidate;
				continue;
			}

			$result[] = $current;
			$current  = $sentence;
		}

		if ( '' !== $current ) {
			$result[] = $current;
		}

		return $result;
	}

	/**
	 * Split an oversized sentence/block on Unicode lexical-unit boundaries.
	 *
	 * @param string $text Oversized text.
	 * @return array<int, string>
	 * @throws ChunkingException When content is not valid UTF-8 or cannot fit the budget.
	 */
	private function split_lexically( string $text ): array {
		$matched = preg_match_all(
			'/[\p{L}\p{N}]+|[^\s\p{L}\p{N}]/u',
			$text,
			$matches,
			PREG_OFFSET_CAPTURE
		);
		if ( false === $matched ) {
			throw new ChunkingException( 'Document content is not valid UTF-8.' );
		}
		if ( 0 === $matched ) {
			return array();
		}

		$tokens = $matches[0];
		$result = array();
		$count  = count( $tokens );
		for ( $start = 0; $start < $count; $start += $this->config->maxTokens ) {
			$end_index  = min( $start + $this->config->maxTokens, $count );
			$start_byte = $tokens[ $start ][1];
			$end_byte   = $end_index < $count ? $tokens[ $end_index ][1] : strlen( $text );
			$piece      = trim( substr( $text, $start_byte, $end_byte - $start_byte ) );

			if ( '' === $piece ) {
				continue;
			}
			if ( $this->counter->count( $piece ) > $this->config->maxTokens ) {
				array_push( $result, ...$this->split_code_point_safe( $piece ) );
				continue;
			}
			$result[] = $piece;
		}

		return $result;
	}

	/**
	 * Last-resort code-point-safe fallback for an injected counter with larger units.
	 *
	 * @param string $text Oversized lexical piece.
	 * @return array<int, string>
	 * @throws ChunkingException When content is not valid UTF-8 or a code point exceeds the budget.
	 */
	private function split_code_point_safe( string $text ): array {
		$characters = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( false === $characters ) {
			throw new ChunkingException( 'Document content is not valid UTF-8.' );
		}

		$result  = array();
		$current = '';
		foreach ( $characters as $character ) {
			$candidate = $current . $character;
			if ( $this->counter->count( $candidate ) <= $this->config->maxTokens ) {
				$current = $candidate;
				continue;
			}
			if ( '' === $current ) {
				throw new ChunkingException( 'A single Unicode code point exceeds the configured token budget.' );
			}
			$result[] = trim( $current );
			$current  = $character;
		}

		if ( '' !== trim( $current ) ) {
			$result[] = trim( $current );
		}

		return $result;
	}
}
// phpcs:enable WordPress.NamingConventions