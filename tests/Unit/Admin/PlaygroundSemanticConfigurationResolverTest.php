<?php
/**
 * M13 persisted Playground semantic configuration resolver tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;
use WpRagAiChatbot\Admin\Rest\PlaygroundConfiguration;
use WpRagAiChatbot\Admin\Rest\PlaygroundRetrievalConfiguration;
use WpRagAiChatbot\Admin\Rest\PlaygroundSemanticConfigurationResolver;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;

/**
 * Proves semantic runtime identity is recovered only from explicit persisted source configuration.
 */
final class PlaygroundSemanticConfigurationResolverTest extends TestCase {
	/** Explicit persisted semantic configuration must resolve without runtime defaults. */
	public function test_resolves_explicit_persisted_semantic_configuration(): void {
		$resolved        = ( new PlaygroundSemanticConfigurationResolver() )->resolve(
			$this->configuration(
				array(
					'semantic_retrieval' => array(
						'embedding_provider_id' => 'openai',
						'embedding_model_id'    => 'text-embedding-3-small',
						'dimensions'            => 1536,
						'normalization'         => 'l2',
						'distance'              => 'cosine',
						'vector_store_id'       => 'local',
					),
				)
			)
		);
		$resolved_fields = get_object_vars( $resolved );

		self::assertSame( 'openai', $resolved->embedding_profile->provider_id );
		self::assertSame( 'text-embedding-3-small', $resolved->embedding_profile->model_id );
		self::assertSame( 1536, $resolved->embedding_profile->dimensions );
		self::assertSame( NormalizationMode::L2, $resolved->embedding_profile->normalization );
		self::assertArrayHasKey( 'distance', $resolved_fields );
		self::assertSame( DistanceMetric::COSINE, $resolved_fields['distance'] );
		self::assertSame( 'local', $resolved->vector_store_id );
	}

	/** Missing persisted semantic configuration must fail closed rather than choose defaults. */
	public function test_rejects_missing_semantic_configuration(): void {
		$this->expectException( UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Persisted semantic retrieval configuration is missing.' );

		( new PlaygroundSemanticConfigurationResolver() )->resolve( $this->configuration( array() ) );
	}

	/** Invalid persisted normalization must fail before any runtime dependency is constructed. */
	public function test_rejects_invalid_persisted_normalization(): void {
		$this->expectException( UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Persisted semantic retrieval normalization is invalid.' );

		( new PlaygroundSemanticConfigurationResolver() )->resolve(
			$this->configuration(
				array(
					'semantic_retrieval' => array(
						'embedding_provider_id' => 'openai',
						'embedding_model_id'    => 'text-embedding-3-small',
						'dimensions'            => 1536,
						'normalization'         => 'unsupported',
						'distance'              => 'cosine',
						'vector_store_id'       => 'local',
					),
				)
			)
		);
	}

	/** Invalid persisted distance must fail before a vector collection can be reconstructed. */
	public function test_rejects_invalid_persisted_distance(): void {
		$this->expectException( UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Persisted semantic retrieval distance is invalid.' );

		( new PlaygroundSemanticConfigurationResolver() )->resolve(
			$this->configuration(
				array(
					'semantic_retrieval' => array(
						'embedding_provider_id' => 'openai',
						'embedding_model_id'    => 'text-embedding-3-small',
						'dimensions'            => 1536,
						'normalization'         => 'l2',
						'distance'              => 'unsupported',
						'vector_store_id'       => 'local',
					),
				)
			)
		);
	}

	/**
	 * Build one closed Playground configuration around persisted source config.
	 *
	 * @param array<string, mixed> $source_config Persisted knowledge-source configuration.
	 */
	private function configuration( array $source_config ): PlaygroundConfiguration {
		$now    = new DateTimeImmutable( '2026-09-10T10:00:00+00:00' );
		$bot    = new Bot(
			new BotId( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ),
			'Production Bot',
			true,
			'openai',
			'gpt-4.1-mini',
			1,
			'2026-09-10 10:00:00',
			'2026-09-10 10:00:00'
		);
		$source = new KnowledgeSourceRecord(
			7,
			'wordpress-posts',
			'wordpress_posts',
			null,
			'WordPress Posts',
			null,
			'indexed',
			$source_config,
			null,
			$now,
			$now,
			$now
		);

		return new PlaygroundConfiguration(
			$bot,
			new PlaygroundRetrievalConfiguration( $source, 'production-rag' )
		);
	}
}
