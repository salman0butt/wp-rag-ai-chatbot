<?php
/**
 * WooCommerce product knowledge source.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Knowledge\Sources;

use JsonException;
use WpRagAiChatbot\Documents\DocumentHasher;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceCatalogGateway;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceProduct;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceVariation;

/**
 * Normalizes stable public WooCommerce product snapshots into canonical documents.
 */
final class WooCommerceProductSource implements KnowledgeSource {
	private const DEFAULT_PAGE_SIZE = 100;
	private const MAX_PAGE_SIZE     = 250;

	/**
	 * Create the WooCommerce product source.
	 *
	 * @param WooCommerceCatalogGateway $gateway Optional-safe WooCommerce catalog gateway.
	 */
	public function __construct( private WooCommerceCatalogGateway $gateway ) {
	}

	/** Return the stable source type. */
	public function type(): string {
		return 'woocommerce_product';
	}

	/**
	 * Normalize selected WooCommerce products into canonical documents.
	 *
	 * @param KnowledgeSourceRecord $source Persisted WooCommerce source.
	 * @return iterable<int, DocumentRecord>
	 * @throws KnowledgeSourceException When source configuration or hashing is invalid.
	 */
	public function documents( KnowledgeSourceRecord $source ): iterable {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Domain records intentionally expose approved camelCase properties.
		if ( $this->type() !== $source->sourceType ) {
			throw new KnowledgeSourceException( 'WooCommerce product source type does not match.' );
		}
		if ( null === $source->id || $source->id < 1 ) {
			throw new KnowledgeSourceException( 'WooCommerce product source must be persisted before normalization.' );
		}

		$selection = $this->selection( $source );
		if ( ! $this->gateway->isAvailable() ) {
			return;
		}

		if ( 'explicit' === $selection['mode'] ) {
			foreach ( $selection['ids'] as $product_id ) {
				$product = $this->gateway->product( $product_id );
				if ( null !== $product ) {
					yield $this->document( $source, $product );
				}
			}
			return;
		}

		$page = 1;
		$seen = array();
		do {
			$product_ids = $this->gateway->productIds( $page, $selection['page_size'] );
			$page_count  = count( $product_ids );
			foreach ( $product_ids as $product_id ) {
				if ( ! is_int( $product_id ) || $product_id < 1 ) {
					throw new KnowledgeSourceException( 'WooCommerce catalog returned an invalid product ID.' );
				}
				if ( isset( $seen[ $product_id ] ) ) {
					continue;
				}
				$seen[ $product_id ] = true;

				$product = $this->gateway->product( $product_id );
				if ( null !== $product ) {
					yield $this->document( $source, $product );
				}
			}

			++$page;
		} while ( $page_count >= $selection['page_size'] );
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Resolve and validate one deterministic product selection mode.
	 *
	 * @param KnowledgeSourceRecord $source Source configuration.
	 * @return array{mode:string,ids:array<int,int>,page_size:int}
	 * @throws KnowledgeSourceException When source selection is malformed or ambiguous.
	 */
	private function selection( KnowledgeSourceRecord $source ): array {
		$has_product_ids = array_key_exists( 'product_ids', $source->config );
		$has_catalog     = array_key_exists( 'catalog', $source->config );

		if ( $has_product_ids && $has_catalog ) {
			throw new KnowledgeSourceException( 'WooCommerce product source selection is ambiguous.' );
		}

		if ( $has_product_ids ) {
			if ( array_key_exists( 'page_size', $source->config ) ) {
				throw new KnowledgeSourceException( 'WooCommerce product source page_size is only valid in catalog mode.' );
			}

			$configured_ids = $source->config['product_ids'];
			if ( ! is_array( $configured_ids ) || ! array_is_list( $configured_ids ) || array() === $configured_ids ) {
				throw new KnowledgeSourceException( 'WooCommerce product source product_ids must be a non-empty list.' );
			}

			$product_ids = array();
			foreach ( $configured_ids as $product_id ) {
				if ( ! is_int( $product_id ) || $product_id < 1 ) {
					throw new KnowledgeSourceException( 'WooCommerce product source contains an invalid product ID.' );
				}
				$product_ids[] = $product_id;
			}

			$product_ids = array_values( array_unique( $product_ids ) );
			sort( $product_ids, SORT_NUMERIC );
			return array(
				'mode'      => 'explicit',
				'ids'       => $product_ids,
				'page_size' => self::DEFAULT_PAGE_SIZE,
			);
		}

		if ( ! $has_catalog || true !== $source->config['catalog'] ) {
			throw new KnowledgeSourceException( 'WooCommerce product source requires explicit product_ids or catalog mode.' );
		}

		$page_size = $source->config['page_size'] ?? self::DEFAULT_PAGE_SIZE;
		if ( ! is_int( $page_size ) || $page_size < 1 || $page_size > self::MAX_PAGE_SIZE ) {
			throw new KnowledgeSourceException( 'WooCommerce product source page_size must be between 1 and 250.' );
		}

		return array(
			'mode'      => 'catalog',
			'ids'       => array(),
			'page_size' => $page_size,
		);
	}

	/**
	 * Build one canonical product document.
	 *
	 * @param KnowledgeSourceRecord $source Persisted source.
	 * @param WooCommerceProduct    $product Stable product snapshot.
	 * @throws KnowledgeSourceException When hashing fails.
	 */
	private function document( KnowledgeSourceRecord $source, WooCommerceProduct $product ): DocumentRecord {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved domain records intentionally use camelCase public properties.
		$document_key   = 'woocommerce_product:' . $product->id;
		$external_id    = (string) $product->id;
		$content        = $this->content( $product );
		$metadata       = $this->metadata( $product );
		$source_version = $product->modifiedGmt . ':' . $product->id;

		try {
			$content_hash = DocumentHasher::hash(
				array(
					'document_key'   => $document_key,
					'external_id'    => $external_id,
					'document_type'  => $this->type(),
					'title'          => $product->name,
					'canonical_url'  => $product->canonicalUrl,
					'content'        => $content,
					'metadata'       => $metadata,
					'source_version' => $source_version,
					'language'       => null,
					'visibility'     => 'public',
				)
			);
		} catch ( JsonException $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Previous Throwable is not rendered output.
			throw new KnowledgeSourceException( 'WooCommerce product source could not be hashed.', 0, $exception );
		}

		return new DocumentRecord(
			null,
			$document_key,
			(int) $source->id,
			$external_id,
			$this->type(),
			$product->name,
			$product->canonicalUrl,
			$content,
			$metadata,
			$source_version,
			$content_hash,
			null,
			'public',
			$source->updatedAt,
			$source->updatedAt
		);
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Build deterministic readable product content.
	 *
	 * @param WooCommerceProduct $product Stable product snapshot.
	 */
	private function content( WooCommerceProduct $product ): string {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved product record intentionally uses camelCase public properties.
		$sections = array( $this->normalizeText( $product->name ) );
		if ( null !== $product->sku ) {
			$sections[] = 'SKU: ' . $product->sku;
		}
		foreach ( array( $product->shortDescription, $product->description ) as $description ) {
			$description = $this->normalizeText( $description );
			if ( '' !== $description ) {
				$sections[] = $description;
			}
		}

		$details = array();
		if ( array() !== $product->categories ) {
			$details[] = 'Categories: ' . implode( ', ', $product->categories );
		}
		if ( array() !== $product->tags ) {
			$details[] = 'Tags: ' . implode( ', ', $product->tags );
		}
		if ( array() !== $product->attributes ) {
			$details[] = 'Attributes:';
			foreach ( $product->attributes as $name => $values ) {
				$details[] = $name . ': ' . implode( ', ', $values );
			}
		}
		if ( array() !== $product->variations ) {
			$details[] = 'Variations:';
			foreach ( $product->variations as $variation ) {
				$details[] = $this->variationLine( $variation );
			}
		}
		if ( array() !== $details ) {
			$sections[] = implode( "\n", $details );
		}

		return implode( "\n\n", $sections );
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Return the strict metadata allowlist for one product.
	 *
	 * @param WooCommerceProduct $product Stable product snapshot.
	 * @return array<string, mixed>
	 */
	private function metadata( WooCommerceProduct $product ): array {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Approved product record intentionally uses camelCase public properties.
		$variations = array_map(
			static fn ( WooCommerceVariation $variation ): array => array(
				'id'         => $variation->id,
				'sku'        => $variation->sku,
				'attributes' => $variation->attributes,
			),
			$product->variations
		);

		return array(
			'source_type'        => $this->type(),
			'product_id'         => $product->id,
			'product_type'       => $product->type,
			'product_status'     => $product->status,
			'catalog_visibility' => $product->catalogVisibility,
			'sku'                => $product->sku,
			'categories'         => $product->categories,
			'tags'               => $product->tags,
			'attributes'         => $product->attributes,
			'variations'         => $variations,
		);
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Build one deterministic variation descriptor line.
	 *
	 * @param WooCommerceVariation $variation Stable variation snapshot.
	 */
	private function variationLine( WooCommerceVariation $variation ): string {
		$parts = array( (string) $variation->id );
		if ( null !== $variation->sku ) {
			$parts[] = 'SKU: ' . $variation->sku;
		}
		foreach ( $variation->attributes as $name => $value ) {
			$parts[] = $name . ': ' . $value;
		}

		return implode( ' | ', $parts );
	}

	/**
	 * Normalize surrounding whitespace and line endings without changing meaning.
	 *
	 * @param string $text Text value.
	 */
	private function normalizeText( string $text ): string {
		return trim( str_replace( array( "\r\n", "\r" ), "\n", $text ) );
	}
}
