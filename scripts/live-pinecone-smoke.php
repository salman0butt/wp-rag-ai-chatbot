<?php
/**
 * Opt-in live Pinecone health smoke executed inside WP-CLI.
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
use WpRagAiChatbot\VectorStore\Pinecone\PineconeConfig;
use WpRagAiChatbot\VectorStore\Pinecone\PineconeVectorStore;

$endpoint   = getenv( 'PINECONE_INDEX_HOST' );
$api_key    = getenv( 'PINECONE_API_KEY' );
$index_name = getenv( 'PINECONE_INDEX' );

if ( ! is_string( $endpoint ) || '' === trim( $endpoint ) ) {
	fwrite( STDERR, "Live Pinecone smoke requires PINECONE_INDEX_HOST.\n" );
	exit( 2 );
}

if ( ! is_string( $api_key ) || '' === trim( $api_key ) ) {
	fwrite( STDERR, "Live Pinecone smoke requires PINECONE_API_KEY.\n" );
	exit( 2 );
}

if ( ! is_string( $index_name ) || '' === trim( $index_name ) ) {
	fwrite( STDERR, "Live Pinecone smoke requires PINECONE_INDEX.\n" );
	exit( 2 );
}

$profile = new VectorIndexProfile(
	new EmbeddingProfile( 'live-pinecone', 'health-check', 1, NormalizationMode::NONE ),
	DistanceMetric::COSINE
);
$store   = new PineconeVectorStore(
	new PineconeConfig( $endpoint, $api_key, $index_name ),
	$profile,
	new WordPressHttpTransport()
);
$health  = $store->health();

if ( ! $health->healthy ) {
	fwrite( STDERR, "Live Pinecone health check failed.\n" );
	exit( 1 );
}

fwrite( STDOUT, "Live Pinecone health check passed.\n" );
