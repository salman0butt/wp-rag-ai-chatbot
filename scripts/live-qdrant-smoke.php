<?php
/**
 * Opt-in live Qdrant health smoke executed inside WP-CLI.
 *
 * This file is intentionally never run by normal CI. The shell wrapper validates
 * explicit opt-in and credentials before invoking it.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Providers\Http\WordPressHttpTransport;
use WpRagAiChatbot\VectorStore\Qdrant\QdrantConfig;
use WpRagAiChatbot\VectorStore\Qdrant\QdrantVectorStore;

$endpoint = getenv( 'QDRANT_URL' );
$api_key  = getenv( 'QDRANT_API_KEY' );

if ( ! is_string( $endpoint ) || '' === trim( $endpoint ) ) {
	fwrite( STDERR, "Live Qdrant smoke requires QDRANT_URL.\n" );
	exit( 2 );
}

if ( ! is_string( $api_key ) || '' === trim( $api_key ) ) {
	fwrite( STDERR, "Live Qdrant smoke requires QDRANT_API_KEY.\n" );
	exit( 2 );
}

$profile = new VectorIndexProfile(
	new EmbeddingProfile( 'live-qdrant', 'health-check', 1, NormalizationMode::NONE ),
	DistanceMetric::COSINE
);
$store   = new QdrantVectorStore(
	new QdrantConfig( $endpoint, $api_key ),
	$profile,
	new WordPressHttpTransport()
);
$health  = $store->health();

if ( ! $health->healthy ) {
	fwrite( STDERR, "Live Qdrant health check failed.\n" );
	exit( 1 );
}

fwrite( STDOUT, "Live Qdrant health check passed.\n" );
