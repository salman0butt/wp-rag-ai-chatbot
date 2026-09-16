<?php
/**
 * Administrator knowledge source creation resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use JsonException;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\Sync\KnowledgeSourceSyncJobEnqueuer;
use WpRagAiChatbot\Jobs\Sync\KnowledgeSourceSyncJobPayload;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;
use WpRagAiChatbot\Knowledge\WordPress\WordPressContentGateway;
use WpRagAiChatbot\WooCommerce\Catalog\WooCommerceCatalogGateway;

// phpcs:disable WordPress.NamingConventions -- DTO keys and repository APIs follow the approved domain contract.
// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag
/**
 * Validates, persists, and queues one bounded knowledge source.
 */
final class KnowledgeSourceCreateResource {
	private const MAX_TITLE_BYTES = 200;
	private const MAX_TEXT_BYTES  = 100000;
	private const MAX_FAQ_ITEMS   = 500;
	private const MAX_UPLOAD_SIZE = 10485760;
	private const MAX_PRODUCT_IDS = 100;

	/** The only source types exposed by the first setup flow. */
	private const SOURCE_TYPES = array( 'wordpress_posts', 'manual_text', 'faq', 'woocommerce_product', 'file' );

	/** The fixed server-owned retrieval profile used by Tasks 2–4. */
	private const SEMANTIC_RETRIEVAL = array(
		'collection_id'         => 'wp-rag-default',
		'configuration_id'      => 'gemini-embedding-001-3072-cosine-v1',
		'embedding_provider_id' => 'gemini_direct',
		'embedding_model_id'    => 'gemini-embedding-001',
		'dimensions'            => 3072,
		'normalization'         => 'none',
		'distance'              => 'cosine',
		'vector_store_id'       => 'local-wordpress',
	);

	/**
	 * Create the resource.
	 *
	 * @param KnowledgeSourceRepository $sources Source repository.
	 * @param JobRepository             $jobs Durable queue repository.
	 * @param Clock                     $clock UTC clock.
	 * @param WordPressContentGateway  $wordpress WordPress content authority.
	 * @param WooCommerceCatalogGateway $woocommerce WooCommerce catalog authority.
	 */
	public function __construct(
		private readonly KnowledgeSourceRepository $sources,
		private readonly JobRepository $jobs,
		private readonly Clock $clock,
		private readonly WordPressContentGateway $wordpress,
		private readonly WooCommerceCatalogGateway $woocommerce
	) {
	}

	/**
	 * Create one source from JSON or a multipart file request.
	 *
	 * @param array<string,mixed>      $payload JSON/form fields.
	 * @param array<string,mixed>|null $file Uploaded file field, when creating a file source.
	 * @return array<string,mixed>
	 */
	public function create( array $payload, ?array $file = null ): array {
		$uploaded_path = null;
		$allowed_root  = null;
		try {
			$normalized = $this->normalize( $payload, $file, $uploaded_path, $allowed_root );
		} catch ( SourceValidationException ) {
			$cleaned = self::cleanup_uploaded_file( $uploaded_path, $allowed_root );
			if ( ! $cleaned ) {
				return self::error( 'database_error', 'The knowledge source could not be saved.' );
			}
			return self::error( 'validation_error', 'The knowledge source details are invalid.' );
		}

		try {
			$existing = $this->sources->findByKey( $normalized['source_key'] );
		} catch ( DatabaseException ) {
			self::cleanup_uploaded_file( $uploaded_path, $allowed_root );
			return self::error( 'database_error', 'The knowledge source could not be saved.' );
		}
		if ( null !== $existing ) {
			$cleaned = self::cleanup_uploaded_file( $uploaded_path, $allowed_root );
			if ( ! $cleaned ) {
				return self::error( 'database_error', 'The knowledge source could not be saved.' );
			}
			return self::error( 'conflict', 'A knowledge source with these details already exists.' );
		}

		$now    = $this->clock->now();
		$record = new KnowledgeSourceRecord(
			null,
			$normalized['source_key'],
			$normalized['source_type'],
			$normalized['external_id'],
			$normalized['title'],
			$normalized['canonical_url'],
			'active',
			$normalized['config'],
			$normalized['generation'],
			null,
			$now,
			$now
		);

		$saved = null;
		try {
			$saved = $this->sources->save( $record );
			$job   = ( new KnowledgeSourceSyncJobEnqueuer( $this->jobs, $this->clock ) )->enqueue(
				new KnowledgeSourceSyncJobPayload(
					(int) $saved->id,
					self::SEMANTIC_RETRIEVAL['collection_id'],
					self::SEMANTIC_RETRIEVAL['configuration_id'],
					(string) $saved->sourceHash
				)
			);
		} catch ( DatabaseException ) {
			$compensated = true;
			if ( null !== $saved && null !== $saved->id ) {
				$compensated = $this->delete_source( $saved );
			} else {
				try {
					$raced = $this->sources->findByKey( $normalized['source_key'] );
				} catch ( DatabaseException ) {
					$raced = null;
				}
				if ( null !== $raced ) {
					$cleaned = self::cleanup_uploaded_file( $uploaded_path, $allowed_root );
					if ( ! $cleaned ) {
						return self::error( 'database_error', 'The knowledge source could not be saved.' );
					}
					return self::error( 'conflict', 'A knowledge source with these details already exists.' );
				}
			}
			$cleaned = self::cleanup_uploaded_file( $uploaded_path, $allowed_root );
			if ( ! $compensated || ! $cleaned ) {
				return self::error( 'database_error', 'The knowledge source could not be saved.' );
			}
			return self::error( 'database_error', 'The knowledge source could not be saved.' );
		} catch ( JobQueueException $exception ) {
			$job_compensated    = $this->delete_queued_job( $exception->job_id );
			$source_compensated = $this->delete_source( $saved );
			$cleaned            = self::cleanup_uploaded_file( $uploaded_path, $allowed_root );
			if ( ! $job_compensated || ! $source_compensated || ! $cleaned ) {
				return self::error( 'database_error', 'The knowledge source could not be saved.' );
			}
			return self::error( 'queue_error', 'The knowledge source was saved but could not be queued.' );
		}

		return array(
			'source' => KnowledgeSourceRestResource::project( $saved ),
			'job'    => self::project_job( $job ),
		);
	}

	/**
	 * Normalize the finite request shapes and produce source identities.
	 *
	 * @param array<string,mixed>      $payload Raw request payload.
	 * @param array<string,mixed>|null $file Raw upload field.
	 * @param-out string|null          $uploaded_path Newly moved file path.
	 * @param-out string|null          $allowed_root Owned upload root.
	 * @return array{source_key:string,source_type:string,external_id:string|null,title:string,canonical_url:string|null,config:array<string,mixed>,generation:string}
	 * @throws SourceValidationException When the request is outside the allow-list.
	 */
	private function normalize( array $payload, ?array $file, ?string &$uploaded_path, ?string &$allowed_root ): array {
		$allowed = array( 'source_type', 'type', 'title', 'config' );
		foreach ( array_keys( $payload ) as $key ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				throw new SourceValidationException();
			}
		}

		$source_type = $this->source_type( $payload );
		$config      = $payload['config'] ?? array();
		if ( ! is_array( $config ) || ( array() !== $config && array_is_list( $config ) ) ) {
			throw new SourceValidationException();
		}

		$normalized_config = match ( $source_type ) {
			'wordpress_posts'     => $this->wordpress_config( $config ),
			'manual_text'         => $this->manual_config( $config ),
			'faq'                 => $this->faq_config( $config ),
			'woocommerce_product' => $this->woocommerce_config( $config ),
			'file'                => $this->file_config( $config, $file, $uploaded_path, $allowed_root ),
			default               => throw new SourceValidationException(),
		};

		$title = $this->title( $payload['title'] ?? null );
		if ( 'file' === $source_type ) {
			if ( null === $title ) {
				$title = $normalized_config['title'];
			}
			unset( $normalized_config['title'] );
		}
		if ( null === $title ) {
			$title = match ( $source_type ) {
				'wordpress_posts'     => 'WordPress content',
				'woocommerce_product' => 'WooCommerce products',
				default               => throw new SourceValidationException(),
			};
		}

		$identity_config = $normalized_config;
		if ( 'file' === $source_type ) {
			unset( $identity_config['path'], $identity_config['allowed_root'] );
		}
		$identity = array(
			'source_type' => $source_type,
			'title'       => $title,
			'config'      => $this->canonicalize( $identity_config ),
		);
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- This canonical hash is independent of WordPress escaping behavior.
			$encoded = json_encode( $identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES );
		} catch ( JsonException ) {
			throw new SourceValidationException();
		}

		$generation                   = hash( 'sha256', $encoded );
		$config                       = $normalized_config;
		$config['semantic_retrieval'] = self::SEMANTIC_RETRIEVAL;

		return array(
			'source_key'    => 'source:' . $generation,
			'source_type'   => $source_type,
			'external_id'   => null,
			'title'         => $title,
			'canonical_url' => null,
			'config'        => $config,
			'generation'    => $generation,
		);
	}

	/**
	 * Resolve and validate the single supported source-type field.
	 *
	 * @param array<string,mixed> $payload Raw request payload.
	 * @throws SourceValidationException When the type is not allow-listed.
	 */
	private function source_type( array $payload ): string {
		$has_source_type = array_key_exists( 'source_type', $payload );
		$has_type        = array_key_exists( 'type', $payload );
		if ( $has_source_type === $has_type ) {
			throw new SourceValidationException();
		}

		$source_type = $payload[ $has_source_type ? 'source_type' : 'type' ];
		if ( ! is_string( $source_type ) || ! in_array( $source_type, self::SOURCE_TYPES, true ) ) {
			throw new SourceValidationException();
		}

		return $source_type;
	}

	/**
	 * Normalize WordPress source configuration with private content disabled by default.
	 *
	 * @param array<string,mixed> $config Raw source configuration.
	 * @return array<string,mixed> Normalized configuration.
	 * @throws SourceValidationException When a value is invalid.
	 */
	private function wordpress_config( array $config ): array {
		$this->allow_keys( $config, array( 'post_types', 'include_private' ) );
		$normalized = array( 'include_private' => false );
		if ( array_key_exists( 'include_private', $config ) ) {
			if ( ! is_bool( $config['include_private'] ) ) {
				throw new SourceValidationException();
			}
			$normalized['include_private'] = $config['include_private'];
		}
		if ( array_key_exists( 'post_types', $config ) ) {
			$normalized['post_types'] = $this->post_types( $config['post_types'] );
		}

		return $normalized;
	}

	/**
	 * Normalize manual text configuration.
	 *
	 * @param array<string,mixed> $config Raw source configuration.
	 * @return array<string,mixed> Normalized configuration.
	 * @throws SourceValidationException When a value is invalid.
	 */
	private function manual_config( array $config ): array {
		$this->allow_keys( $config, array( 'text', 'language', 'visibility' ) );
		$text = $config['text'] ?? null;
		if ( ! is_string( $text ) || '' === trim( $text ) || strlen( trim( $text ) ) > self::MAX_TEXT_BYTES ) {
			throw new SourceValidationException();
		}

		return $this->common_text_config( trim( $text ), $config );
	}

	/**
	 * Normalize FAQ configuration, including every row before persistence.
	 *
	 * @param array<string,mixed> $config Raw source configuration.
	 * @return array<string,mixed> Normalized configuration.
	 * @throws SourceValidationException When a value is invalid.
	 */
	private function faq_config( array $config ): array {
		$this->allow_keys( $config, array( 'items', 'language', 'visibility' ) );
		$items = $config['items'] ?? null;
		if ( ! is_array( $items ) || ! array_is_list( $items ) || array() === $items || count( $items ) > self::MAX_FAQ_ITEMS ) {
			throw new SourceValidationException();
		}

		$normalized_items = array();
		foreach ( $items as $item ) {
			$item_keys = is_array( $item ) ? array_keys( $item ) : array();
			sort( $item_keys, SORT_STRING );
			if ( ! is_array( $item ) || array( 'answer', 'question' ) !== $item_keys ) {
				throw new SourceValidationException();
			}
			$question = $item['question'];
			$answer   = $item['answer'];
			if ( ! is_string( $question ) || ! is_string( $answer ) || '' === trim( $question ) || '' === trim( $answer ) || strlen( trim( $question ) ) > self::MAX_TEXT_BYTES || strlen( trim( $answer ) ) > self::MAX_TEXT_BYTES ) {
				throw new SourceValidationException();
			}
			$normalized_items[] = array(
				'question' => trim( $question ),
				'answer'   => trim( $answer ),
			);
		}

		$normalized = array( 'items' => $normalized_items );
		return $this->add_common_config( $normalized, $config );
	}

	/**
	 * Normalize WooCommerce explicit or catalog selection.
	 *
	 * @param array<string,mixed> $config Raw source configuration.
	 * @return array<string,mixed> Normalized configuration.
	 * @throws SourceValidationException When a value is invalid.
	 */
	private function woocommerce_config( array $config ): array {
		$this->allow_keys( $config, array( 'product_ids', 'catalog', 'page_size' ) );
		if ( ! $this->woocommerce->isAvailable() ) {
			throw new SourceValidationException();
		}
		$has_ids     = array_key_exists( 'product_ids', $config );
		$has_catalog = array_key_exists( 'catalog', $config );
		if ( $has_ids === $has_catalog ) {
			throw new SourceValidationException();
		}

		if ( $has_ids ) {
			if ( array_key_exists( 'page_size', $config ) || ! is_array( $config['product_ids'] ) || ! array_is_list( $config['product_ids'] ) || array() === $config['product_ids'] || count( $config['product_ids'] ) > self::MAX_PRODUCT_IDS ) {
				throw new SourceValidationException();
			}
			$ids = array();
			foreach ( $config['product_ids'] as $id ) {
				if ( ! is_int( $id ) || $id < 1 ) {
					throw new SourceValidationException();
				}
				if ( null === $this->woocommerce->product( $id ) ) {
					throw new SourceValidationException();
				}
				$ids[] = $id;
			}
			$ids = array_values( array_unique( $ids ) );
			sort( $ids, SORT_NUMERIC );
			return array( 'product_ids' => $ids );
		}

		if ( true !== $config['catalog'] || ( array_key_exists( 'page_size', $config ) && ( ! is_int( $config['page_size'] ) || $config['page_size'] < 1 || $config['page_size'] > 250 ) ) ) {
			throw new SourceValidationException();
		}

		return array(
			'catalog'   => true,
			'page_size' => $config['page_size'] ?? 100,
		);
	}

	/**
	 * Validate the file-only configuration and store only server-owned file metadata.
	 *
	 * @param array<string,mixed>      $config Raw source configuration.
	 * @param array<string,mixed>|null $file Raw uploaded file.
	 * @param-out string               $uploaded_path Newly moved file path.
	 * @param-out string               $allowed_root_reference Owned upload root.
	 * @return array<string,mixed> Server-owned file configuration.
	 * @throws SourceValidationException When the upload is invalid.
	 */
	private function file_config( array $config, ?array $file, ?string &$uploaded_path, ?string &$allowed_root_reference ): array {
		if ( array() !== $config || null === $file ) {
			throw new SourceValidationException();
		}
		foreach ( array( 'name', 'tmp_name', 'size', 'error' ) as $key ) {
			if ( ! array_key_exists( $key, $file ) ) {
				throw new SourceValidationException();
			}
		}
		if ( ! is_string( $file['name'] ) || '' === $file['name'] || str_contains( $file['name'], '/' ) || str_contains( $file['name'], '\\' ) || str_contains( $file['name'], "\0" ) || ! is_string( $file['tmp_name'] ) || '' === $file['tmp_name'] || UPLOAD_ERR_OK !== $file['error'] || ! is_int( $file['size'] ) || $file['size'] < 1 || $file['size'] > self::MAX_UPLOAD_SIZE ) {
			throw new SourceValidationException();
		}

		$uploads = wp_upload_dir();
		if ( '' === trim( $uploads['basedir'] ) ) {
			throw new SourceValidationException();
		}
		$allowed_root = rtrim( $uploads['basedir'], '/\\' ) . '/wp-rag-ai-chatbot';
		if ( ! wp_mkdir_p( $allowed_root ) ) {
			throw new SourceValidationException();
		}

		$upload_filter = static function ( mixed $directory ) use ( $uploads, $allowed_root ): mixed {
			if ( ! is_array( $directory ) ) {
				return $directory;
			}
			$directory['path']    = $allowed_root;
			$directory['url']     = rtrim( (string) $uploads['baseurl'], '/' ) . '/wp-rag-ai-chatbot';
			$directory['subdir']  = '/wp-rag-ai-chatbot';
			$directory['basedir'] = (string) $uploads['basedir'];
			$directory['baseurl'] = (string) $uploads['baseurl'];
			return $directory;
		};
		add_filter( 'upload_dir', $upload_filter );
		try {
			$result = wp_handle_upload(
				$file,
				array(
					'test_form' => false,
					'mimes'     => array(
						'txt'      => 'text/plain',
						'md'       => 'text/markdown',
						'markdown' => 'text/markdown',
						'html'     => 'text/html',
						'htm'      => 'text/html',
						'csv'      => 'text/csv',
						'json'     => 'application/json',
						'xml'      => 'application/xml',
						'pdf'      => 'application/pdf',
						'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
					),
				)
			);
		} finally {
			remove_filter( 'upload_dir', $upload_filter );
		}

		if ( isset( $result['error'] ) ) {
			throw new SourceValidationException();
		}
		$uploaded_file = $result['file'];
		$path          = realpath( $uploaded_file );
		$root          = realpath( $allowed_root );
		if ( false === $path || false === $root || ! is_file( $path ) || ! str_starts_with( $path, rtrim( $root, '/\\' ) . DIRECTORY_SEPARATOR ) ) {
			throw new SourceValidationException();
		}
		$uploaded_path          = $path;
		$allowed_root_reference = $root;
		$fingerprint            = hash_file( 'sha256', $path );
		if ( false === $fingerprint ) {
			throw new SourceValidationException();
		}

		$file_name = $file['name'] ?? null;
		if ( ! is_string( $file_name ) ) {
			throw new SourceValidationException();
		}

		return array(
			'path'         => $path,
			'allowed_root' => $root,
			'fingerprint'  => $fingerprint,
			'title'        => $this->file_title( $file_name ),
		);
	}

	/**
	 * Validate common text-source options.
	 *
	 * @param string               $text Normalized text.
	 * @param array<string,mixed>  $config Raw source configuration.
	 * @return array<string,mixed> Normalized configuration.
	 */
	private function common_text_config( string $text, array $config ): array {
		$normalized = array( 'text' => $text );
		return $this->add_common_config( $normalized, $config );
	}

	/**
	 * Add only the supported optional language/visibility fields.
	 *
	 * @param array<string,mixed> $normalized Normalized configuration.
	 * @param array<string,mixed> $config Raw source configuration.
	 * @return array<string,mixed> Normalized configuration.
	 * @throws SourceValidationException When a value is invalid.
	 */
	private function add_common_config( array $normalized, array $config ): array {
		if ( array_key_exists( 'language', $config ) ) {
			if ( ! is_string( $config['language'] ) || '' === trim( $config['language'] ) || strlen( trim( $config['language'] ) ) > 32 || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', trim( $config['language'] ) ) ) {
				throw new SourceValidationException();
			}
			$normalized['language'] = trim( $config['language'] );
		}
		$visibility = $config['visibility'] ?? 'public';
		if ( ! is_string( $visibility ) || ! in_array( $visibility, array( 'public', 'private' ), true ) ) {
			throw new SourceValidationException();
		}
		$normalized['visibility'] = $visibility;

		return $normalized;
	}

	/**
	 * Validate a finite list of WordPress post type slugs.
	 *
	 * @param mixed $post_types Raw post type list.
	 * @return list<string> Normalized post type slugs.
	 * @throws SourceValidationException When a value is invalid.
	 */
	private function post_types( mixed $post_types ): array {
		if ( ! is_array( $post_types ) || ! array_is_list( $post_types ) || array() === $post_types ) {
			throw new SourceValidationException();
		}
		$normalized = array();
		foreach ( $post_types as $post_type ) {
			if ( ! is_string( $post_type ) || 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]{0,19}$/', $post_type ) ) {
				throw new SourceValidationException();
			}
			if ( ! in_array( $post_type, $this->wordpress->publicPostTypes(), true ) ) {
				throw new SourceValidationException();
			}
			$normalized[] = $post_type;
		}
		$normalized = array_values( array_unique( $normalized ) );
		sort( $normalized, SORT_STRING );
		return $normalized;
	}

	/**
	 * Reject arbitrary configuration keys at the trust boundary.
	 *
	 * @param array<string,mixed> $config Raw source configuration.
	 * @param list<string>        $allowed Allow-listed keys.
	 * @throws SourceValidationException When an unsupported key is present.
	 */
	private function allow_keys( array $config, array $allowed ): void {
		foreach ( array_keys( $config ) as $key ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				throw new SourceValidationException();
			}
		}
	}

	/**
	 * Normalize a human title, returning null only for optional file titles.
	 *
	 * @param mixed $title Raw title.
	 * @return string|null Normalized title.
	 * @throws SourceValidationException When a value is invalid.
	 */
	private function title( mixed $title ): ?string {
		if ( null === $title ) {
			return null;
		}
		if ( ! is_string( $title ) ) {
			throw new SourceValidationException();
		}
		$normalized = preg_replace( '/\s+/u', ' ', trim( $title ) );
		if ( ! is_string( $normalized ) || '' === $normalized || strlen( $normalized ) > self::MAX_TITLE_BYTES ) {
			throw new SourceValidationException();
		}
		return $normalized;
	}

	/**
	 * Derive a safe title from an upload basename.
	 *
	 * @param string $name Upload basename.
	 * @return string Safe title.
	 * @throws SourceValidationException When the name cannot produce a title.
	 */
	private function file_title( string $name ): string {
		$title      = pathinfo( $name, PATHINFO_FILENAME );
		$normalized = $this->title( $title );
		if ( null === $normalized ) {
			throw new SourceValidationException();
		}
		return $normalized;
	}

	/**
	 * Canonicalize associative arrays for stable source identities.
	 *
	 * @param mixed $value Value to canonicalize.
	 * @return mixed Canonicalized value.
	 */
	private function canonicalize( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_is_list( $value ) ) {
			return array_map( $this->canonicalize( ... ), $value );
		}
		ksort( $value, SORT_STRING );
		foreach ( $value as $key => $item ) {
			$value[ $key ] = $this->canonicalize( $item );
		}
		return $value;
	}

	/** Remove a newly moved file only when it remains inside its server-owned root. */
	private function delete_source( ?KnowledgeSourceRecord $saved ): bool {
		if ( null === $saved || null === $saved->id ) {
			return true;
		}
		try {
			$this->sources->delete( $saved->id );
		} catch ( DatabaseException ) {
			return false;
		}
		return true;
	}

	/** Delete a job identity exposed by a post-insert queue failure. */
	private function delete_queued_job( ?int $job_id ): bool {
		if ( null === $job_id ) {
			return true;
		}
		try {
			$this->jobs->deleteQueued( $job_id );
		} catch ( DatabaseException | JobQueueException ) {
			return false;
		}
		return true;
	}

	/** Remove a newly moved file only when it remains inside its server-owned root. */
	private static function cleanup_uploaded_file( ?string $path, ?string $allowed_root ): bool {
		if ( null === $path || null === $allowed_root ) {
			return true;
		}
		$real_path = realpath( $path );
		$real_root = realpath( $allowed_root );
		if ( false === $real_path ) {
			return true;
		}
		if ( false === $real_root || ! is_file( $real_path ) || ! str_starts_with( $real_path, rtrim( $real_root, '/\\' ) . DIRECTORY_SEPARATOR ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- The path was just verified inside the plugin-owned upload root.
		return unlink( $real_path );
	}

	/**
	 * Project one bounded, non-secret job DTO.
	 *
	 * @param JobRecord $record Persisted job record.
	 * @return array<string,mixed> Safe job DTO.
	 */
	private static function project_job( JobRecord $record ): array {
		return array(
			'job_key'             => $record->job_key,
			'type'                => $record->type,
			'status'              => $record->status->value,
			'attempts'            => $record->attempts,
			'max_attempts'        => $record->max_attempts,
			'available_at'        => $record->available_at->format( DATE_ATOM ),
			'cancel_requested_at' => $record->cancel_requested_at?->format( DATE_ATOM ),
			'progress_current'    => $record->progress_current,
			'progress_total'      => $record->progress_total,
			'progress_message'    => $record->progress_message,
			'last_error_code'     => $record->last_error_code,
			'last_error_message'  => $record->last_error_message,
			'started_at'          => $record->started_at?->format( DATE_ATOM ),
			'completed_at'        => $record->completed_at?->format( DATE_ATOM ),
			'created_at'          => $record->created_at->format( DATE_ATOM ),
			'updated_at'          => $record->updated_at->format( DATE_ATOM ),
		);
	}

	/**
	 * Return one stable safe create error.
	 *
	 * @param string $code Stable error code.
	 * @param string $message Safe message.
	 * @return array{error:array{code:string,message:string}} Error DTO.
	 */
	private static function error( string $code, string $message ): array {
		return array(
			'error' => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile
/** Internal marker for bounded source validation failures. */
final class SourceValidationException extends \InvalidArgumentException {
}
// phpcs:enable Generic.Files.OneObjectStructurePerFile
// phpcs:enable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag
// phpcs:enable WordPress.NamingConventions
